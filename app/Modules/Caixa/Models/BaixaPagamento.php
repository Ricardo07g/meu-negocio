<?php

declare(strict_types=1);

namespace App\Modules\Caixa\Models;

use App\Models\BaseModel;
use App\Modules\Conta\Models\Conta;
use App\Modules\FormaPagamento\Models\FormaPagamento;
use App\Modules\Pagamento\Models\ParcelaPagamento;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $parcela_pagamento_id
 * @property int|null $caixa_id
 * @property int|null $conta_id
 * @property float $valor
 * @property float $multa
 * @property float $juros
 * @property float $desconto
 * @property int|null $forma_pagamento_id
 * @property string|null $forma_pagamento_nome
 * @property int|null $parcelas_cartao
 * @property Carbon $data
 * @property Carbon|null $estornado_em
 * @property string|null $observacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ParcelaPagamento $parcela
 * @property-read FormaPagamento|null $formaPagamento
 * @property-read Caixa|null $caixa
 * @property-read Conta|null $conta
 * @property-read Collection<int, Recebivel> $recebiveis
 */
class BaixaPagamento extends BaseModel
{
    use EmpresaTrait;

    protected $table = 'baixas_pagamento';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'parcela_pagamento_id',
        'caixa_id',
        'conta_id',
        'valor',
        'multa',
        'juros',
        'desconto',
        'forma_pagamento_id',
        'forma_pagamento_nome',
        'parcelas_cartao',
        'data',
        'estornado_em',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'multa' => 'decimal:2',
            'juros' => 'decimal:2',
            'desconto' => 'decimal:2',
            'parcelas_cartao' => 'integer',
            'data' => 'datetime',
            'estornado_em' => 'datetime',
        ];
    }

    /** Valor líquido que entra no caixa: principal + multa + juros - desconto. */
    public function valorTotal(): float
    {
        return (float) ($this->valor + $this->multa + $this->juros - $this->desconto);
    }

    /**
     * Forma do recebimento com o parcelamento do cartão, quando houver:
     * "Cartão de Crédito 2x" / "Dinheiro". Fonte única do rótulo — as views
     * (venda, caixa do dia, recebimentos por período, recibo) usam este método
     * em vez de repetir a concatenação.
     */
    public function rotuloForma(): string
    {
        $nome = $this->forma_pagamento_nome ?? '—';

        return $this->parcelas_cartao > 1
            ? "{$nome} {$this->parcelas_cartao}x"
            : $nome;
    }

    // ███████╗ ██████╗ ██████╗ ██████╗ ███████╗███████╗
    // ██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝██╔════╝
    // ███████╗██║     ██║   ██║██████╔╝█████╗  ███████╗
    // ╚════██║██║     ██║   ██║██╔═══╝ ██╔══╝  ╚════██║
    // ███████║╚██████╗╚██████╔╝██║     ███████╗███████║
    // ╚══════╝ ╚═════╝ ╚═════╝ ╚═╝     ╚══════╝╚══════╝

    /**
     * Eager loading da origem do recebimento (parcela -> titulo -> cliente).
     *
     * O `withTrashed()` na espinha NAO e opcional: `Pagamento` e `ParcelaPagamento`
     * usam SoftDeletes e a Baixa NAO. Cancelar uma venda apaga o titulo e deixa a
     * baixa orfa da relacao — a linha aparece sem cliente ("—") mesmo com o
     * recebimento tendo existido. Quem lista baixas deve usar este scope em vez de
     * repetir (e esquecer) o `withTrashed`.
     */
    public function scopeComOrigem(Builder $query): Builder
    {
        return $query->with(self::relacoesDaOrigem());
    }

    /**
     * A espinha crua, para quem precisa acrescentar relacoes MAIS FUNDAS a partir
     * dela (ex.: a timeline do caixa, que desce ate o servico da origem).
     *
     * Tem de ir tudo num UNICO `with()`, com estas chaves primeiro: um segundo
     * `with(['parcela.pagamento.x'])` faz o Laravel registrar `parcela` e
     * `parcela.pagamento` como no-op e o array_merge sobrescreve os closures acima
     * — o `withTrashed` se perde em silencio e a linha volta a quebrar no titulo
     * apagado. Por isso `array_merge(relacoesDaOrigem(), [...])`, nunca
     * `comOrigem()` encadeado com outro `with()`.
     *
     * @return array<array-key, \Closure|string>
     */
    public static function relacoesDaOrigem(): array
    {
        return [
            'parcela' => fn ($q) => $q->withTrashed(),
            'parcela.pagamento' => fn ($q) => $q->withTrashed(),
            'parcela.pagamento.cliente',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(ParcelaPagamento::class, 'parcela_pagamento_id');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class, 'caixa_id');
    }

    /** Conta de destino do recebimento (gaveta p/ dinheiro; banco/carteira p/ cartão/pix). */
    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class, 'conta_id');
    }

    /** Recebíveis gerados por esta baixa (quando a forma é diferida: cartão/pix-maquineta). */
    public function recebiveis(): HasMany
    {
        return $this->hasMany(Recebivel::class, 'baixa_pagamento_id');
    }
}
