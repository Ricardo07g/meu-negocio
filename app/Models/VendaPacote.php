<?php

namespace App\Models;

use App\Enums\StatusVendaPacote;
use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendaPacote extends Model
{
    use PertenceARede, PertenceAEmpresa, SoftDeletes;

    protected $table = 'vendas_pacote';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'cliente_id',
        'servico_id',
        'profissional_id',
        'valor_total',
        'qtd_sessoes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'status' => StatusVendaPacote::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'profissional_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'venda_pacote_id');
    }

    public function sessoesRealizadas(): int
    {
        return $this->agendamentos()
            ->where('status', 'finalizado')
            ->count();
    }

    public function sessoesPendentes(): int
    {
        return $this->agendamentos()
            ->whereIn('status', ['agendado', 'confirmado'])
            ->count();
    }
}
