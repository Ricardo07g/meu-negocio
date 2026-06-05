<?php

namespace App\Modules\Venda\Models;

use App\Enums\StatusVendaEtapas;
use App\Models\BaseModel;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Servico\Models\Servico;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\EmpresaTrait;
use App\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $cliente_id
 * @property int $servico_id
 * @property int $atendente_id
 * @property Carbon $data
 * @property float $valor_total
 * @property float $desconto
 * @property float $acrescimo
 * @property int $qtd_etapas
 * @property StatusVendaEtapas $status
 * @property string|null $observacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Cliente $cliente
 * @property-read Servico $servico
 * @property-read Usuario $atendente
 * @property-read Collection<int, Agendamento> $agendamentos
 * @property-read Pagamento|null $pagamento
 */
class VendaEtapas extends BaseModel
{
    use EmpresaTrait, RegistraAtividade, SoftDeletes;

    protected $table = 'vendas_etapas';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'cliente_id',
        'servico_id',
        'atendente_id',
        'data',
        'valor_total',
        'desconto',
        'acrescimo',
        'qtd_etapas',
        'status',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor_total' => 'decimal:2',
            'desconto' => 'decimal:2',
            'acrescimo' => 'decimal:2',
            'status' => StatusVendaEtapas::class,
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

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    public function atendente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'atendente_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'venda_etapas_id');
    }

    public function pagamento(): HasOne
    {
        return $this->hasOne(Pagamento::class, 'venda_etapas_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    public function etapasRealizadas(): int
    {
        return $this->agendamentos()
            ->where('status', 'finalizado')
            ->count();
    }

    public function etapasPendentes(): int
    {
        return $this->agendamentos()
            ->whereIn('status', ['agendado', 'confirmado'])
            ->count();
    }
}
