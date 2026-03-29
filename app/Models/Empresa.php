<?php

namespace App\Models;

use App\Traits\PertenceARede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use PertenceARede, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'rede_id',
        'nome',
        'documento',
        'telefone',
        'email',
    ];

    public function usuarios(): HasMany
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

    public function profissionais(): HasMany
    {
        return $this->hasMany(Profissional::class, 'empresa_id');
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
