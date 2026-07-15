<?php

declare(strict_types=1);

namespace App\Modules\Conta\Models;

use App\Enums\{TipoConta, TipoLancamento};
use App\Models\BaseModel;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\{Builder, Collection, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Conta financeira (onde o dinheiro da empresa fica): a gaveta do caixa, contas
 * bancarias e carteiras digitais. Transacional (empresa-level). O saldo e o
 * razao de lancamentos (credito/debito). Ver ADR-0010 e TipoConta/TipoLancamento.
 *
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property string $nome
 * @property TipoConta $tipo
 * @property string $saldo_inicial
 * @property bool $ativo
 * @property bool $eh_caixa_padrao
 * @property bool $eh_destino_recebivel_padrao
 * @property string|null $instituicao
 * @property string|null $agencia
 * @property string|null $numero
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Lancamento> $lancamentos
 */
class Conta extends BaseModel
{
    use EmpresaTrait;
    use SoftDeletes;

    protected $table = 'contas';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'nome',
        'tipo',
        'saldo_inicial',
        'ativo',
        'eh_caixa_padrao',
        'eh_destino_recebivel_padrao',
        'instituicao',
        'agencia',
        'numero',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoConta::class,
            'saldo_inicial' => 'decimal:2',
            'ativo' => 'boolean',
            'eh_caixa_padrao' => 'boolean',
            'eh_destino_recebivel_padrao' => 'boolean',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class, 'conta_id');
    }

    // ███████╗ ██████╗ ██████╗ ██████╗ ███████╗███████╗
    // ██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝██╔════╝
    // ███████╗██║     ██║   ██║██████╔╝█████╗  ███████╗
    // ╚════██║██║     ██║   ██║██╔═══╝ ██╔══╝  ╚════██║
    // ███████║╚██████╗╚██████╔╝██║     ███████╗███████║
    // ╚══════╝ ╚═════╝ ╚═════╝ ╚═╝     ╚══════╝╚══════╝

    public function scopeAtivas(Builder $query): Builder
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
     * Saldo realizado da conta = saldo_inicial + creditos − debitos.
     * (Os recebiveis de cartao "a caminho" entram por data na fase B.2.)
     */
    public function saldo(): float
    {
        $creditos = (float) $this->lancamentos()->where('tipo', TipoLancamento::Credito->value)->sum('valor');
        $debitos = (float) $this->lancamentos()->where('tipo', TipoLancamento::Debito->value)->sum('valor');

        return round((float) $this->saldo_inicial + $creditos - $debitos, 2);
    }
}
