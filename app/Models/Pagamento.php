<?php

namespace App\Models;

use App\Enums\FormaPagamento;
use App\Enums\StatusPagamento;
use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use App\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pagamento extends Model
{
    use PertenceARede, PertenceAEmpresa, RegistraAtividade, SoftDeletes;

    protected $table = 'pagamentos';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'agendamento_id',
        'venda_pacote_id',
        'venda_produto_id',
        'valor',
        'forma_pagamento',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'forma_pagamento' => FormaPagamento::class,
            'status' => StatusPagamento::class,
        ];
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
}
