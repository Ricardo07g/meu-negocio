<?php

namespace App\Modules\Cliente\Models;

use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Venda\Models\VendaPacote;
use App\Modules\Agenda\Models\Agendamento;
use App\Traits\PertenceARede;
use App\Traits\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use PertenceARede, PertenceAEmpresa, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'rede_id',
        'empresa_id',
        'nome',
        'telefone',
        'telefone_whatsapp',
        'email',
        'data_nascimento',
        'cpf',
        'sexo',
        'cep',
        'estado',
        'cidade',
        'bairro',
        'logradouro',
        'numero',
        'complemento',
        'observacoes',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'telefone_whatsapp' => 'boolean',
    ];

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'cliente_id');
    }

    public function vendasPacote(): HasMany
    {
        return $this->hasMany(VendaPacote::class, 'cliente_id');
    }

    public function pagamentos(): HasManyThrough
    {
        return $this->hasManyThrough(Pagamento::class, Agendamento::class, 'cliente_id', 'agendamento_id');
    }
}
