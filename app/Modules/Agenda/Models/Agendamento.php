<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Models;

use App\Enums\{MotivoSemCobranca, SituacaoFinanceiraAgendamento, StatusAgendamento, StatusPagamento};
use App\Models\BaseModel;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Servico\Models\Servico;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Venda\Models\VendaEtapas;
use App\Traits\{EmpresaTrait, RegistraAtividade};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $cliente_id
 * @property int $servico_id
 * @property int $atendente_id
 * @property int|null $venda_etapas_id
 * @property Carbon $inicio
 * @property Carbon $fim
 * @property StatusAgendamento $status
 * @property string|null $observacoes
 * @property MotivoSemCobranca|null $motivo_sem_cobranca
 * @property bool $fora_expediente
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Cliente $cliente
 * @property-read Servico $servico
 * @property-read Usuario $atendente
 * @property-read VendaEtapas|null $vendaEtapas
 * @property-read Pagamento|null $pagamento
 */
class Agendamento extends BaseModel
{
    use EmpresaTrait, RegistraAtividade, SoftDeletes;

    protected $table = 'agendamentos';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'cliente_id',
        'servico_id',
        'atendente_id',
        'venda_etapas_id',
        'inicio',
        'fim',
        'status',
        'observacoes',
        'motivo_sem_cobranca',
        'fora_expediente',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'status' => StatusAgendamento::class,
            'motivo_sem_cobranca' => MotivoSemCobranca::class,
            'fora_expediente' => 'boolean',
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

    public function vendaEtapas(): BelongsTo
    {
        return $this->belongsTo(VendaEtapas::class, 'venda_etapas_id');
    }

    public function pagamento(): HasOne
    {
        return $this->hasOne(Pagamento::class, 'agendamento_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    /** Este atendimento ja tem titulo financeiro (veio de venda ou foi cobrado na finalizacao)? */
    public function foiCobrado(): bool
    {
        return $this->pagamento()->exists();
    }

    /**
     * Situacao financeira derivada do titulo — nunca persistida.
     *
     * A ausencia de titulo tem duas leituras diferentes e a distincao importa na
     * recepcao: enquanto o atendimento esta em aberto, e "a cobrar"; depois de
     * finalizado com motivo declarado, e uma decisao ("sem cobranca").
     */
    public function situacaoFinanceira(): SituacaoFinanceiraAgendamento
    {
        $pagamento = $this->loadMissing('pagamento')->pagamento;

        if (! $pagamento) {
            return $this->status === StatusAgendamento::Finalizado
                ? SituacaoFinanceiraAgendamento::SemCobranca
                : SituacaoFinanceiraAgendamento::ACobrar;
        }

        return match ($pagamento->status) {
            StatusPagamento::Pago => SituacaoFinanceiraAgendamento::Pago,
            StatusPagamento::Estornado, StatusPagamento::Cancelado => SituacaoFinanceiraAgendamento::Estornado,
            default => SituacaoFinanceiraAgendamento::AReceber,
        };
    }
}
