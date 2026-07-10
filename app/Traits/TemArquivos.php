<?php

declare(strict_types=1);

namespace App\Traits;

use App\Modules\Arquivo\Models\Arquivo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\{MorphMany, MorphOne};

/**
 * Habilita um model a anexar arquivos (imagens, PDFs, etc.) via a tabela
 * polimorfica `arquivos`, agrupados por `colecao`.
 *
 * O model deve `implements App\Modules\Arquivo\Contracts\PossuiArquivos` e pode
 * sobrescrever `colecoesArquivo()` para declarar comportamento por colecao:
 *
 *   protected function colecoesArquivo(): array
 *   {
 *       return [
 *           'galeria' => ['mimes' => ['jpg','jpeg','png','webp'], 'max_kb' => 4096, 'unica' => false, 'max' => 8],
 *           'avatar'  => ['mimes' => ['jpg','jpeg','png','webp'], 'max_kb' => 2048, 'unica' => true],
 *       ];
 *   }
 *
 * Para entidades transacionais (por empresa), defina
 * `protected bool $arquivosPorEmpresa = true;` para aninhar o path por empresa.
 */
trait TemArquivos
{
    public function arquivos(): MorphMany
    {
        return $this->morphMany(Arquivo::class, 'anexavel')->orderBy('ordem');
    }

    /**
     * Imagem/arquivo de capa (principal). Eager-loadavel para listagens
     * (`with('arquivoPrincipal')`) evitando N+1.
     */
    public function arquivoPrincipal(): MorphOne
    {
        return $this->morphOne(Arquivo::class, 'anexavel')->where('principal', true);
    }

    /**
     * @return Collection<int, Arquivo>
     */
    public function arquivosDaColecao(string $colecao): Collection
    {
        /** @var Collection<int, Arquivo> $arquivos */
        $arquivos = $this->arquivos;

        return $arquivos->where('colecao', $colecao)->values();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function colecoesArquivo(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function configColecao(string $colecao): array
    {
        return $this->colecoesArquivo()[$colecao] ?? [];
    }

    public function empresaIdParaArquivo(): ?int
    {
        if (($this->arquivosPorEmpresa ?? false) && $this->getAttribute('empresa_id')) {
            return (int) $this->getAttribute('empresa_id');
        }

        return null;
    }

    /**
     * Monta o diretorio base no bucket:
     *   {sistema}/redes/{rede}/[empresas/{empresa}/]{tabela}/{id}/{colecao}
     */
    public function diretorioBaseArquivos(string $colecao): string
    {
        $partes = [
            (string) config('arquivos.pasta_sistema'),
            'redes',
            (string) $this->getAttribute('rede_id'),
        ];

        if (($this->arquivosPorEmpresa ?? false) && $this->getAttribute('empresa_id')) {
            $partes[] = 'empresas';
            $partes[] = (string) $this->getAttribute('empresa_id');
        }

        $partes[] = $this->getTable();
        $partes[] = (string) $this->getKey();
        $partes[] = $colecao;

        return implode('/', $partes);
    }

    public function getImagemThumbUrlAttribute(): ?string
    {
        $principal = $this->relationLoaded('arquivoPrincipal')
            ? $this->getRelation('arquivoPrincipal')
            : $this->arquivoPrincipal;

        return $principal?->thumb_url;
    }

    public function getImagemUrlAttribute(): ?string
    {
        $principal = $this->relationLoaded('arquivoPrincipal')
            ? $this->getRelation('arquivoPrincipal')
            : $this->arquivoPrincipal;

        return $principal?->url;
    }
}
