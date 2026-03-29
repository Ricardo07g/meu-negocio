<?php

namespace App\Modules\Agenda\Models;

use App\Enums\StatusAgendamento;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Venda\Models\VendaPacote;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Servico\Models\Profissional;
use App\Modules\Servico\Models\Servico;
use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use App\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agendamento extends Model
{
    use PertenceARede, PertenceAEmpresa, RegistraAtividade, SoftDeletes;

    protected $table = 'agendamentos';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'cliente_id',
        'servico_id',
        'profissional_id',
        'venda_pacote_id',
        'inicio',
        'fim',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'status' => StatusAgendamento::class,
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

    public function vendaPacote(): BelongsTo
    {
        return $this->belongsTo(VendaPacote::class, 'venda_pacote_id');
    }

    public function pagamento(): HasOne
    {
        return $this->hasOne(Pagamento::class, 'agendamento_id');
    }
}
