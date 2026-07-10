<?php

declare(strict_types=1);

namespace App\Modules\Produto\Controllers;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Modules\Arquivo\Models\Arquivo;
use App\Modules\Arquivo\Services\ArquivoService;
use App\Modules\Produto\Models\Produto;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Endpoints AJAX da galeria de imagens do Produto.
 *
 * Edicao (produto existe): upload direto no path final.
 * Criacao (produto ainda nao existe): upload em staging ({sistema}/tmp/{token}/)
 * amarrado ao token da sessao; o ProdutoController@store move ao salvar.
 */
class ProdutoArquivoController extends Controller
{
    private const COLECAO = 'galeria';

    private const REGRAS_IMAGEM = ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    public function __construct(private ArquivoService $service) {}

    public function store(Request $request, Produto $produto): JsonResponse
    {
        $this->authorize('update', $produto);
        $request->validate(['arquivo' => self::REGRAS_IMAGEM]);

        try {
            $arquivo = $this->service->armazenar($produto, $request->file('arquivo'), self::COLECAO);
        } catch (NegocioException $e) {
            return response()->json(['erro' => $e->getMessage()], 422);
        }

        return response()->json(['arquivo' => $arquivo], 201);
    }

    public function destroy(Produto $produto, Arquivo $arquivo): JsonResponse
    {
        $this->authorize('update', $produto);
        $this->garantirDono($produto, $arquivo);

        $this->service->remover($arquivo);

        return response()->json(['ok' => true]);
    }

    public function reordenar(Request $request, Produto $produto): JsonResponse
    {
        $this->authorize('update', $produto);
        $dados = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->service->reordenar($produto, self::COLECAO, $dados['ids']);

        return response()->json(['ok' => true]);
    }

    public function principal(Produto $produto, Arquivo $arquivo): JsonResponse
    {
        $this->authorize('update', $produto);
        $this->garantirDono($produto, $arquivo);

        $this->service->definirPrincipal($produto, self::COLECAO, $arquivo->id);

        return response()->json(['ok' => true]);
    }

    public function storeRascunho(Request $request): JsonResponse
    {
        $this->authorize('create', Produto::class);
        $request->validate([
            'arquivo' => self::REGRAS_IMAGEM,
            'token' => ['required', 'string'],
        ]);

        $token = (string) $request->string('token');
        abort_unless($token === session('arquivo_rascunho_token'), 403);

        return response()->json($this->service->armazenarRascunho($request->file('arquivo'), $token), 201);
    }

    public function destroyRascunho(Request $request): JsonResponse
    {
        $this->authorize('create', Produto::class);
        $request->validate([
            'token' => ['required', 'string'],
            'caminho' => ['required', 'string'],
        ]);

        $token = (string) $request->string('token');
        abort_unless($token === session('arquivo_rascunho_token'), 403);

        $this->service->removerRascunho($token, (string) $request->string('caminho'));

        return response()->json(['ok' => true]);
    }

    private function garantirDono(Produto $produto, Arquivo $arquivo): void
    {
        abort_unless(
            $arquivo->anexavel_type === Produto::class && $arquivo->anexavel_id === $produto->id,
            404,
        );
    }
}
