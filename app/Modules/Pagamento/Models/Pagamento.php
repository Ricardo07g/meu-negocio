<?php

namespace App\Modules\Pagamento\Models;

use App\Enums\FormaPagamento;
use App\Enums\StatusPagamento;
use App\Models\BaseModel;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Venda\Models\VendaPacote;
use App\Modules\Venda\Models\VendaProduto;
use App\Traits\EmpresaTrait;
use App\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pagamento extends BaseModel
{
    use EmpresaTrait, RegistraAtividade, SoftDeletes;

    protected $table = 'pagamentos';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'cliente_id',
        'agendamento_id',
        'venda_pacote_id',
        'venda_produto_id',
        'valor',
        'valor_pago',
        'forma_pagamento',
        'status',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'forma_pagamento' => FormaPagamento::class,
            'status' => StatusPagamento::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }

    public function vendaPacote(): BelongsTo
    {
        return $this->belongsTo(VendaPacote::class, 'venda_pacote_id');
    }

    public function vendaProduto(): BelongsTo
    {
        return $this->belongsTo(VendaProduto::class, 'venda_produto_id');
    }

    public function baixas(): HasMany
    {
        return $this->hasMany(BaixaPagamento::class, 'pagamento_id');
    }

    // █████╗  ██████╗███████╗███████╗███████╗ ██████╗ ██████╗ ███████╗
    // ██╔══██╗██╔════╝██╔════╝██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝
    // ███████║██║     █████╗  ███████╗███████╗██║   ██║██████╔╝███████╗
    // ██╔══██║██║     ██╔══╝  ╚════██║╚════██║██║   ██║██╔══██╗╚════██║
    // ██║  ██║╚██████╗███████╗███████║███████║╚██████╔╝██║  ██║███████║
    // ╚═╝  ╚═╝ ╚═════╝╚══════╝╚══════╝╚══════╝ ╚═════╝ ╚═╝  ╚═╝╚══════╝

    // ███╗   ███╗██╗   ██╗████████╗ █████╗ ████████╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██║   ██║╚══██╔══╝██╔══██╗╚══██╔══╝██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║██║   ██║   ██║   ███████║   ██║   ██║   ██║██████╔╝███████╗
    // ██║╚██╔╝██║██║   ██║   ██║   ██╔══██║   ██║   ██║   ██║██╔══██╗╚════██║
    // ██║ ╚═╝ ██║╚██████╔╝   ██║   ██║  ██║   ██║   ╚██████╔╝██║  ██║███████║
    // ╚═╝     ╚═╝ ╚═════╝    ╚═╝   ╚═╝  ╚═╝   ╚═╝    ╚═════╝ ╚═╝  ╚═╝╚══════╝

    // ███████╗ ██████╗ ██████╗ ██████╗ ███████╗███████╗
    // ██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝██╔════╝
    // ███████╗██║     ██║   ██║██████╔╝█████╗  ███████╗
    // ╚════██║██║     ██║   ██║██╔═══╝ ██╔══╝  ╚════██║
    // ███████║╚██████╗╚██████╔╝██║     ███████╗███████║
    // ╚══════╝ ╚═════╝ ╚═════╝ ╚═╝     ╚══════╝╚══════╝

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    public function saldoRestante(): float
    {
        return (float) ($this->valor - $this->valor_pago);
    }
}
