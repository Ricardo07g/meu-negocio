<?php

declare(strict_types=1);

namespace App\Modules\Conta\Models;

use App\Enums\{FormatoExportacao, StatusExportacao};
use App\Models\BaseModel;
use App\Modules\Usuario\Models\Usuario;
use App\Traits\EmpresaTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pedido de exportacao do extrato de uma conta num periodo. Gerado de forma
 * assincrona por um job (fila), que grava a planilha (CSV/XLSX) no storage e
 * marca o status. Transacional (empresa-level). Ver ADR-0012.
 *
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $conta_id
 * @property int $usuario_id
 * @property FormatoExportacao $formato
 * @property Carbon $periodo_inicio
 * @property Carbon $periodo_fim
 * @property StatusExportacao $status
 * @property string|null $disco
 * @property string|null $caminho
 * @property string|null $nome_arquivo
 * @property int|null $tamanho
 * @property string|null $erro
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conta $conta
 * @property-read Usuario $usuario
 */
class Exportacao extends BaseModel
{
    use EmpresaTrait;

    /** Dias que o arquivo fica disponivel antes de ser removido pela rotina de limpeza. */
    public const DIAS_RETENCAO = 1;

    protected $table = 'exportacoes';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'conta_id',
        'usuario_id',
        'formato',
        'periodo_inicio',
        'periodo_fim',
        'status',
        'disco',
        'caminho',
        'nome_arquivo',
        'tamanho',
        'erro',
    ];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'date',
            'periodo_fim' => 'date',
            'formato' => FormatoExportacao::class,
            'status' => StatusExportacao::class,
            'tamanho' => 'integer',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function conta(): BelongsTo
    {
        return $this->belongsTo(Conta::class, 'conta_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    public function estaPronta(): bool
    {
        return $this->status === StatusExportacao::Pronto && $this->caminho !== null;
    }

    /** Momento em que o arquivo expira (a rotina horaria remove a partir daqui). */
    public function expiraEm(): Carbon
    {
        return ($this->created_at ?? now())->copy()->addDays(self::DIAS_RETENCAO);
    }

    /** Enquanto processa nao pode excluir (o job ainda pode estar escrevendo). */
    public function podeExcluir(): bool
    {
        return $this->status !== StatusExportacao::Processando;
    }
}
