<?php

namespace App\Modules\Cliente\Models;

use App\Models\BaseModel;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Venda\Models\VendaEtapas;
use App\Modules\Venda\Models\VendaProduto;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends BaseModel
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'rede_id',
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

    // ██████╗ ███████╗██╗      █████╗ ████████╗██╗ ██████╗ ███╗   ██╗███████╗
    // ██╔══██╗██╔════╝██║     ██╔══██╗╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
    // ██████╔╝█████╗  ██║     ███████║   ██║   ██║██║   ██║██╔██╗ ██║███████╗
    // ██╔══██╗██╔══╝  ██║     ██╔══██║   ██║   ██║██║   ██║██║╚██╗██║╚════██║
    // ██║  ██║███████╗███████╗██║  ██║   ██║   ██║╚██████╔╝██║ ╚████║███████║
    // ╚═╝  ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'cliente_id');
    }

    public function vendasEtapas(): HasMany
    {
        return $this->hasMany(VendaEtapas::class, 'cliente_id');
    }

    public function vendasProduto(): HasMany
    {
        return $this->hasMany(VendaProduto::class, 'cliente_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class, 'cliente_id');
    }
}
