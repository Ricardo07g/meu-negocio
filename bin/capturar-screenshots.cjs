#!/usr/bin/env node
/**
 * Recaptura as 5 screenshots do README (telas reais, logado como o usuario demo).
 * Mesmo padrao de .claude/skills/validar-implementacao/scripts/smoke.cjs.
 *
 * Pre-requisito (HOST, nao no container): google-chrome + puppeteer-core.
 *   npm install --no-save puppeteer-core
 *
 * Uso (a partir da raiz do projeto, com o ambiente Docker no ar):
 *   node bin/capturar-screenshots.cjs
 *
 * Env (todas opcionais):
 *   BASE_URL     default http://localhost:8080
 *   MN_EMAIL     default admin@teste.com
 *   MN_PASSWORD  default password
 *   OUT_DIR      default <repo>/docs/screenshots
 *   CHROME_BIN   caminho do Chrome/Chromium (senao tenta locais comuns)
 */
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const EMAIL = process.env.MN_EMAIL || 'admin@teste.com';
const PASSWORD = process.env.MN_PASSWORD || 'password';
const OUT_DIR = process.env.OUT_DIR || path.resolve(__dirname, '..', 'docs', 'screenshots');

const WIDTH = 1920;
const HEIGHT = 1080;

// Cada alvo: arquivo, rota, fullPage (ou altura fixa), seletor a aguardar, espera extra (ms).
const TARGETS = [
  { file: 'dashboard.png', route: '/dashboard', fullPage: true, wait: '.card', extra: 1800 },
  { file: 'agenda.png', route: '/agenda', fullPage: false, wait: null, extra: 2200 },
  { file: 'venda.png', route: '/vendas/nova', fullPage: true, wait: null, extra: 1200 },
  { file: 'contas-a-receber.png', route: '/contas-a-receber', fullPage: false, height: 1200, wait: null, extra: 1200 },
  { file: 'caixa.png', route: '/caixas', fullPage: false, height: 1200, wait: null, extra: 1200 },
];

function resolveChrome() {
  if (process.env.CHROME_BIN) return process.env.CHROME_BIN;
  const candidates = [
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/snap/bin/chromium',
  ];
  return candidates.find((p) => fs.existsSync(p));
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  let puppeteer;
  try {
    puppeteer = require('puppeteer-core');
  } catch (e) {
    console.error('ERRO: puppeteer-core indisponivel. Rode: npm install --no-save puppeteer-core');
    process.exit(3);
  }
  const executablePath = resolveChrome();
  if (!executablePath) {
    console.error('ERRO: Chrome/Chromium nao encontrado (defina CHROME_BIN).');
    process.exit(3);
  }
  fs.mkdirSync(OUT_DIR, { recursive: true });

  const browser = await puppeteer.launch({
    executablePath,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--hide-scrollbars', `--window-size=${WIDTH},${HEIGHT}`],
    defaultViewport: { width: WIDTH, height: HEIGHT, deviceScaleFactor: 1 },
  });

  const summary = [];
  try {
    const page = await browser.newPage();

    // --- login ---
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });
    await page.type('input[name=email]', EMAIL);
    await page.type('input[name=password]', PASSWORD);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
      page.click('button[type=submit]'),
    ]);
    console.log('login OK ->', page.url());

    for (const t of TARGETS) {
      await page.setViewport({ width: WIDTH, height: t.height || HEIGHT, deviceScaleFactor: 1 });
      const resp = await page.goto(`${BASE_URL}${t.route}`, { waitUntil: 'networkidle2' });
      const status = resp ? resp.status() : 0;
      if (t.wait) {
        try { await page.waitForSelector(t.wait, { timeout: 4000 }); } catch (_) {}
      }
      // Forca o sidebar expandido (remove o estado "minimenu" que aparece por timing
      // em algumas paginas) para manter as 5 capturas visualmente consistentes.
      await page.evaluate(() => {
        document.documentElement.classList.remove('minimenu', 'minimenu-hover');
        window.scrollTo(0, 0);
      });
      await sleep(t.extra || 800); // reflow do sidebar + render de graficos/calendario
      const dest = path.join(OUT_DIR, t.file);
      await page.screenshot({ path: dest, fullPage: !!t.fullPage });
      const size = fs.statSync(dest).size;
      summary.push({ file: t.file, route: t.route, finalUrl: page.url(), status, fullPage: !!t.fullPage, bytes: size });
      console.log(`captured ${t.file}  status=${status}  bytes=${size}  url=${page.url()}`);
    }
  } finally {
    await browser.close();
  }

  console.log('\n=== RESUMO ===');
  console.log(JSON.stringify(summary, null, 2));
  const bad = summary.filter((s) => s.status >= 400 || s.bytes < 8000);
  if (bad.length) {
    console.error('\nATENCAO: alvos suspeitos (status>=400 ou imagem muito pequena):', bad.map((b) => b.file).join(', '));
    process.exit(1);
  }
})().catch((e) => {
  console.error('FALHA na captura:', e.message);
  process.exit(1);
});
