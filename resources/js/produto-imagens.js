/**
 * Gerenciador de imagens do Produto (galeria).
 *
 * Funciona em dois modos:
 *  - criacao: uploads vao para o staging (tmp/{token}); os caminhos ficam em
 *    inputs ocultos `arquivos_rascunho[]` (na ordem) e o servidor move ao salvar.
 *  - edicao: uploads vao direto para o produto; excluir/reordenar chamam a API.
 *
 * A primeira imagem e sempre a capa.
 */

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function requisitar(method, url, { form, json } = {}) {
    const headers = {
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    };
    let body = form;
    if (json) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(json);
    }

    const res = await fetch(url, { method, headers, body });
    if (!res.ok) {
        let msg = 'Não foi possível concluir a operação.';
        try {
            const dados = await res.json();
            msg = dados.erro || dados.message || msg;
        } catch (e) { /* resposta sem corpo JSON */ }
        throw new Error(msg);
    }
    return res.status === 204 ? {} : res.json();
}

function initGaleria(raiz) {
    const cfgEl = raiz.querySelector('[data-galeria-config]');
    if (!cfgEl) return;

    const cfg = JSON.parse(cfgEl.textContent);
    const grid = raiz.querySelector('[data-galeria-grid]');
    const boxErro = raiz.querySelector('[data-galeria-erro]');
    const boxHidden = raiz.querySelector('[data-galeria-hidden]');
    const ehCriacao = cfg.modo === 'criacao';

    // Estado: cada item { chave, id?, caminho?, url, thumb_url }
    let seq = 0;
    const itens = (cfg.itens || []).map((i) => ({ chave: `s${seq++}`, ...i }));

    function erro(msg) {
        boxErro.textContent = msg || '';
        boxErro.classList.toggle('d-none', !msg);
    }

    function sincronizarHidden() {
        if (!ehCriacao) return;
        boxHidden.innerHTML = '';
        itens.forEach((it) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'arquivos_rascunho[]';
            input.value = it.caminho;
            boxHidden.appendChild(input);
        });
    }

    function render() {
        grid.innerHTML = '';
        itens.forEach((it, idx) => grid.appendChild(tile(it, idx)));

        if (itens.length < cfg.max) {
            grid.appendChild(tileAdd());
        }
        sincronizarHidden();
    }

    function tile(it, idx) {
        const el = document.createElement('div');
        el.className = 'galeria-item';
        el.draggable = true;
        el.dataset.chave = it.chave;

        // src via propriedade (nao interpola em HTML) — evita qualquer XSS.
        const img = document.createElement('img');
        img.alt = '';
        img.src = it.thumb_url || it.url;
        el.appendChild(img);

        const acoes = document.createElement('div');
        acoes.className = 'galeria-acoes';
        acoes.innerHTML =
            '<button type="button" data-acao="capa" title="Definir como capa"><i class="feather-star"></i></button>' +
            '<button type="button" data-acao="remover" title="Remover"><i class="feather-trash-2"></i></button>';
        el.appendChild(acoes);

        if (idx === 0) {
            const capa = document.createElement('span');
            capa.className = 'galeria-capa';
            capa.textContent = 'Capa';
            el.appendChild(capa);
        }

        acoes.querySelector('[data-acao="remover"]').addEventListener('click', () => remover(it));
        acoes.querySelector('[data-acao="capa"]').addEventListener('click', () => definirCapa(it));
        ligarArrasto(el);
        return el;
    }

    function tileAdd() {
        const label = document.createElement('label');
        label.className = 'galeria-add';
        label.innerHTML = '<i class="feather-plus"></i><span>Adicionar</span>';
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.multiple = true;
        input.hidden = true;
        input.addEventListener('change', () => {
            enviarArquivos([...input.files]);
            input.value = '';
        });
        label.appendChild(input);
        return label;
    }

    async function enviarArquivos(arquivos) {
        erro('');
        for (const arquivo of arquivos) {
            if (itens.length >= cfg.max) {
                erro(`Máximo de ${cfg.max} imagens.`);
                break;
            }
            try {
                await enviarUm(arquivo);
            } catch (e) {
                erro(e.message);
            }
        }
        render();
    }

    async function enviarUm(arquivo) {
        const form = new FormData();
        form.append('arquivo', arquivo);

        if (ehCriacao) {
            form.append('token', cfg.token);
            const d = await requisitar('POST', cfg.urls.rascunhoStore, { form });
            itens.push({ chave: `s${seq++}`, caminho: d.caminho, url: d.url, thumb_url: d.thumb_url });
        } else {
            const d = await requisitar('POST', cfg.urls.store, { form });
            itens.push({ chave: `s${seq++}`, id: d.arquivo.id, url: d.arquivo.url, thumb_url: d.arquivo.thumb_url });
        }
    }

    async function remover(it) {
        erro('');
        try {
            if (ehCriacao) {
                await requisitar('DELETE', cfg.urls.rascunhoDestroy, { json: { token: cfg.token, caminho: it.caminho } });
            } else {
                await requisitar('DELETE', `${cfg.urls.itemBase}/${it.id}`);
            }
            const i = itens.findIndex((x) => x.chave === it.chave);
            if (i > -1) itens.splice(i, 1);
            render();
        } catch (e) {
            erro(e.message);
        }
    }

    async function definirCapa(it) {
        const i = itens.findIndex((x) => x.chave === it.chave);
        if (i <= 0) return; // ja e a capa
        itens.splice(i, 1);
        itens.unshift(it);
        render();
        await persistirOrdem();
    }

    async function persistirOrdem() {
        if (ehCriacao) return; // ordem via inputs ocultos
        try {
            await requisitar('PATCH', cfg.urls.reordenar, { json: { ids: itens.map((x) => x.id) } });
        } catch (e) {
            erro(e.message);
        }
    }

    // ---- Reordenar por arrasto (HTML5 DnD) ----
    let arrastando = null;

    function ligarArrasto(el) {
        el.addEventListener('dragstart', () => {
            arrastando = el.dataset.chave;
            el.classList.add('arrastando');
        });
        el.addEventListener('dragend', () => el.classList.remove('arrastando'));
        el.addEventListener('dragover', (ev) => ev.preventDefault());
        el.addEventListener('drop', (ev) => {
            ev.preventDefault();
            const alvo = el.dataset.chave;
            if (!arrastando || arrastando === alvo) return;
            const de = itens.findIndex((x) => x.chave === arrastando);
            const para = itens.findIndex((x) => x.chave === alvo);
            if (de < 0 || para < 0) return;
            const [item] = itens.splice(de, 1);
            itens.splice(para, 0, item);
            render();
            persistirOrdem();
        });
    }

    render();
}

document.querySelectorAll('#galeria-produto').forEach(initGaleria);
