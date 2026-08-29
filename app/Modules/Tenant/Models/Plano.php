<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Licenca de UMA empresa. O plano nao concede empresas: cada empresa contrata a sua.
 *
 * @property int $id
 * @property string $slug
 * @property string $nome
 * @property float $preco_por_licenca
 * @property string|null $descricao
 * @property int $max_usuarios
 * @property bool $tem_estoque
 * @property bool $tem_financeiro
 * @property int $limite_tokens_ia_dia
 */
class Plano extends Model
{
    /** Slug do plano gratuito — limitado a uma empresa por rede. */
    public const GRATIS = 'gratis';

    /** Slug do plano pago — e o plano do trial e das unidades contratadas. */
    public const PRO = 'pro';

    protected $table = 'planos';

    protected $fillable = [
        'slug',
        'nome',
        'preco_por_licenca',
        'descricao',
        'max_usuarios',
        'tem_estoque',
        'tem_financeiro',
        'limite_tokens_ia_dia',
    ];

    protected function casts(): array
    {
        return [
            'preco_por_licenca' => 'decimal:2',
            'tem_estoque' => 'boolean',
            'tem_financeiro' => 'boolean',
            'limite_tokens_ia_dia' => 'integer',
        ];
    }

    /**
     * A licenca da uma analise por IA?
     *
     * Deriva da cota em vez de uma coluna `tem_ia` ao lado: duas colunas dizendo a mesma
     * coisa e uma delas ficando desatualizada. `0` ja e inequivoco — e mantem a convencao
     * do projeto de que todo limite e finito.
     */
    public function temIa(): bool
    {
        return $this->limite_tokens_ia_dia > 0;
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    /** Empresas licenciadas neste plano (uma licenca por empresa). */
    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class, 'plano_id');
    }
}
