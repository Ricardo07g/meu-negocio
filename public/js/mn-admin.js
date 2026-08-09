/**
 * JS proprio do sistema — espelha o papel do public/css/mn-admin.css: carregado
 * com asset() nos dois layouts, fora do Vite (o layout admin usa os assets
 * pre-compilados do Duralux, nao entries de build).
 *
 * ── Loading padronizado de submit ──────────────────────────────────────────
 *
 * Um unico listener delegado no documento: ao submeter qualquer formulario, o
 * botao que disparou o envio troca o rotulo por um spinner e trava. Resolve dois
 * problemas de uma vez — o usuario deixa de achar que "nao clicou" em operacoes
 * que demoram (upload de imagem sobe para o R2, duas viagens de rede) e o duplo
 * clique para de gerar registro duplicado.
 *
 * Usa `.spinner-border` do Bootstrap, ja carregado nos dois layouts — sem CSS novo.
 *
 * Para desligar num formulario especifico: `<form data-sem-loading>`.
 * Para trocar o texto: `<button data-texto-carregando="Enviando...">`.
 */

(function () {
    'use strict';

    const SELETOR_SUBMIT = 'button[type="submit"], input[type="submit"], button:not([type])';

    // Guarda os nos originais do botao (nao o HTML como texto): restaurar por
    // innerHTML reinjetaria markup e perderia os listeners dos filhos, alem de
    // transformar um data-attribute qualquer em vetor de XSS.
    const conteudoOriginal = new WeakMap();

    function textoDeCarregando(botao) {
        return botao.dataset.textoCarregando || 'Salvando...';
    }

    function acender(botao) {
        if (!botao || botao.dataset.carregando === 'true') {
            return;
        }

        botao.dataset.carregando = 'true';

        if (botao.tagName === 'INPUT') {
            conteudoOriginal.set(botao, botao.value);
            botao.value = textoDeCarregando(botao);
        } else {
            conteudoOriginal.set(botao, [...botao.childNodes]);

            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');

            botao.replaceChildren(spinner, document.createTextNode(textoDeCarregando(botao)));
        }

        botao.setAttribute('aria-busy', 'true');

        // Desabilitar so no proximo tick: o par name/value de um botao de submit e
        // serializado DEPOIS deste evento, e um botao ja desabilitado nao entra no
        // payload — perderia silenciosamente a acao escolhida em forms com mais de
        // um botao.
        setTimeout(() => {
            botao.disabled = true;
        }, 0);
    }

    function apagar(botao) {
        if (!botao || botao.dataset.carregando !== 'true') {
            return;
        }

        const original = conteudoOriginal.get(botao);

        if (botao.tagName === 'INPUT') {
            if (typeof original === 'string') {
                botao.value = original;
            }
        } else if (Array.isArray(original)) {
            botao.replaceChildren(...original);
        }

        conteudoOriginal.delete(botao);
        botao.disabled = false;
        botao.removeAttribute('aria-busy');
        delete botao.dataset.carregando;
    }

    document.addEventListener('submit', (evento) => {
        const form = evento.target;

        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-sem-loading')) {
            return;
        }

        // Fase de bolha, depois dos listeners do proprio form: um submit barrado
        // (por exemplo o `data-confirm` do SweetAlert, que cancela para perguntar
        // antes) nao pode acender o spinner — a confirmacao dispara outro submit.
        if (evento.defaultPrevented) {
            return;
        }

        acender(evento.submitter || form.querySelector(SELETOR_SUBMIT));
    });

    // Voltar pelo historico devolve a pagina do cache do navegador com o botao ainda
    // travado; sem isto, o formulario fica inutilizavel ate um F5.
    window.addEventListener('pageshow', (evento) => {
        if (!evento.persisted) {
            return;
        }

        document.querySelectorAll('[data-carregando="true"]').forEach(apagar);
    });
})();
