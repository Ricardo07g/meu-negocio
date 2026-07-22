<?php

declare(strict_types=1);

namespace App\Modules\Conta\Models;

use App\Enums\{TipoConta, TipoLancamento};
use App\Models\BaseModel;
use App\Modules\Caixa\Models\{Caixa, Recebivel};
use App\Modules\FormaPagamento\Models\FormaPagamento;
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
 * @property-read Collection<int, Recebivel> $recebiveis
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

    /** Recebiveis (cartao/pix-maquineta) que caem nesta conta; entram no saldo pela data prevista. */
    public function recebiveis(): HasMany
    {
        return $this->hasMany(Recebivel::class, 'conta_id');
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
     * Saldo da conta = saldo_inicial + creditos − debitos (regime "fluxo, nao
     * saldo", ADR-0011). So a conta Caixa (gaveta) acumula lançamentos — e o unico
     * saldo "de verdade", reconciliado na contagem fisica. Contas banco/carteira
     * viram rotulos de origem (cartao/pix caem so como Baixa, sem lançamento):
     * o saldo delas fica no saldo_inicial, sem controle vivo.
     */
    public function saldo(): float
    {
        $creditos = (float) $this->lancamentos()->where('tipo', TipoLancamento::Credito->value)->sum('valor');
        $debitos = (float) $this->lancamentos()->where('tipo', TipoLancamento::Debito->value)->sum('valor');

        return round((float) $this->saldo_inicial + $creditos - $debitos, 2);
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    /** Conta Caixa do sistema: 1 por empresa; nao muda de tipo, nao inativa nem exclui (so renomeia). */
    public function ehProtegida(): bool
    {
        return $this->tipo === TipoConta::Caixa;
    }

    /** Ja teve dinheiro passando (lancamentos ou recebiveis) — impede exclusao (resta inativar). */
    public function temMovimentacoes(): bool
    {
        return $this->lancamentos()->withoutGlobalScope('empresa')->exists()
            || $this->recebiveis()->withoutGlobalScope('empresa')->exists();
    }

    /** Referenciada como destino de alguma forma OU conta de algum caixa (empresa-explicito). */
    public function estaEmUso(): bool
    {
        return FormaPagamento::withoutGlobalScope('empresa')
            ->where('empresa_id', $this->empresa_id)
            ->where('conta_destino_id', $this->id)
            ->exists()
            || Caixa::withoutGlobalScope('empresa')
                ->where('empresa_id', $this->empresa_id)
                ->where('conta_id', $this->id)
                ->exists();
    }

    /** Alguma forma ATIVA ainda aponta para esta conta (impede inativar sem trocar o destino). */
    public function temFormaAtivaVinculada(): bool
    {
        return FormaPagamento::withoutGlobalScope('empresa')
            ->where('empresa_id', $this->empresa_id)
            ->where('conta_destino_id', $this->id)
            ->where('ativo', true)
            ->exists();
    }

    /** So se pode excluir a conta que nao e do sistema, sem movimentacoes e sem vinculo. */
    public function podeExcluir(): bool
    {
        return ! $this->ehProtegida() && ! $this->temMovimentacoes() && ! $this->estaEmUso();
    }
}
