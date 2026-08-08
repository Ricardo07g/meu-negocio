<?php

declare(strict_types=1);

namespace App\Traits;

use App\Modules\Arquivo\Contracts\PossuiArquivos;
use App\Modules\Arquivo\Services\ArquivoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{Request, UploadedFile};

/**
 * Aplica a imagem unica (avatar) de um formulario ao seu dono, considerando
 * tambem a imagem estacionada por um erro de validacao anterior
 * (ver a trait PreservaImagemRascunho).
 *
 * Precedencia: arquivo novo > rascunho estacionado > remocao. Um upload novo
 * sempre vence o que ficou estacionado, e marcar "Remover" so vale quando nao
 * veio imagem nenhuma.
 */
trait SincronizaImagemUnica
{
    protected function sincronizarImagem(
        Model&PossuiArquivos $dono,
        Request $request,
        string $campo = 'foto',
        string $colecao = 'avatar',
    ): void {
        $servico = app(ArquivoService::class);

        $arquivo = $request->file($campo);
        $rascunho = trim((string) $request->input("{$campo}_rascunho", ''));
        $token = (string) $request->session()->get(ArquivoService::SESSAO_TOKEN_UNICO, '');

        if ($arquivo instanceof UploadedFile) {
            $servico->sincronizarUnico($dono, $colecao, $arquivo);
        } elseif ($rascunho !== '' && $token !== '') {
            $servico->anexarRascunhoUnico($dono, $colecao, $token, $rascunho);
        } elseif ($request->boolean("remover_{$campo}")) {
            $servico->sincronizarUnico($dono, $colecao, null, remover: true);
        }

        // Salvou: o token cumpriu o papel. Sobras morrem no arquivos:limpar-rascunhos.
        $request->session()->forget(ArquivoService::SESSAO_TOKEN_UNICO);
    }
}
