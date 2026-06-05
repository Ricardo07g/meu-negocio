{{--
    Modal global de visualizacao/impressao de recibos e comprovantes.

    Incluido uma unica vez no layout principal. Para disparar:
    <a href="javascript:void(0)" class="..."
       data-bs-toggle="modal" data-bs-target="#modalRecibo"
       data-recibo-url="{{ route('...recibo...') }}"
       data-recibo-titulo="Comprovante #123">
       Imprimir
    </a>

    Listener pega o trigger via `event.relatedTarget`, le `data-recibo-url` e
    `data-recibo-titulo`, carrega no iframe. Botao "Imprimir" tenta
    `iframe.contentWindow.print()` (funciona em PDFs same-origin); se falhar,
    cai pro fallback de nova aba.
--}}
<div class="modal fade" id="modalRecibo" tabindex="-1" aria-labelledby="modalReciboTituloLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-recibo">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center mb-0" id="modalReciboTituloLabel">
                    <i class="feather-file-text me-2 text-primary"></i>
                    <span data-recibo-titulo>Comprovante</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0 modal-recibo-body">
                <div class="modal-recibo-loader" data-recibo-loader>
                    <div class="modal-recibo-spinner" aria-hidden="true"></div>
                    <p class="modal-recibo-loader-text">Gerando comprovante...</p>
                </div>
                <iframe data-recibo-iframe src="about:blank" title="Comprovante" class="modal-recibo-iframe"></iframe>
                <a href="#" target="_blank" rel="noopener" class="d-none" data-recibo-nova-aba aria-hidden="true"></a>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="feather-x me-1"></i>Sair
                </button>
                <button type="button" class="btn btn-primary px-4" data-recibo-imprimir>
                    <i class="feather-printer me-1"></i>Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

{{--
    OBS: NAO usamos @push('css') aqui porque o partial e incluido no fim do
    body, depois de @stack('css') ja ter sido renderizado em <head>. O push
    chegaria tarde demais. CSS inline em body funciona normalmente.
--}}
<style>
    /* Largura — usa duas classes pra ganhar especificidade contra `.modal-dialog` do Bootstrap/Duralux. */
    .modal-dialog.modal-recibo {
        max-width: min(1200px, 95vw);
        width: min(1200px, 95vw);
    }

    /* Altura explicita ativa o flex chain (modal-content ja e flex column no Bootstrap). */
    .modal-dialog.modal-recibo .modal-content {
        height: min(90vh, calc(100vh - 1rem));
        border: 0;
        border-radius: .75rem;
        overflow: hidden;
        box-shadow: 0 25px 70px rgba(0, 0, 0, .35);
    }

    .modal-dialog.modal-recibo .modal-header {
        flex-shrink: 0;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-bottom: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
    }
    .modal-dialog.modal-recibo .modal-header .modal-title { font-size: 1rem; font-weight: 600; }

    .modal-dialog.modal-recibo .modal-recibo-body {
        position: relative;
        background: #525659;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
        padding: 0;
    }

    .modal-dialog.modal-recibo .modal-recibo-iframe {
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
        background: #525659;
        opacity: 0;
        transition: opacity .35s ease;
    }
    .modal-dialog.modal-recibo .modal-recibo-iframe.is-ready { opacity: 1; }

    /* Loader: fundo solido cobrindo o iframe, spinner grande, texto claro. */
    .modal-dialog.modal-recibo .modal-recibo-loader {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #525659;
        z-index: 2;
        transition: opacity .35s ease;
        opacity: 1;
    }
    .modal-dialog.modal-recibo .modal-recibo-loader.is-hidden { opacity: 0; pointer-events: none; }

    .modal-dialog.modal-recibo .modal-recibo-spinner {
        width: 56px;
        height: 56px;
        border: 4px solid rgba(255, 255, 255, .15);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: modal-recibo-spin .85s linear infinite;
    }

    .modal-dialog.modal-recibo .modal-recibo-loader-text {
        color: rgba(255, 255, 255, .85);
        font-size: .9rem;
        font-weight: 500;
        letter-spacing: .02em;
        margin: 1.1rem 0 0;
    }

    @keyframes modal-recibo-spin {
        to { transform: rotate(360deg); }
    }

    .modal-dialog.modal-recibo .modal-footer {
        flex-shrink: 0;
        padding: .85rem 1.5rem;
        gap: .4rem;
        background: #fafafa;
        border-top: 1px solid #e9ecef;
    }
    .modal-dialog.modal-recibo .modal-footer .btn { font-weight: 500; }

    @media (max-width: 768px) {
        .modal-dialog.modal-recibo {
            max-width: 100vw;
            width: 100vw;
            margin: 0;
        }
        .modal-dialog.modal-recibo .modal-content {
            height: 100vh;
            border-radius: 0;
        }
    }
</style>

@push('js')
<script>
(function () {
    const modalEl = document.getElementById('modalRecibo');
    if (!modalEl) return;

    const iframe = modalEl.querySelector('[data-recibo-iframe]');
    const loader = modalEl.querySelector('[data-recibo-loader]');
    const titulo = modalEl.querySelector('[data-recibo-titulo]');
    const novaAba = modalEl.querySelector('[data-recibo-nova-aba]');
    const btnImprimir = modalEl.querySelector('[data-recibo-imprimir]');

    const MIN_LOADER_MS = 450;
    let loaderShownAt = 0;

    modalEl.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const url = trigger?.dataset?.reciboUrl;
        const tit = trigger?.dataset?.reciboTitulo || 'Comprovante';
        if (!url) return;

        titulo.textContent = tit;
        novaAba.href = url;
        iframe.classList.remove('is-ready');
        loader.classList.remove('is-hidden');
        loaderShownAt = Date.now();
        iframe.src = url;
    });

    iframe.addEventListener('load', function () {
        if (!iframe.src || iframe.src === 'about:blank') return;
        const elapsed = Date.now() - loaderShownAt;
        const remaining = Math.max(0, MIN_LOADER_MS - elapsed);
        setTimeout(function () {
            iframe.classList.add('is-ready');
            loader.classList.add('is-hidden');
        }, remaining);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        iframe.src = 'about:blank';
        iframe.classList.remove('is-ready');
        loader.classList.remove('is-hidden');
    });

    btnImprimir.addEventListener('click', function () {
        try {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        } catch (e) {
            window.open(novaAba.href, '_blank', 'noopener');
        }
    });
})();
</script>
@endpush
