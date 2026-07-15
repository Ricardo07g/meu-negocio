<?php

declare(strict_types=1);

namespace App\Modules\FormaPagamento\Models;

use App\Enums\TipoFormaPagamento;
use App\Models\BaseModel;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\{Builder, Collection, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Forma de pagamento configuravel por empresa (transacional, empresa-level).
 * Cada unidade cria suas formas nomeadas (ex.: "Credito Cielo") a partir de um
 * TipoFormaPagamento, com taxa e prazo de liquidacao proprios — maquinas e
 * taxas variam por empresa.
 *
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property string $nome
 * @property TipoFormaPagamento $tipo
 * @property bool $ativo
 * @property bool $gera_recebivel
 * @property int $dias_liquidacao
 * @property string $taxa_percentual
 * @property bool $permite_parcelas
 * @property int|null $max_parcelas
 * @property bool $antecipacao_automatica
 * @property string $taxa_antecipacao_mensal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, FormaPagamentoTaxa> $taxas
 */
class FormaPagamento extends BaseModel
{
    use EmpresaTrait;
    use SoftDeletes;

    protected $table = 'formas_pagamento';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'nome',
        'tipo',
        'ativo',
        'gera_recebivel',
        'dias_liquidacao',
        'taxa_percentual',
        'permite_parcelas',
        'max_parcelas',
        'antecipacao_automatica',
        'taxa_antecipacao_mensal',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoFormaPagamento::class,
            'ativo' => 'boolean',
            'gera_recebivel' => 'boolean',
            'dias_liquidacao' => 'integer',
            'taxa_percentual' => 'decimal:2',
            'permite_parcelas' => 'boolean',
            'max_parcelas' => 'integer',
            'antecipacao_automatica' => 'boolean',
            'taxa_antecipacao_mensal' => 'decimal:2',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function taxas(): HasMany
    {
        return $this->hasMany(FormaPagamentoTaxa::class, 'forma_pagamento_id');
    }

    // ███████╗ ██████╗ ██████╗ ██████╗ ███████╗███████╗
    // ██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝██╔════╝
    // ███████╗██║     ██║   ██║██████╔╝█████╗  ███████╗
    // ╚════██║██║     ██║   ██║██╔═══╝ ██╔══╝  ╚════██║
    // ███████║╚██████╗╚██████╔╝██║     ███████╗███████║
    // ╚══════╝ ╚═════╝ ╚═════╝ ╚═╝     ╚══════╝╚══════╝

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    // ███╗   ██╗███████╗ ██████╗  ██████╗  ██████╗██╗ ██████╗
    // ████╗  ██║██╔════╝██╔════╝ ██╔═══██╗██╔════╝██║██╔═══██╗
    // ██╔██╗ ██║█████╗  ██║  ███╗██║   ██║██║     ██║██║   ██║
    // ██║╚██╗██║██╔══╝  ██║   ██║██║   ██║██║     ██║██║   ██║
    // ██║ ╚████║███████╗╚██████╔╝╚██████╔╝╚██████╗██║╚██████╔╝
    // ╚═╝  ╚═══╝╚══════╝ ╚═════╝  ╚═════╝  ╚═════╝╚═╝ ╚═════╝

    /**
     * Taxa (%) aplicavel a um numero de parcelas: usa a faixa que cobre $parcelas,
     * caindo na taxa plana da forma quando nao ha faixa correspondente.
     */
    public function taxaParaParcelas(int $parcelas): float
    {
        foreach ($this->taxas as $faixa) {
            if ($parcelas >= $faixa->parcela_min && $parcelas <= $faixa->parcela_max) {
                return (float) $faixa->taxa_percentual;
            }
        }

        // Sem faixa correspondente: cai na taxa plana da forma.
        return (float) $this->taxa_percentual;
    }

    /**
     * Valor liquido apos a taxa do adquirente para um dado numero de parcelas.
     */
    public function valorLiquido(float $bruto, int $parcelas = 1): float
    {
        $taxa = $this->taxaParaParcelas($parcelas);

        return round($bruto * (1 - $taxa / 100), 2);
    }
}
