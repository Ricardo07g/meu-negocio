<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Models;

use App\Models\BaseModel;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\Produto;
use App\Modules\Servico\Models\Servico;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Database\Eloquent\{Collection, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property string $nome
 * @property string|null $documento
 * @property string|null $telefone
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Usuario> $usuarios
 * @property-read Collection<int, Usuario> $usuariosDefault
 * @property-read Collection<int, Cliente> $clientes
 * @property-read Collection<int, Servico> $servicos
 * @property-read Collection<int, Agendamento> $agendamentos
 * @property-read Collection<int, Pagamento> $pagamentos
 * @property-read Collection<int, Despesa> $despesas
 * @property-read Collection<int, Produto> $produtos
 */
class Empresa extends BaseModel
{
    use SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'rede_id',
        'nome',
        'documento',
        'telefone',
        'email',
    ];

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    /**
     * Usuarios com acesso a esta empresa via pivot empresa_usuario (N:N).
     * Fonte de verdade do conjunto de usuarios autorizados a operar a empresa.
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'empresa_usuario')->withTimestamps();
    }

    /**
     * Usuarios que tem esta empresa como default ao logar (usuarios.empresa_id).
     * Mantido para compatibilidade; nao e fonte de verdade de acesso.
     */
    public function usuariosDefault(): HasMany
    {
        return $this->hasMany(Usuario::class, 'empresa_id');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'empresa_id');
    }

    public function servicos(): HasMany
    {
        return $this->hasMany(Servico::class, 'empresa_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'empresa_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class, 'empresa_id');
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class, 'empresa_id');
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'empresa_id');
    }
}
