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
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use Illuminate\Support\Carbon;

/**
 * Uma empresa e uma licenca contratada individualmente: o plano mora aqui, nao na rede.
 *
 * @property int $id
 * @property int $rede_id
 * @property int $plano_id
 * @property Carbon|null $trial_expira_em
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
 * @property-read Plano $plano
 * @property-read Rede $rede
 */
class Empresa extends BaseModel
{
    use SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'rede_id',
        'plano_id',
        'trial_expira_em',
        'nome',
        'documento',
        'telefone',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'trial_expira_em' => 'date',
        ];
    }

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    /** Licenca contratada para esta unidade. */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

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

    // ██╗     ██╗ ██████╗███████╗███╗   ██╗ ██████╗ █████╗
    // ██║     ██║██╔════╝██╔════╝████╗  ██║██╔════╝██╔══██╗
    // ██║     ██║██║     █████╗  ██╔██╗ ██║██║     ███████║
    // ██║     ██║██║     ██╔══╝  ██║╚██╗██║██║     ██╔══██║
    // ███████╗██║╚██████╗███████╗██║ ╚████║╚██████╗██║  ██║
    // ╚══════╝╚═╝ ╚═════╝╚══════╝╚═╝  ╚═══╝ ╚═════╝╚═╝  ╚═╝

    /** Duracao do teste gratuito, em dias, da unidade criada no registro. */
    public const DIAS_DE_TRIAL = 14;

    /** Duracao de cada renovacao do teste pedida pelo proprio Admin na tela de assinatura. */
    public const DIAS_DE_RENOVACAO_TRIAL = 15;

    /** Esta unidade esta em periodo de teste (no Pro, sem cobranca)? */
    public function emTrial(): bool
    {
        return $this->trial_expira_em !== null
            && $this->trial_expira_em->endOfDay()->isFuture();
    }

    /**
     * Ja teve teste e ele acabou.
     *
     * A data sobrevive ao rebaixamento justamente para responder isto: `trial_expira_em`
     * nulo significa "nunca teve teste", nao "o teste acabou".
     */
    public function trialVencido(): bool
    {
        return $this->trial_expira_em !== null && ! $this->emTrial();
    }

    /**
     * O Admin pode reabrir o teste desta unidade?
     *
     * Enquanto nao ha gateway de pagamento, o teste vencido e renovavel em vez de ser um
     * beco sem saida (ADR-0013). So vale para quem caiu no Gratis: numa licenca paga
     * "renovar o teste" seria deixar de cobrar quem ja contratou.
     */
    public function podeRenovarTrial(): bool
    {
        return $this->trialVencido() && $this->plano->slug === Plano::GRATIS;
    }

    /** Dias que faltam para o teste acabar (0 quando nao ha trial vigente). */
    public function diasRestantesTrial(): int
    {
        if (! $this->emTrial()) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($this->trial_expira_em->startOfDay(), absolute: true);
    }

    /**
     * Assentos ocupados nesta licenca.
     *
     * Une o pivot `empresa_usuario` (fonte de verdade de acesso) com os usuarios que
     * tem esta empresa como default — o Admin criado no registro entra so pelo segundo
     * caminho, e contar apenas o pivot o deixaria de fora do limite do plano.
     */
    public function contarUsuarios(): int
    {
        return $this->usuarios()->pluck('usuarios.id')
            ->merge($this->usuariosDefault()->pluck('id'))
            ->unique()
            ->count();
    }
}
