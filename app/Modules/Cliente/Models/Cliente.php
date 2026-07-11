<?php

declare(strict_types=1);

namespace App\Modules\Cliente\Models;

use App\Models\BaseModel;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Arquivo\Contracts\PossuiArquivos;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Venda\Models\{VendaEtapas, VendaProduto};
use App\Traits\TemArquivos;
use Illuminate\Database\Eloquent\{Collection, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $rede_id
 * @property string $nome
 * @property Carbon|null $data_nascimento
 * @property string|null $cpf
 * @property string|null $sexo
 * @property string|null $telefone
 * @property bool $telefone_whatsapp
 * @property string|null $email
 * @property string|null $cep
 * @property string|null $estado
 * @property string|null $cidade
 * @property string|null $bairro
 * @property string|null $logradouro
 * @property string|null $numero
 * @property string|null $complemento
 * @property string|null $observacoes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Agendamento> $agendamentos
 * @property-read Collection<int, VendaEtapas> $vendasEtapas
 * @property-read Collection<int, VendaProduto> $vendasProduto
 * @property-read Collection<int, Pagamento> $pagamentos
 */
class Cliente extends BaseModel implements PossuiArquivos
{
    use SoftDeletes;
    use TemArquivos;

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

    /**
     * Representacao resumida do cliente usada na busca AJAX (`clientes.buscar`) e no
     * card de selecao de cliente (ex.: tela de nova venda). Fonte unica do formato.
     *
     * @return array<string, mixed>
     */
    public function dadosParaCard(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'telefone' => $this->telefone,
            'telefone_whatsapp' => $this->telefone_whatsapp,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'sexo' => $this->sexo,
            'data_nascimento' => $this->data_nascimento?->format('d/m/Y'),
            'idade' => $this->data_nascimento?->age,
            'cep' => $this->cep,
            'estado' => $this->estado,
            'cidade' => $this->cidade,
            'bairro' => $this->bairro,
            'logradouro' => $this->logradouro,
            'numero' => $this->numero,
            'complemento' => $this->complemento,
            'imagem_thumb_url' => $this->imagem_thumb_url,
            'imagem_url' => $this->imagem_url,
        ];
    }
}
