@php
    $entidade = $entidade ?? null;
    $galeriaItens = $entidade
        ? $entidade->arquivosDaColecao('galeria')->map(fn ($a) => [
            'id' => $a->id,
            'url' => $a->url,
            'thumb_url' => $a->thumb_url,
        ])->values()->all()
        : [];

    $galeriaConfig = [
        'modo' => $entidade ? 'edicao' : 'criacao',
        'max' => 8,
        'token' => $tokenRascunho ?? null,
        'urls' => [
            'rascunhoStore' => route('produtos.arquivos.rascunho.store'),
            'rascunhoDestroy' => route('produtos.arquivos.rascunho.destroy'),
            'store' => $entidade ? route('produtos.arquivos.store', $entidade) : null,
            'reordenar' => $entidade ? route('produtos.arquivos.reordenar', $entidade) : null,
            'itemBase' => $entidade ? url('produtos/'.$entidade->getKey().'/arquivos') : null,
        ],
        'itens' => $galeriaItens,
    ];
@endphp

{{-- Imagens --}}
<div class="card stretch stretch-full">
    <div class="card-header">
        <h5 class="card-title">Imagens</h5>
        <div class="card-header-action">
            <small class="text-muted">Até {{ $galeriaConfig['max'] }} imagens · arraste para ordenar · a primeira é a capa</small>
        </div>
    </div>
    <div class="card-body">
        <div id="galeria-produto">
            <script type="application/json" data-galeria-config>@json($galeriaConfig)</script>
            <div class="galeria-grid" data-galeria-grid></div>
            <div class="text-danger small mt-2 d-none" data-galeria-erro></div>
            <div data-galeria-hidden></div>
        </div>
    </div>
</div>

@push('css')
    <style>
        .galeria-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .galeria-item, .galeria-add {
            position: relative; width: 116px; height: 116px; border-radius: 10px;
            overflow: hidden; border: 1px solid var(--bs-border-color, #e5e7eb);
            background: var(--bs-tertiary-bg, #f8f9fa);
        }
        .galeria-item { cursor: grab; }
        .galeria-item.arrastando { opacity: .4; }
        .galeria-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .galeria-item .galeria-acoes {
            position: absolute; top: 6px; right: 6px; display: flex; gap: 4px; opacity: 0;
            transition: opacity .15s ease;
        }
        .galeria-item:hover .galeria-acoes { opacity: 1; }
        .galeria-acoes button {
            border: 0; width: 26px; height: 26px; border-radius: 6px; line-height: 1;
            background: rgba(0,0,0,.6); color: #fff; display: inline-flex; align-items: center; justify-content: center;
        }
        .galeria-acoes button:hover { background: rgba(0,0,0,.85); }
        .galeria-capa {
            position: absolute; bottom: 0; left: 0; right: 0; text-align: center;
            font-size: 11px; padding: 2px 0; background: #3454d1; color: #fff;
        }
        .galeria-add {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 4px; cursor: pointer; color: var(--bs-secondary-color, #6b7280);
            border-style: dashed; font-size: 12px;
        }
        .galeria-add:hover { color: #3454d1; border-color: #3454d1; }
        .galeria-add i { font-size: 22px; }
        .galeria-item.carregando::after {
            content: ''; position: absolute; inset: 0; background: rgba(255,255,255,.6);
        }
    </style>
@endpush

@push('js')
    @vite('resources/js/produto-imagens.js')
@endpush
