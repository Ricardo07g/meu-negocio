/**
 * Relogio do "Meu Negocio".
 *
 * Workers nao executa PHP: este Worker nao roda tarefa nenhuma. Ele so existe
 * para BATER na aplicacao no horario — um POST autenticado em /cron/executar,
 * onde o Laravel roda as tarefas que ficaram devidas (ver docs/ADR/0016).
 *
 * Em producao a aplicacao vive num unico servico do Railway com App Sleeping
 * ligado, sem processo `schedule:work`. Este ping acorda o container e dispara
 * o trabalho na mesma tacada.
 */

const CAMINHO = "/cron/executar";
const TENTATIVAS = 2;
const ESPERA_ENTRE_TENTATIVAS_MS = 5000;

const espera = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function acionarAgendador(env) {
  if (!env.APP_URL || !env.CRON_TOKEN) {
    throw new Error(
      "APP_URL (wrangler.toml) e CRON_TOKEN (wrangler secret put) sao obrigatorios",
    );
  }

  const url = `${env.APP_URL.replace(/\/+$/, "")}${CAMINHO}`;

  for (let tentativa = 1; tentativa <= TENTATIVAS; tentativa++) {
    try {
      const resposta = await fetch(url, {
        method: "POST",
        headers: {
          "X-Cron-Token": env.CRON_TOKEN,
          Accept: "application/json",
        },
      });

      const corpo = await resposta.text();

      if (resposta.ok) {
        console.log(`agendador ok (tentativa ${tentativa}): ${corpo}`);
        return;
      }

      console.warn(
        `agendador respondeu ${resposta.status} (tentativa ${tentativa}): ${corpo}`,
      );
    } catch (erro) {
      // A primeira tentativa costuma pegar o container ainda acordando do sleep.
      console.warn(`agendador inacessivel (tentativa ${tentativa}): ${erro}`);
    }

    if (tentativa < TENTATIVAS) {
      await espera(ESPERA_ENTRE_TENTATIVAS_MS);
    }
  }

  // Lancar marca a invocacao como falha nas metricas do Worker e no `wrangler tail`.
  throw new Error(`agendador nao respondeu apos ${TENTATIVAS} tentativas`);
}

export default {
  async scheduled(event, env, ctx) {
    ctx.waitUntil(acionarAgendador(env));
  },
};
