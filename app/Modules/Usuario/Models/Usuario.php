<?php

declare(strict_types=1);

namespace App\Modules\Usuario\Models;

use App\Modules\Arquivo\Contracts\PossuiArquivos;
use App\Modules\Auth\Mail\{RecuperacaoSenhaMailable, VerificacaoEmailMailable};
use App\Modules\Tenant\Models\{Empresa, Rede};
use App\Traits\{RedeTrait, TemArquivos};
use Illuminate\Auth\MustVerifyEmail as VerificaEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\{Builder, Collection, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Log, Mail, URL};
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int $rede_id
 * @property int|null $empresa_id
 * @property string $nome
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $two_factor_secret
 * @property array<int, string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_ultimo_passo
 * @property string $password
 * @property bool $ativo
 * @property bool $atende
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Empresa> $empresas
 * @property-read Empresa|null $empresa
 * @property-read Rede|null $rede
 */
class Usuario extends Authenticatable implements MustVerifyEmail, PossuiArquivos
{
    use HasRoles, Notifiable, RedeTrait, SoftDeletes, TemArquivos, VerificaEmail;

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
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_ultimo_passo' => 'integer',
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

    /**
     * Mesmo motivo do reset: Mailable com branding em vez da notification default.
     * Sincrono — em producao a fila e `sync` de qualquer forma (ADR-0016).
     */
    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute(
            'verificacao.verificar',
            now()->addMinutes(60),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        Mail::to($this->email)->send(new VerificacaoEmailMailable($url, $this->nome));
    }

    /**
     * Troca de e-mail derruba a verificacao e reenvia o link: o endereco novo
     * nunca foi provado. Chamado depois do save (Perfil e edicao pelo Admin).
     */
    public function revalidarEmailSeAlterado(): void
    {
        if (! $this->wasChanged('email')) {
            return;
        }

        $this->forceFill(['email_verified_at' => null])->save();
        $this->enviarVerificacaoSemFalhar();
    }

    /**
     * Envia o link sem deixar uma falha do provedor derrubar o fluxo que o chamou
     * (registro, criacao de usuario). O usuario reenvia pela tela de confirmacao.
     */
    public function enviarVerificacaoSemFalhar(): void
    {
        try {
            $this->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::error('Falha ao enviar o link de verificacao de e-mail', [
                'usuario_id' => $this->getKey(),
                'exception' => $e::class,
                'mensagem' => $e->getMessage(),
            ]);
        }
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
    /** 2FA so vale depois de confirmado com um codigo do app (ADR-0022). */
    public function temDoisFatoresAtivo(): bool
    {
        // Atributo cru: so conferir presenca, sem descriptografar o segredo a cada linha da listagem.
        return ($this->attributes['two_factor_secret'] ?? null) !== null && $this->two_factor_confirmed_at !== null;
    }

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

    /**
     * @return array<string, array<string, mixed>>
     */
    public function colecoesArquivo(): array
    {
        return [
            'avatar' => [
                'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
                'max_kb' => 2048,
                'unica' => true,
                'thumb' => true,
            ],
        ];
    }
}
