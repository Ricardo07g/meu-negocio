#!/usr/bin/env node
/**
 * Smoke headless de telas do Meu Negocio.
 *
 * Uso:
 *   node smoke.cjs "/rota"                 # so checa status + erros de console
 *   node smoke.cjs "/rota" "table"         # tambem exige um seletor CSS presente
 *   node smoke.cjs "/a" "/b" "/c"          # varias rotas (sem seletor)
 *   node smoke.cjs --mobile "/a" "/b"      # viewport 375x812 + checagens de mobile
 *
 * Modo --mobile (viewport de celular, 375x812):
 *   - reprova rota com overflow horizontal (documentElement.scrollWidth > innerWidth)
 *     e aponta os elementos culpados (os mais internos que estouram);
 *   - reprova botao visivel com altura < 44px (alvo de toque);
 *   - salva screenshot de pagina inteira em storage/app/smoke/.
 *
 * Env (todas opcionais):
 *   BASE_URL      default http://localhost:8080
 *   MN_EMAIL      default admin@teste.com
 *   MN_PASSWORD   default password
 *   CHROME_BIN    caminho do Chrome/Chromium (senao tenta locais comuns)
 *   SMOKE_OUT     diretorio dos screenshots (default storage/app/smoke)
 *
 * Saida: JSON por rota. Exit code != 0 se qualquer rota falhar.
 *
 * Pre-requisito (no HOST, nao no container): google-chrome + puppeteer-core.
 */
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';
const EMAIL = process.env.MN_EMAIL || 'admin@teste.com';
const PASSWORD = process.env.MN_PASSWORD || 'password';
const OUT_DIR = process.env.SMOKE_OUT || path.resolve(process.cwd(), 'storage/app/smoke');

const ALVO_TOQUE_MIN = 44; // px — minimo confortavel para o dedo

function resolveChrome() {
  if (process.env.CHROME_BIN) return process.env.CHROME_BIN;
  const candidates = [
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/Applications/Chromium.app/Contents/MacOS/Chromium',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/snap/bin/chromium',
  ];
  return candidates.find((p) => fs.existsSync(p));
}

/**
 * Overflow horizontal da PAGINA.
 *
 * O que importa e o documento rolar de lado, nao um elemento passar da borda:
 * tabela dentro de .table-responsive (overflow-x: auto) e mais larga que a tela
 * de proposito e rola sozinha. Por isso os culpados sao filtrados para fora de
 * qualquer ancestral com scroll horizontal proprio.
 */
function coletarOverflow() {
  const doc = document.documentElement;
  const limite = doc.clientWidth;
  const paginaEstoura = doc.scrollWidth > limite + 1;

  const dentroDeScrollX = (el) => {
    for (let p = el.parentElement; p && p !== doc; p = p.parentElement) {
      const ox = getComputedStyle(p).overflowX;
      if (ox === 'auto' || ox === 'scroll') return true;
    }
    return false;
  };

  const todos = Array.from(document.querySelectorAll('body *')).filter((el) => {
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0 && r.right > limite + 1 && !dentroDeScrollX(el);
  });

  // Se um descendente tambem estoura, ele e a causa mais precisa — fica so a folha.
  const culpados = todos
    .filter((el) => !todos.some((o) => o !== el && el.contains(o)))
    .slice(0, 5)
    .map((el) => ({
      tag: el.tagName.toLowerCase(),
      classe: String(el.className || '').slice(0, 90),
      direita: Math.round(el.getBoundingClientRect().right),
      limite,
    }));

  return { paginaEstoura, larguraDocumento: doc.scrollWidth, limite, culpados };
}

/** Botoes visiveis abaixo do alvo minimo de toque. */
function coletarAlvosPequenos(min) {
  // .btn-close (X de alerta) e os botoes do SweetAlert sao do tema e ficam de fora.
  const ignorar = ['.btn-close', '.swal2-container *', '.hamburger *'];
  const visivel = (el) => {
    const st = getComputedStyle(el);
    if (st.visibility === 'hidden' || st.display === 'none' || st.opacity === '0') return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };

  // [data-bs-toggle=dropdown] entra porque o tema monta o "..." de acoes e o
  // avatar do header como <a class="avatar-text">, sem .btn nem role=button —
  // justamente os alvos mais tocados de uma listagem.
  return Array.from(document.querySelectorAll('a.btn, button, [role=button], [data-bs-toggle="dropdown"]'))
    .filter((el) => !ignorar.some((sel) => el.matches(sel)))
    .filter(visivel)
    .filter((el) => el.getBoundingClientRect().height < min - 0.5)
    .slice(0, 8)
    .map((el) => ({
      tag: el.tagName.toLowerCase(),
      classe: String(el.className || '').slice(0, 90),
      texto: (el.textContent || '').trim().slice(0, 40),
      altura: Math.round(el.getBoundingClientRect().height),
    }));
}

(async () => {
  let args = process.argv.slice(2);
  const mobile = args.includes('--mobile');
  args = args.filter((a) => a !== '--mobile');

  if (args.length === 0) {
    console.error('Uso: node smoke.cjs [--mobile] "/rota" ["seletorCss"]  |  node smoke.cjs [--mobile] "/a" "/b" ...');
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

  if (mobile) fs.mkdirSync(OUT_DIR, { recursive: true });

  const browser = await puppeteer.launch({
    executablePath,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--hide-scrollbars'],
    defaultViewport: mobile
      ? { width: 375, height: 812, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }
      : { width: 1280, height: 900 },
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

    for (const { path: rota, selector } of targets) {
      const errors = [];
      const onError = (e) => errors.push(String(e.message || e));
      const onConsole = (m) => m.type() === 'error' && errors.push(m.text());
      page.on('pageerror', onError);
      page.on('console', onConsole);

      const resp = await page.goto(`${BASE_URL}${rota}`, { waitUntil: 'networkidle2' });
      const status = resp ? resp.status() : 0;
      let seletorPresente = null;
      if (selector) seletorPresente = (await page.$(selector)) !== null;

      const resultado = { path: rota, status, consoleErrors: errors, seletorPresente };

      if (mobile) {
        const overflow = await page.evaluate(coletarOverflow);
        const alvosPequenos = await page.evaluate(coletarAlvosPequenos, ALVO_TOQUE_MIN);

        const arquivo = path.join(OUT_DIR, `${(rota === '/' ? 'raiz' : rota.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, ''))}.png`);
        await page.screenshot({ path: arquivo, fullPage: true });

        resultado.overflowHorizontal = overflow;
        resultado.alvosAbaixoDe44px = alvosPequenos;
        resultado.screenshot = arquivo;
        resultado.ok =
          status > 0 && status < 400 &&
          errors.length === 0 &&
          seletorPresente !== false &&
          !overflow.paginaEstoura &&
          alvosPequenos.length === 0;
      } else {
        resultado.ok = status > 0 && status < 400 && errors.length === 0 && seletorPresente !== false;
      }

      results.push(resultado);
      page.off('pageerror', onError);
      page.off('console', onConsole);
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
