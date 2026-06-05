<?php

namespace App\Modules\Usuario\Models;

use App\Modules\Auth\Mail\RecuperacaoSenhaMailable;
use App\Modules\Tenant\Models\Empresa;
use App\Traits\RedeTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int $rede_id
 * @property int|null $empresa_id
 * @property string $nome
 * @property string $email
 * @property string $password
 * @property bool $ativo
 * @property bool $atende
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Empresa> $empresas
 * @property-read Empresa|null $empresa
 */
class Usuario extends Authenticatable
{
    use HasRoles, Notifiable, RedeTrait, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'nome',
        'email',
        'password',
        'ativo',
        'atende',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
            'atende' => 'boolean',
        ];
    }

    /**
     * Envia o email de recuperacao de senha usando a Mailable customizada
     * (Markdown + branding "Meu Negocio") ao inves da notification default
     * do Laravel.
     */
    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->send(new RecuperacaoSenhaMailable($token, $this->email));
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    /**
     * Empresas que este usuario pode acessar (N:N via pivot empresa_usuario).
     *
     * Para Admin, esta relacao normalmente nao e usada para autorizacao
     * (Admin acessa tudo via EmpresaTrait + hasRole('Admin')); para nao-admin
     * e a fonte de verdade do conjunto de empresas acessiveis.
     */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_usuario')->withTimestamps();
    }

    /**
     * Empresa default do usuario (preferencia ao logar). Mantida por compat —
     * o universo real de acesso vem do pivot `empresas()`. Esta relacao continua
     * existindo para forms e dashboards que mostram a empresa "principal".
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    // ███████╗ ██████╗ ██████╗ ██████╗ ███████╗███████╗
    // ██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔════╝██╔════╝
    // ███████╗██║     ██║   ██║██████╔╝█████╗  ███████╗
    // ╚════██║██║     ██║   ██║██╔═══╝ ██╔══╝  ╚════██║
    // ███████║╚██████╗╚██████╔╝██║     ███████╗███████║
    // ╚══════╝ ╚═════╝ ╚═════╝ ╚═╝     ╚══════╝╚══════╝

    /**
     * Atendentes que podem operar numa empresa especifica.
     *
     * Inclui: usuarios com `atende=true` cuja pivot `empresa_usuario` contem
     * a empresa OU usuarios com Role 'Admin' (acesso a toda a rede).
     */
    public function scopeAtendentesDaEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('atende', true)
            ->where(function (Builder $q) use ($empresaId) {
                $q->whereHas('empresas', fn (Builder $qq) => $qq->where('empresas.id', $empresaId))
                    ->orWhereHas('roles', fn (Builder $qq) => $qq->where('name', 'Admin'));
            });
    }

    // ███╗   ███╗███████╗████████╗██╗  ██╗ ██████╗ ██████╗ ███████╗
    // ████╗ ████║██╔════╝╚══██╔══╝██║  ██║██╔═══██╗██╔══██╗██╔════╝
    // ██╔████╔██║█████╗     ██║   ███████║██║   ██║██║  ██║███████╗
    // ██║╚██╔╝██║██╔══╝     ██║   ██╔══██║██║   ██║██║  ██║╚════██║
    // ██║ ╚═╝ ██║███████╗   ██║   ██║  ██║╚██████╔╝██████╔╝███████║
    // ╚═╝     ╚═╝╚══════╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚═════╝ ╚══════╝

    /**
     * Verifica se este usuario pode acessar uma determinada empresa.
     *
     * Admin acessa tudo dentro da rede; demais usuarios so acessam empresas
     * presentes na pivot empresa_usuario. Usado em Policies para autorizar
     * operacoes sobre entidades transacionais (Agendamento, Venda, Caixa,
     * Pagamento, Despesa).
     */
    public function podeAcessarEmpresa(?int $empresaId): bool
    {
        if ($empresaId === null) {
            return false;
        }

        if ($this->hasRole('Admin')) {
            return true;
        }

        return $this->empresas()->where('empresas.id', $empresaId)->exists();
    }
}
