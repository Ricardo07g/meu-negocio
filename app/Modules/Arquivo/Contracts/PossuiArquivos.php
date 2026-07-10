<?php

declare(strict_types=1);

namespace App\Modules\Arquivo\Contracts;

use Illuminate\Database\Eloquent\Relations\{MorphMany, MorphOne};

/**
 * Implementado por models que anexam arquivos (via trait TemArquivos).
 *
 * Permite tipar o ArquivoService por este contrato mantendo o PHPStan feliz
 * ao chamar os metodos do trait sobre um dono generico.
 */
interface PossuiArquivos
{
    public function arquivos(): MorphMany;

    public function arquivoPrincipal(): MorphOne;

    /**
     * Configuracao das colecoes de arquivo do model.
     *
     * @return array<string, array<string, mixed>>
     */
    public function colecoesArquivo(): array;

    /**
     * @return array<string, mixed>
     */
    public function configColecao(string $colecao): array;

    public function diretorioBaseArquivos(string $colecao): string;

    /**
     * empresa_id a gravar no arquivo (null quando o dono e rede-level).
     */
    public function empresaIdParaArquivo(): ?int;
}
