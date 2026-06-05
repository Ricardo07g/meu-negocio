<?php

namespace App\Modules\Servico\Models;

use App\Enums\TipoServico;
use App\Models\BaseModel;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Venda\Models\VendaEtapas;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property string $nome
 * @property int $duracao
 * @property float $valor
 * @property TipoServico $tipo
 * @property int|null $qtd_etapas
 * @property string|null $descricao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Agendamento> $agendamentos
 * @property-read Collection<int, VendaEtapas> $vendasEtapas
 */
class Servico extends BaseModel
{
    use SoftDeletes;

    protected $table = 'servicos';

    protected $fillable = [
        'rede_id',
        'nome',
        'duracao',
        'valor',
        'tipo',
        'qtd_etapas',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'tipo' => TipoServico::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'servico_id');
    }

    public function vendasEtapas(): HasMany
    {
        return $this->hasMany(VendaEtapas::class, 'servico_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    public function isEtapas(): bool
    {
        return $this->tipo === TipoServico::Etapas;
    }

    public function isUnico(): bool
    {
        return $this->tipo === TipoServico::Unico;
    }
}
