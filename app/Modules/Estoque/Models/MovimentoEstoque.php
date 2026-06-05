<?php

declare(strict_types=1);

namespace App\Modules\Estoque\Models;

use App\Enums\TipoMovimentoEstoque;
use App\Models\BaseModel;
use App\Modules\Produto\Models\Produto;
use App\Traits\{EmpresaTrait, RegistraAtividade};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property int $empresa_id
 * @property int $produto_id
 * @property TipoMovimentoEstoque $tipo
 * @property int $quantidade
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Produto $produto
 */
class MovimentoEstoque extends BaseModel
{
    use EmpresaTrait, RegistraAtividade;

    protected $table = 'movimentos_estoque';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'produto_id',
        'tipo',
        'quantidade',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentoEstoque::class,
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
