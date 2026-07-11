/**
 * Upload de imagem unica (avatar) com recorte.
 *
 * Usado pelo componente Blade <x-campo-imagem> (Cliente, Usuario, Meu Perfil,
 * Servico). Ao escolher um arquivo local, abre um modal com o Cropper.js para
 * recorte 1:1; o resultado e gravado de volta no <input type="file"> e enviado
 * no submit multipart normal (sem AJAX). Nao ha mudanca de backend.
 *
 * O recorte roda apenas em arquivo recem-escolhido (object URL, mesma origem);
 * a imagem ja salva no R2 nao e reaberta no cropper (evita canvas tainted/CORS).
 */

import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const MIMES_OK = ['image/jpeg', 'image/png', 'image/webp'];
const EXT_POR_TIPO = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };
const SAIDA = { largura: 512, altura: 512, qualidade: 0.9 };

/** Troca a extensao do nome do arquivo para casar com o tipo de saida. */
function renomear(nome, tipo) {
    const ext = EXT_POR_TIPO[tipo] || 'jpg';
    const base = (nome || 'foto').replace(/\.[^.]+$/, '');
    return `${base}.${ext}`;
}

// ---------------------------------------------------------------------------
// Modal de recorte — instancia unica, compartilhada por todos os campos
// ---------------------------------------------------------------------------
const modal = (() => {
    let el = null;
    let instancia = null; // bootstrap.Modal quando disponivel
    let cropper = null;
    let stage = null;
    let img = null;

    let cbConfirmar = null;
    let cbCancelar = null;
    let tipoSaida = 'image/jpeg';
    let arredondado = false;
    let confirmou = false;

    function construir() {
        el = document.createElement('div');
        el.className = 'modal fade ci-crop-modal';
        el.id = 'ci-crop-modal';
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = `
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Recortar imagem</h5>
                        <button type="button" class="btn-close" data-ci-cancel aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ci-crop-stage"><img data-ci-crop-img alt="Imagem para recorte"></div>
                        <div class="ci-crop-tools" role="group" aria-label="Ferramentas de recorte">
                            <button type="button" class="btn btn-light" data-ci-zoom="0.1" title="Aproximar" aria-label="Aproximar"><i class="feather-zoom-in"></i></button>
                            <button type="button" class="btn btn-light" data-ci-zoom="-0.1" title="Afastar" aria-label="Afastar"><i class="feather-zoom-out"></i></button>
                            <button type="button" class="btn btn-light" data-ci-rotate="-90" title="Girar à esquerda" aria-label="Girar à esquerda"><i class="feather-rotate-ccw"></i></button>
                            <button type="button" class="btn btn-light" data-ci-rotate="90" title="Girar à direita" aria-label="Girar à direita"><i class="feather-rotate-cw"></i></button>
                            <button type="button" class="btn btn-light" data-ci-reset title="Restaurar" aria-label="Restaurar"><i class="feather-maximize"></i></button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-ci-cancel>Cancelar</button>
                        <button type="button" class="btn btn-primary" data-ci-apply><i class="feather-check me-1"></i>Aplicar</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(el);

        stage = el.querySelector('.ci-crop-stage');
        img = el.querySelector('[data-ci-crop-img]');

        el.querySelectorAll('[data-ci-zoom]').forEach((b) =>
            b.addEventListener('click', () => cropper && cropper.zoom(parseFloat(b.dataset.ciZoom))));
        el.querySelectorAll('[data-ci-rotate]').forEach((b) =>
            b.addEventListener('click', () => cropper && cropper.rotate(parseInt(b.dataset.ciRotate, 10))));
        el.querySelector('[data-ci-reset]').addEventListener('click', () => cropper && cropper.reset());
        el.querySelectorAll('[data-ci-cancel]').forEach((b) => b.addEventListener('click', esconder));
        el.querySelector('[data-ci-apply]').addEventListener('click', aplicar);

        if (window.bootstrap && window.bootstrap.Modal) {
            instancia = window.bootstrap.Modal.getOrCreateInstance(el);
            el.addEventListener('shown.bs.modal', iniciarCropper);
            el.addEventListener('hidden.bs.modal', aoFechar);
        }
    }

    function iniciarCropper() {
        destruirCropper();
        stage.classList.toggle('is-round', arredondado);
        cropper = new Cropper(img, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            dragMode: 'move',
            background: false,
            responsive: true,
            restore: false,
            guides: false,
            center: true,
            highlight: false,
            toggleDragModeOnDblclick: false,
        });
    }

    function destruirCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    /** Fim do ciclo do modal: limpa o cropper e dispara o cancelamento se nao confirmou. */
    function aoFechar() {
        destruirCropper();
        if (!confirmou && cbCancelar) cbCancelar();
        cbConfirmar = null;
        cbCancelar = null;
    }

    function abrir({ url, round, tipo, onConfirmar, onCancelar }) {
        if (!el) construir();
        confirmou = false;
        arredondado = !!round;
        tipoSaida = tipo;
        cbConfirmar = onConfirmar;
        cbCancelar = onCancelar;
        img.src = url;

        if (instancia) {
            instancia.show();
        } else {
            // Fallback sem o JS do Bootstrap (o layout admin sempre o carrega).
            el.classList.add('show');
            el.style.display = 'block';
            document.body.classList.add('modal-open');
            requestAnimationFrame(iniciarCropper);
        }
    }

    function esconder() {
        if (instancia) {
            instancia.hide();
        } else {
            el.classList.remove('show');
            el.style.display = 'none';
            document.body.classList.remove('modal-open');
            aoFechar();
        }
    }

    function aplicar() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({
            width: SAIDA.largura,
            height: SAIDA.altura,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        canvas.toBlob(
            (blob) => {
                confirmou = true;
                if (blob && cbConfirmar) cbConfirmar(blob);
                esconder();
            },
            tipoSaida,
            SAIDA.qualidade,
        );
    }

    return { abrir };
})();

// ---------------------------------------------------------------------------
// Ligacao de cada campo <x-campo-imagem>
// ---------------------------------------------------------------------------
function ligarCampo(raiz) {
    const well = raiz.querySelector('[data-ci-well]');
    const input = raiz.querySelector('[data-ci-input]');
    const preview = raiz.querySelector('[data-ci-preview]');
    const placeholder = raiz.querySelector('[data-ci-placeholder]');
    const btnRemover = raiz.querySelector('[data-ci-remove]');
    const btnAlterar = raiz.querySelector('[data-ci-change]');
    const btnAlterarTxt = raiz.querySelector('[data-ci-change-txt]');
    const flag = raiz.querySelector('[data-ci-remove-flag]');
    const round = raiz.dataset.formato === 'circulo';

    let arquivoAplicado = null; // File recortado atualmente no input (para reverter no cancelar)

    function mostrar(url) {
        preview.src = url;
        preview.hidden = false;
        placeholder.hidden = true;
        if (btnRemover) btnRemover.hidden = false;
        if (btnAlterarTxt) btnAlterarTxt.textContent = 'Alterar';
        if (flag) flag.value = '0';
    }

    function limpar() {
        input.value = '';
        arquivoAplicado = null;
        preview.removeAttribute('src');
        preview.hidden = true;
        placeholder.hidden = false;
        if (btnRemover) btnRemover.hidden = true;
        if (btnAlterarTxt) btnAlterarTxt.textContent = 'Enviar';
        if (flag) flag.value = '1'; // remove a imagem existente ao salvar
    }

    function gravarNoInput(file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        arquivoAplicado = file;
    }

    /** Restaura o input ao ultimo arquivo aplicado (ou vazio) — usado ao cancelar. */
    function reverterInput() {
        const dt = new DataTransfer();
        if (arquivoAplicado) dt.items.add(arquivoAplicado);
        input.files = dt.files;
    }

    function abrirSeletor() {
        input.click();
    }

    well.addEventListener('click', abrirSeletor);
    well.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            abrirSeletor();
        }
    });
    if (btnAlterar) btnAlterar.addEventListener('click', abrirSeletor);

    input.addEventListener('change', () => {
        const f = input.files && input.files[0];
        if (!f) return;
        if (!MIMES_OK.includes(f.type)) {
            reverterInput();
            return;
        }

        const url = URL.createObjectURL(f);
        const tipo = f.type;
        const nome = renomear(f.name, tipo);

        modal.abrir({
            url,
            round,
            tipo,
            onCancelar: () => {
                URL.revokeObjectURL(url);
                reverterInput();
            },
            onConfirmar: (blob) => {
                URL.revokeObjectURL(url);
                const file = new File([blob], nome, { type: tipo });
                gravarNoInput(file);
                mostrar(URL.createObjectURL(file));
            },
        });
    });

    if (btnRemover) {
        btnRemover.addEventListener('click', (e) => {
            e.stopPropagation();
            limpar();
        });
    }

    ['dragenter', 'dragover'].forEach((ev) =>
        well.addEventListener(ev, (e) => {
            e.preventDefault();
            well.classList.add('ci-drag');
        }));
    ['dragleave', 'dragend', 'drop'].forEach((ev) =>
        well.addEventListener(ev, () => well.classList.remove('ci-drag')));
    well.addEventListener('drop', (e) => {
        e.preventDefault();
        const f = e.dataTransfer.files && e.dataTransfer.files[0];
        if (!f) return;
        const dt = new DataTransfer();
        dt.items.add(f);
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
    });
}

function iniciar() {
    document.querySelectorAll('[data-campo-imagem]').forEach(ligarCampo);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
} else {
    iniciar();
}
