#!/usr/bin/env node
/**
 * Clique real no modal "Novo Agendamento" da /agenda.
 *
 * Existe porque este defeito e invisivel para a porta de qualidade inteira: o
 * modal tem DUAS portas (clicar num horario da grade e o botao da sidebar, que
 * e Bootstrap puro) e o submit ficava ligado so pela primeira. Pela sidebar o
 * form abria sem handler e, como nao tem `action` nem `method`, o clique em
 * "Agendar" virava GET nativo para a propria URL: nada criado, nada no console,
 * suite verde. Teste HTTP nao pega — o endpoint sempre funcionou.
 *
 * Uso (no HOST, nao no container — precisa de Chrome + puppeteer-core):
 *   NODE_PATH=$(npm root -g) node .claude/skills/validar-implementacao/scripts/clique-agenda.cjs [--navegacao]
 *
 * Env: BASE_URL (default http://localhost:8080), MN_EMAIL, MN_PASSWORD, CHROME_BIN.
 *
 * Nao roda no CI: o workflow e so PHP, sem navegador. E porta manual, como o smoke.
 * Sai != 0 se o clique nao gerar o POST de criacao.
 *
 * Com `--navegacao`, testa outra coisa: as setas de semana. O relogio do
 * navegador e fixado as 10:30 de hoje — dentro do expediente, com a linha do
 * "agora" visivel. Era exatamente ai que um template do Toast UI chamava
 * `toLocaleTimeString()` num TZDate, o render lancava e a grade congelava na
 * semana corrente: o titulo mudava, os dias e os atendimentos nao. Fora do
 * expediente (a noite, quando alguem testava) a linha nao aparecia e o bug
 * sumia. Sai != 0 se houver erro de pagina ou se a grade nao acompanhar as setas.
 */
const fs = require('fs');
const puppeteer = require('puppeteer-core');

const BASE = process.env.BASE_URL || 'http://localhost:8080';
const EMAIL = process.env.MN_EMAIL || 'admin@teste.com';
const PASSWORD = process.env.MN_PASSWORD || 'password';

function resolveChrome() {
    if (process.env.CHROME_BIN) return process.env.CHROME_BIN;
    return [
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Chromium.app/Contents/MacOS/Chromium',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
    ].find((p) => fs.existsSync(p));
}

const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

/** Digita no autocomplete e escolhe a primeira sugestao (a busca exige 2+ chars). */
async function escolherNaLista(page, inputId, texto) {
    await page.click(`#${inputId}`);
    await page.type(`#${inputId}`, texto, { delay: 40 });
    await esperar(1200);
    const opcoes = await page.$$(`#${inputId} ~ .ajax-search-dropdown > div`);
    if (!opcoes.length) return false;
    await opcoes[0].click();
    await esperar(300);
    return true;
}

/** Cabecalho de dias da grade: e ele que prova que a semana andou, nao o titulo. */
const diasDaGrade = (page) => page.$$eval('.toastui-calendar-day-name-item', (els) => els.map((e) => e.textContent.trim()).join(' | '));

async function navegacao(page, consoleErros) {
    await page.goto(`${BASE}/agenda`, { waitUntil: 'networkidle2' });
    await esperar(800);

    const passos = [{ passo: 'inicial', dias: await diasDaGrade(page), agoraVisivel: !!(await page.$('.toastui-calendar-timegrid-now-indicator')) }];

    for (const [botao, rotulo] of [['#cal-next', 'proxima'], ['#cal-next', 'proxima'], ['#cal-prev', 'anterior'], ['#cal-today', 'hoje']]) {
        await page.click(botao);
        await esperar(900);
        passos.push({ passo: rotulo, titulo: await page.$eval('#cal-range', (e) => e.textContent), dias: await diasDaGrade(page) });
    }

    const [inicial, prox1, prox2, anterior, hoje] = passos;
    const andou = prox1.dias !== inicial.dias && prox2.dias !== prox1.dias && anterior.dias === prox1.dias && hoje.dias === inicial.dias;

    return { passos, consoleErros, ok: andou && inicial.agoraVisivel && consoleErros.length === 0 };
}

