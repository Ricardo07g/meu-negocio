<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nome
 * @property float $preco_mensal
 * @property string|null $descricao
 * @property int $max_empresas
 * @property int $max_usuarios
 * @property bool $tem_estoque
 * @property bool $tem_financeiro
 */
class Plano extends Model
{
    protected $table = 'planos';

    protected $fillable = [
        'nome',
        'preco_mensal',
        'descricao',
        'max_empresas',
        'max_usuarios',
        'tem_estoque',
        'tem_financeiro',
    ];

    protected function casts(): array
    {
        return [
            'preco_mensal' => 'decimal:2',
            'tem_estoque' => 'boolean',
            'tem_financeiro' => 'boolean',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function redes(): HasMany
    {
        return $this->hasMany(Rede::class, 'plano_id');
    }
}
