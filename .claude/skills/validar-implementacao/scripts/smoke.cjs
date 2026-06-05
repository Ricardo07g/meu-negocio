#!/usr/bin/env node
/**
 * Smoke headless de telas do Meu Negocio.
 *
 * Uso:
 *   node smoke.cjs "/rota"                 # so checa status + erros de console
 *   node smoke.cjs "/rota" "table"         # tambem exige um seletor CSS presente
 *   node smoke.cjs "/a" "/b" "/c"          # varias rotas (sem seletor)
 *
 * Env (todas opcionais):
 *   BASE_URL      default http://localhost:8080
 *   MN_EMAIL      default admin@teste.com
 *   MN_PASSWORD   default password
 *   CHROME_BIN    caminho do Chrome/Chromium (senao tenta locais comuns)
 *
 * Saida: JSON por rota { path, status, ok, consoleErrors, seletorPresente }.
 * Exit code != 0 se qualquer rota falhar (status>=400, erro de pagina, ou seletor ausente).
 *
 * Pre-requisito (no HOST, nao no container): google-chrome + puppeteer-core.
 */
const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const EMAIL = process.env.MN_EMAIL || 'admin@teste.com';
const PASSWORD = process.env.MN_PASSWORD || 'password';

function resolveChrome() {
  if (process.env.CHROME_BIN) return process.env.CHROME_BIN;
  const fs = require('fs');
  const candidates = [
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/snap/bin/chromium',
  ];
  return candidates.find((p) => fs.existsSync(p));
}

(async () => {
  const args = process.argv.slice(2);
  if (args.length === 0) {
    console.error('Uso: node smoke.cjs "/rota" ["seletorCss"]  |  node smoke.cjs "/a" "/b" ...');
    process.exit(2);
  }
  // 2 args onde o 2o nao comeca com "/" => (rota, seletor). Senao => lista de rotas.
  let targets;
  if (args.length === 2 && !args[1].startsWith('/')) {
    targets = [{ path: args[0], selector: args[1] }];
  } else {
    targets = args.map((p) => ({ path: p, selector: null }));
  }

  let puppeteer;
  try {
    puppeteer = require('puppeteer-core');
  } catch (e) {
    console.error('AVISO: puppeteer-core indisponivel — pulando smoke. Cubra a tela com um teste de view.');
    process.exit(3);
  }
  const executablePath = resolveChrome();
  if (!executablePath) {
    console.error('AVISO: Chrome/Chromium nao encontrado (defina CHROME_BIN) — pulando smoke.');
    process.exit(3);
  }

  const browser = await puppeteer.launch({
    executablePath,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--hide-scrollbars'],
    defaultViewport: { width: 1280, height: 900 },
  });

  const results = [];
  try {
    const page = await browser.newPage();
    // login
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[name=email]', EMAIL);
    await page.type('input[name=password]', PASSWORD);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
      page.click('button[type=submit]'),
    ]);

    for (const { path, selector } of targets) {
      const errors = [];
      const onError = (e) => errors.push(String(e.message || e));
      page.on('pageerror', onError);
      page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));

      const resp = await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle2' });
      const status = resp ? resp.status() : 0;
      let seletorPresente = null;
      if (selector) seletorPresente = (await page.$(selector)) !== null;

      const ok = status > 0 && status < 400 && errors.length === 0 && seletorPresente !== false;
      results.push({ path, status, ok, consoleErrors: errors, seletorPresente });

      page.off('pageerror', onError);
    }
  } finally {
    await browser.close();
  }

  console.log(JSON.stringify(results, null, 2));
  process.exit(results.every((r) => r.ok) ? 0 : 1);
})().catch((e) => {
  console.error('FALHA no smoke:', e.message);
  process.exit(1);
});