(async () => {
    const modoNavegacao = process.argv.includes('--navegacao');
    const browser = await puppeteer.launch({
        executablePath: resolveChrome(),
        headless: 'new',
        args: ['--no-sandbox', '--disable-gpu'],
        defaultViewport: { width: 1400, height: 950 },
    });
    const page = await browser.newPage();

    if (modoNavegacao) {
        // Relogio adiantado/atrasado para as 10:30 de hoje, preservando a data.
        await page.evaluateOnNewDocument(() => {
            const alvo = new Date();
            alvo.setHours(10, 30, 0, 0);
            const delta = alvo.getTime() - Date.now();
            const DateReal = Date;
            // eslint-disable-next-line no-global-assign
            Date = class extends DateReal {
                constructor(...args) { super(...(args.length ? args : [DateReal.now() + delta])); }
                static now() { return DateReal.now() + delta; }
            };
        });
    }

    const posts = [];
    const consoleErros = [];
    page.on('console', (m) => m.type() === 'error' && consoleErros.push(m.text()));
    page.on('pageerror', (e) => consoleErros.push('pageerror: ' + e.message));
    page.on('request', (r) => r.method() === 'POST' && posts.push(r.url().replace(BASE, '')));

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[name=email]', EMAIL);
    await page.type('input[name=password]', PASSWORD);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.click('button[type=submit]'),
    ]);

    if (modoNavegacao) {
        const r = await navegacao(page, consoleErros);
        console.log(JSON.stringify(r, null, 2));
        await browser.close();
        process.exit(r.ok ? 0 : 1);
    }

    await page.goto(`${BASE}/agenda`, { waitUntil: 'networkidle2' });

    // A porta que estava quebrada: o botao da sidebar, nao a grade.
    await page.click('[data-bs-target="#modalNovoAgendamento"]');
    await esperar(700);

    // O termo vem do proprio banco: o autocomplete so dispara com 2+ caracteres
    // (`value.trim()`), entao chutar letra nao serve — e um termo que nao casa
    // faria o script reprovar por falta de dado, nao por defeito.
    const termo = (rota) => page.evaluate(async (url) => {
        // Cliente E servidor cortam abaixo de 2 caracteres, entao o sorteio
        // comeca em pares comuns em portugues e para no primeiro que casar.
        for (const tentativa of ['de', 'ra', 'an', 'ma', 'co', 'li', 'sa', 'er']) {
            const r = await fetch(`${url}?q=${tentativa}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const itens = await r.json();
            if (itens.length) return String(itens[0].nome).slice(0, 3);
        }
        return null;
    }, rota);

    const cliente = await escolherNaLista(page, 'agenda-cliente-input', await termo('/clientes/buscar'));
    const servico = await escolherNaLista(page, 'agenda-servico-input', await termo('/servicos/buscar'));
    await page.select(
        'select[name=atendente_id]',
        await page.$eval('select[name=atendente_id] option:nth-child(2)', (o) => o.value),
    );

    const d = new Date(Date.now() + 86400000);
    const pad = (n) => String(n).padStart(2, '0');
    await page.$eval(
        'input[name=inicio]',
        (el, v) => { el.value = v; },
        `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T10:00`,
    );

    const urlAntes = page.url();
    const postsAntes = posts.length;

    await page.click('#formNovoAgendamento button[type=submit]');
    await esperar(2500);

    const criou = posts.slice(postsAntes).some((u) => u.includes('/agenda/criar-rapido'));
    const navegou = page.url() !== urlAntes;

    const resultado = {
        autocomplete: { cliente, servico },
        postsAposClique: posts.slice(postsAntes),
        houveNavegacaoNativa: navegou,
        consoleErros,
        ok: criou && !navegou,
    };
    console.log(JSON.stringify(resultado, null, 2));

    await browser.close();
    process.exit(resultado.ok ? 0 : 1);
})().catch((e) => {
    console.error('FALHOU:', e.message);
    process.exit(1);
});
