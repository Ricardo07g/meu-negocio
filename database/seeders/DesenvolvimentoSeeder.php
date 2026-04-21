<?php

namespace Database\Seeders;

use App\Enums\FormaPagamento;
use App\Enums\StatusAgendamento;
use App\Enums\StatusCaixa;
use App\Enums\StatusDespesa;
use App\Enums\StatusPagamento;
use App\Enums\StatusRede;
use App\Enums\StatusVendaPacote;
use App\Enums\StatusVendaProduto;
use App\Enums\TipoMovimentoCaixa;
use App\Enums\TipoMovimentoEstoque;
use App\Enums\TipoServico;
use App\Modules\Agenda\Models\Agendamento;
use App\Modules\Caixa\Models\BaixaDespesa;
use App\Modules\Caixa\Models\BaixaPagamento;
use App\Modules\Caixa\Models\Caixa;
use App\Modules\Caixa\Models\MovimentoCaixa;
use App\Modules\Cliente\Models\Cliente;
use App\Modules\Despesa\Models\CategoriaDespesa;
use App\Modules\Despesa\Models\Despesa;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Produto\Models\CategoriaProduto;
use App\Modules\Produto\Models\Produto;
use App\Modules\Servico\Models\Servico;
use App\Modules\Tenant\Models\Empresa;
use App\Modules\Tenant\Models\Plano;
use App\Modules\Tenant\Models\Rede;
use App\Modules\Usuario\Models\Usuario;
use App\Modules\Venda\Models\VendaPacote;
use App\Modules\Venda\Models\VendaProduto;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Popula o banco com dados de teste volumosos.
 * Uso: php artisan db:seed --class=DesenvolvimentoSeeder
 * Login: admin@teste.com / password
 */
class DesenvolvimentoSeeder extends Seeder
{
    // Parâmetros de volume
    private const TOTAL_CLIENTES        = 500;
    private const TOTAL_SERVICOS        = 15;
    private const TOTAL_PRODUTOS        = 30;
    private const TOTAL_ATENDENTES      = 5;
    private const TOTAL_AGENDAMENTOS    = 800;
    private const TOTAL_VENDAS_PACOTE   = 80;
    private const TOTAL_VENDAS_PRODUTO  = 120;
    private const TOTAL_DESPESAS        = 200;
    private const TOTAL_DESPESAS_PARCEL = 40; // além das simples
    private const CAIXAS_DIAS           = 60;
    private const DIAS_PASSADO          = 60;
    private const DIAS_FUTURO           = 30;

    private $faker;
    private Rede $rede;
    private Empresa $empresa;
    private Usuario $admin;
    /** @var Usuario[] */
    private array $atendentes = [];
    /** @var CategoriaProduto[] */
    private array $categoriasProduto = [];
    /** @var CategoriaDespesa[] */
    private array $categoriasDespesa = [];
    /** @var Produto[] */
    private array $produtos = [];
    /** @var Servico[] */
    private array $servicos = [];
    /** @var Cliente[] */
    private array $clientes = [];

    public function run(): void
    {
        // Desabilita activity log para não poluir e ganhar velocidade
        config(['activitylog.enabled' => false]);

        $this->faker = FakerFactory::create('pt_BR');

        // Garante que planos e permissões existem
        $this->call([
            PlanoSeeder::class,
            PermissaoSeeder::class,
        ]);

        DB::transaction(function () {
            $this->criarRedeEEmpresa();
            $this->criarUsuarios();
            $this->criarCategoriasEProdutos();
            $this->criarServicos();
            $this->criarClientes();
            $this->criarAgendamentosEPagamentos();
            $this->criarVendasPacote();
            $this->criarVendasProduto();
            $this->criarDespesas();
            $this->criarCaixasEMovimentos();
        });

        $this->command->info('');
        $this->command->info('✅ Seed de desenvolvimento concluída!');
        $this->command->info('   Login:  admin@teste.com');
        $this->command->info('   Senha:  password');
    }

    private function criarRedeEEmpresa(): void
    {
        $plano = Plano::where('nome', 'business')->firstOrFail();

        $this->rede = Rede::firstOrCreate(
            ['nome' => 'Rede Demo'],
            ['plano_id' => $plano->id, 'status' => StatusRede::Ativa],
        );

        $this->empresa = Empresa::firstOrCreate(
            ['rede_id' => $this->rede->id, 'nome' => 'Unidade Central'],
            [
                'documento' => $this->faker->numerify('##.###.###/0001-##'),
                'telefone'  => $this->faker->cellphoneNumber(),
                'email'     => 'contato@teste.com',
            ],
        );

        $this->command->info("Rede #{$this->rede->id} e Empresa #{$this->empresa->id} criadas.");
    }

    private function criarUsuarios(): void
    {
        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $profRole  = Role::where('name', 'Profissional')->firstOrFail();

        $this->admin = Usuario::firstOrCreate(
            ['email' => 'admin@teste.com'],
            [
                'rede_id'    => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'nome'       => 'Administrador Demo',
                'password'   => 'password',
                'ativo'      => true,
                'atende'     => false,
            ],
        );
        $this->admin->syncRoles([$adminRole]);

        for ($i = 1; $i <= self::TOTAL_ATENDENTES; $i++) {
            $nome = $this->faker->name();
            $atendente = Usuario::firstOrCreate(
                ['email' => "atendente{$i}@teste.com"],
                [
                    'rede_id'    => $this->rede->id,
                    'empresa_id' => $this->empresa->id,
                    'nome'       => $nome,
                    'password'   => 'password',
                    'ativo'      => true,
                    'atende'     => true,
                ],
            );
            $atendente->syncRoles([$profRole]);
            $this->atendentes[] = $atendente;
        }

        $this->command->info('Usuários: 1 admin + ' . count($this->atendentes) . ' atendentes.');
    }

    private function criarCategoriasEProdutos(): void
    {
        $catProdNomes = ['Cabelo', 'Corpo', 'Rosto', 'Unhas', 'Consumíveis', 'Suplementos', 'Outros'];
        foreach ($catProdNomes as $n) {
            $this->categoriasProduto[] = CategoriaProduto::firstOrCreate(
                ['rede_id' => $this->rede->id, 'descricao' => $n],
                ['ativo' => true],
            );
        }

        $catDespNomes = ['Aluguel', 'Energia', 'Água', 'Internet', 'Salário', 'Fornecedor', 'Imposto', 'Marketing', 'Manutenção', 'Outros'];
        foreach ($catDespNomes as $n) {
            $this->categoriasDespesa[] = CategoriaDespesa::firstOrCreate(
                ['rede_id' => $this->rede->id, 'descricao' => $n],
                ['ativo' => true],
            );
        }

        for ($i = 0; $i < self::TOTAL_PRODUTOS; $i++) {
            $custo = $this->faker->randomFloat(2, 5, 200);
            $venda = round($custo * $this->faker->randomFloat(2, 1.4, 2.8), 2);
            $cat = $this->faker->randomElement($this->categoriasProduto);
            $this->produtos[] = Produto::create([
                'rede_id'              => $this->rede->id,
                'categoria_produto_id' => $cat->id,
                'nome'                 => ucfirst($this->faker->words(2, true)) . ' ' . $this->faker->word(),
                'codigo'               => 'P' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'codigo_barras'        => $this->faker->ean13(),
                'descricao'            => $this->faker->sentence(8),
                'quantidade'           => $this->faker->numberBetween(0, 80),
                'valor_custo'          => $custo,
                'valor_venda'          => $venda,
                'estoque_minimo'       => $this->faker->numberBetween(2, 10),
                'unidade'              => $this->faker->randomElement(['un', 'cx', 'kg', 'L']),
                'ativo'                => true,
            ]);
        }

        $this->command->info('Categorias e ' . count($this->produtos) . ' produtos criados.');
    }

    private function criarServicos(): void
    {
        $tiposAvulso = [
            ['Corte Masculino', 30, 45], ['Corte Feminino', 60, 85], ['Coloração', 90, 180],
            ['Escova Progressiva', 120, 220], ['Hidratação', 45, 60], ['Manicure', 45, 35],
            ['Pedicure', 60, 45], ['Depilação Axilas', 20, 30], ['Depilação Pernas', 45, 80],
            ['Massagem Relaxante', 60, 120], ['Limpeza de Pele', 60, 150],
        ];
        foreach (array_slice($tiposAvulso, 0, self::TOTAL_SERVICOS - 4) as $s) {
            $this->servicos[] = Servico::create([
                'rede_id'  => $this->rede->id,
                'nome'     => $s[0],
                'duracao'  => $s[1],
                'valor'    => $s[2],
                'tipo'     => TipoServico::Avulso,
            ]);
        }

        $pacotes = [
            ['Pacote Massagem 10 Sessões', 60, 1000, 10],
            ['Pacote Drenagem 8 Sessões', 60, 720, 8],
            ['Pacote Depilação Laser 6 Sessões', 30, 1200, 6],
            ['Pacote Limpeza de Pele 4 Sessões', 60, 520, 4],
        ];
        foreach ($pacotes as $p) {
            $this->servicos[] = Servico::create([
                'rede_id'     => $this->rede->id,
                'nome'        => $p[0],
                'duracao'     => $p[1],
                'valor'       => $p[2],
                'tipo'        => TipoServico::Pacote,
                'qtd_sessoes' => $p[3],
            ]);
        }

        $this->command->info('Serviços: ' . count($this->servicos) . ' (mix avulso/pacote).');
    }

    private function criarClientes(): void
    {
        $this->command->info('Criando ' . self::TOTAL_CLIENTES . ' clientes…');
        for ($i = 0; $i < self::TOTAL_CLIENTES; $i++) {
            $this->clientes[] = Cliente::create([
                'rede_id'             => $this->rede->id,
                'nome'                => $this->faker->name(),
                'telefone'            => $this->faker->cellphoneNumber(),
                'telefone_whatsapp'   => $this->faker->boolean(70),
                'email'               => $this->faker->boolean(60) ? $this->faker->safeEmail() : null,
                'data_nascimento'     => $this->faker->boolean(80) ? $this->faker->dateTimeBetween('-70 years', '-18 years') : null,
                'cpf'                 => $this->faker->boolean(60) ? $this->faker->cpf(false) : null,
                'sexo'                => $this->faker->randomElement(['M', 'F', 'outro', null]),
                'cep'                 => $this->faker->boolean(50) ? $this->faker->postcode() : null,
                'estado'              => $this->faker->boolean(50) ? $this->faker->stateAbbr() : null,
                'cidade'              => $this->faker->boolean(50) ? $this->faker->city() : null,
                'bairro'              => $this->faker->boolean(50) ? $this->faker->streetName() : null,
                'logradouro'          => $this->faker->boolean(50) ? $this->faker->streetAddress() : null,
                'numero'              => $this->faker->boolean(50) ? (string) $this->faker->numberBetween(1, 9999) : null,
                'complemento'         => $this->faker->boolean(25) ? $this->faker->secondaryAddress() : null,
                'observacoes'         => $this->faker->boolean(15) ? $this->faker->sentence() : null,
            ]);
        }
    }

    private function criarAgendamentosEPagamentos(): void
    {
        $this->command->info('Criando ' . self::TOTAL_AGENDAMENTOS . ' agendamentos + pagamentos…');
        $avulsos = array_values(array_filter($this->servicos, fn ($s) => $s->tipo === TipoServico::Avulso));

        for ($i = 0; $i < self::TOTAL_AGENDAMENTOS; $i++) {
            $cliente = $this->faker->randomElement($this->clientes);
            $servico = $this->faker->randomElement($avulsos);
            $atendente = $this->faker->randomElement($this->atendentes);

            $diasOffset = $this->faker->numberBetween(-self::DIAS_PASSADO, self::DIAS_FUTURO);
            $hora = $this->faker->numberBetween(8, 18);
            $minuto = $this->faker->randomElement([0, 30]);
            $inicio = Carbon::now()->startOfDay()->addDays($diasOffset)->setTime($hora, $minuto);
            $fim    = $inicio->copy()->addMinutes($servico->duracao);

            $status = $this->escolherStatusAgendamento($diasOffset);

            $ag = Agendamento::create([
                'rede_id'     => $this->rede->id,
                'empresa_id'  => $this->empresa->id,
                'cliente_id'  => $cliente->id,
                'servico_id'  => $servico->id,
                'atendente_id'=> $atendente->id,
                'inicio'      => $inicio,
                'fim'         => $fim,
                'status'      => $status,
                'observacoes' => $this->faker->boolean(15) ? $this->faker->sentence() : null,
            ]);

            if ($status === StatusAgendamento::Finalizado) {
                $this->criarPagamentoParaAgendamento($ag);
            }
        }
    }

    private function escolherStatusAgendamento(int $diasOffset): StatusAgendamento
    {
        if ($diasOffset < -1) {
            return $this->faker->randomElement([
                StatusAgendamento::Finalizado, StatusAgendamento::Finalizado, StatusAgendamento::Finalizado,
                StatusAgendamento::Cancelado, StatusAgendamento::Cancelado,
            ]);
        }
        if ($diasOffset <= 1) {
            return $this->faker->randomElement([
                StatusAgendamento::Confirmado, StatusAgendamento::Confirmado, StatusAgendamento::Confirmado,
                StatusAgendamento::Finalizado,
                StatusAgendamento::Cancelado,
            ]);
        }
        return $this->faker->randomElement([
            StatusAgendamento::Agendado, StatusAgendamento::Agendado,
            StatusAgendamento::Confirmado,
            StatusAgendamento::Cancelado,
        ]);
    }

    private function criarPagamentoParaAgendamento(Agendamento $ag): Pagamento
    {
        $valor = $ag->servico->valor;
        [$status, $valorPago, $forma] = $this->rollStatusPagamento($valor);

        return Pagamento::create([
            'rede_id'          => $this->rede->id,
            'empresa_id'       => $this->empresa->id,
            'cliente_id'       => $ag->cliente_id,
            'agendamento_id'   => $ag->id,
            'valor'            => $valor,
            'valor_pago'       => $valorPago,
            'data_vencimento'  => $ag->inicio->copy()->addDays($this->faker->numberBetween(0, 10)),
            'forma_pagamento'  => $forma,
            'status'           => $status,
        ]);
    }

    /**
     * @return array{0: StatusPagamento, 1: float, 2: ?FormaPagamento}
     */
    private function rollStatusPagamento(float $valor): array
    {
        $r = $this->faker->numberBetween(1, 100);
        $forma = $this->faker->randomElement(FormaPagamento::cases());

        if ($r <= 55) return [StatusPagamento::Pago,     $valor,                                                   $forma];
        if ($r <= 75) return [StatusPagamento::Pendente, 0,                                                        null];
        if ($r <= 85) return [StatusPagamento::Pendente, round($valor * $this->faker->randomFloat(2, 0.1, 0.8), 2), $forma];
        if ($r <= 92) return [StatusPagamento::Estornado, $valor,                                                  $forma];
        return [StatusPagamento::Cancelado, 0, null];
    }

    private function criarVendasPacote(): void
    {
        $pacotes = array_values(array_filter($this->servicos, fn ($s) => $s->tipo === TipoServico::Pacote));
        if (empty($pacotes)) return;

        $this->command->info('Criando ' . self::TOTAL_VENDAS_PACOTE . ' vendas de pacote…');
        for ($i = 0; $i < self::TOTAL_VENDAS_PACOTE; $i++) {
            $pacote = $this->faker->randomElement($pacotes);
            $cliente = $this->faker->randomElement($this->clientes);
            $atendente = $this->faker->randomElement($this->atendentes);
            $dataVenda = Carbon::now()->subDays($this->faker->numberBetween(1, self::DIAS_PASSADO));

            $status = $this->faker->randomElement([
                StatusVendaPacote::Ativo, StatusVendaPacote::Ativo, StatusVendaPacote::Ativo,
                StatusVendaPacote::Concluido,
                StatusVendaPacote::Cancelado,
            ]);

            $vp = VendaPacote::create([
                'rede_id'      => $this->rede->id,
                'empresa_id'   => $this->empresa->id,
                'cliente_id'   => $cliente->id,
                'servico_id'   => $pacote->id,
                'atendente_id' => $atendente->id,
                'data'         => $dataVenda,
                'valor_total'  => $pacote->valor,
                'qtd_sessoes'  => $pacote->qtd_sessoes,
                'status'       => $status,
            ]);

            // Pagamento do pacote
            [$statusPg, $valorPago, $forma] = $this->rollStatusPagamento($pacote->valor);
            Pagamento::create([
                'rede_id'         => $this->rede->id,
                'empresa_id'      => $this->empresa->id,
                'cliente_id'      => $cliente->id,
                'venda_pacote_id' => $vp->id,
                'valor'           => $pacote->valor,
                'valor_pago'      => $valorPago,
                'data_vencimento' => $dataVenda->copy()->addDays($this->faker->numberBetween(0, 30)),
                'forma_pagamento' => $forma,
                'status'          => $statusPg,
            ]);
        }
    }

    private function criarVendasProduto(): void
    {
        $this->command->info('Criando ' . self::TOTAL_VENDAS_PRODUTO . ' vendas de produto…');
        for ($i = 0; $i < self::TOTAL_VENDAS_PRODUTO; $i++) {
            $cliente = $this->faker->randomElement($this->clientes);
            $usuario = $this->faker->randomElement($this->atendentes);
            $dataVenda = Carbon::now()->subDays($this->faker->numberBetween(0, self::DIAS_PASSADO));

            $status = $this->faker->randomElement([
                StatusVendaProduto::Ativa, StatusVendaProduto::Ativa, StatusVendaProduto::Ativa, StatusVendaProduto::Ativa,
                StatusVendaProduto::Cancelada,
            ]);

            $itensQtd = $this->faker->numberBetween(1, 5);
            $subtotal = 0;

            $vp = VendaProduto::create([
                'rede_id'     => $this->rede->id,
                'empresa_id'  => $this->empresa->id,
                'cliente_id'  => $cliente->id,
                'usuario_id'  => $usuario->id,
                'data'        => $dataVenda,
                'subtotal'    => 0,
                'desconto'    => 0,
                'acrescimo'   => 0,
                'valor_total' => 0,
                'status'      => $status,
            ]);

            for ($j = 0; $j < $itensQtd; $j++) {
                $produto = $this->faker->randomElement($this->produtos);
                $qtd = $this->faker->numberBetween(1, 4);
                $unit = (float) $produto->valor_venda;
                $sub = round($qtd * $unit, 2);
                $subtotal += $sub;

                DB::table('venda_produto_itens')->insert([
                    'venda_produto_id' => $vp->id,
                    'produto_id'       => $produto->id,
                    'descricao'        => $produto->nome,
                    'quantidade'       => $qtd,
                    'valor_unitario'   => $unit,
                    'desconto'         => 0,
                    'acrescimo'        => 0,
                    'subtotal'         => $sub,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                if ($status === StatusVendaProduto::Ativa) {
                    MovimentoEstoque::create([
                        'rede_id'    => $this->rede->id,
                        'empresa_id' => $this->empresa->id,
                        'produto_id' => $produto->id,
                        'tipo'       => TipoMovimentoEstoque::Saida,
                        'quantidade' => $qtd,
                    ]);
                    $produto->decrement('quantidade', $qtd);
                }
            }

            $vp->update(['subtotal' => $subtotal, 'valor_total' => $subtotal]);

            [$statusPg, $valorPago, $forma] = $this->rollStatusPagamento($subtotal);
            Pagamento::create([
                'rede_id'          => $this->rede->id,
                'empresa_id'       => $this->empresa->id,
                'cliente_id'       => $cliente->id,
                'venda_produto_id' => $vp->id,
                'valor'            => $subtotal,
                'valor_pago'       => $valorPago,
                'data_vencimento'  => $dataVenda->copy()->addDays($this->faker->numberBetween(0, 30)),
                'forma_pagamento'  => $forma,
                'status'           => $statusPg,
            ]);
        }
    }

    private function criarDespesas(): void
    {
        $this->command->info('Criando ' . (self::TOTAL_DESPESAS + self::TOTAL_DESPESAS_PARCEL) . ' despesas…');

        // Despesas simples
        for ($i = 0; $i < self::TOTAL_DESPESAS; $i++) {
            $categoria = $this->faker->randomElement($this->categoriasDespesa);
            $valor = $this->faker->randomFloat(2, 50, 3000);
            $emissao = Carbon::now()->subDays($this->faker->numberBetween(0, self::DIAS_PASSADO));
            $vencDias = $this->faker->numberBetween(-30, 30);
            $vencimento = Carbon::now()->addDays($vencDias)->startOfDay();

            $statusRoll = $this->faker->numberBetween(1, 100);
            [$status, $valorPago, $forma] = $this->decideStatusDespesa($statusRoll, $valor, $vencimento);

            $despesa = Despesa::create([
                'rede_id'              => $this->rede->id,
                'empresa_id'           => $this->empresa->id,
                'categoria_despesa_id' => $categoria->id,
                'nome'                 => $categoria->descricao . ' — ' . $this->faker->words(2, true),
                'fornecedor_nome'      => $this->faker->boolean(75) ? $this->faker->company() : null,
                'documento'            => $this->faker->boolean(60) ? 'DOC-' . $this->faker->numerify('######') : null,
                'observacoes'          => $this->faker->boolean(25) ? $this->faker->sentence() : null,
                'valor'                => $valor,
                'valor_pago'           => $valorPago,
                'forma_pagamento'      => $forma,
                'data_emissao'         => $emissao,
                'data_vencimento'      => $vencimento,
                'competencia'          => $vencimento->copy()->startOfMonth(),
                'status'               => $status,
            ]);

            // Gera baixa(s) parciais quando há valor pago sem ser total
            if ($valorPago > 0 && $valorPago < $valor) {
                $this->criarBaixaDespesa($despesa, $valorPago, $forma ?? FormaPagamento::Pix);
            } elseif ($status === StatusDespesa::Paga && $valorPago > 0) {
                $this->criarBaixaDespesa($despesa, $valor, $forma ?? FormaPagamento::Pix);
            }
        }

        // Despesas parceladas (cada uma gera 3-12 registros)
        for ($i = 0; $i < self::TOTAL_DESPESAS_PARCEL; $i++) {
            $categoria = $this->faker->randomElement($this->categoriasDespesa);
            $n = $this->faker->numberBetween(3, 12);
            $valorTotal = $this->faker->randomFloat(2, 600, 6000);
            $valorParcela = round($valorTotal / $n, 2);
            $valorUltima = round($valorTotal - $valorParcela * ($n - 1), 2);
            $grupo = (string) Str::uuid();
            $nome = $categoria->descricao . ' — ' . $this->faker->words(2, true);
            $fornecedor = $this->faker->company();
            $documento = 'DOC-' . $this->faker->numerify('######');
            $emissao = Carbon::now()->subDays($this->faker->numberBetween(0, 15));
            $primeiroVenc = $emissao->copy()->addDays($this->faker->numberBetween(-20, 15));

            for ($j = 1; $j <= $n; $j++) {
                $vencimento = $primeiroVenc->copy()->addMonths($j - 1);
                $valor = $j === $n ? $valorUltima : $valorParcela;

                // Primeiras parcelas com maior chance de estar pagas
                $chancesPagar = max(20, 85 - ($j - 1) * 20);
                $pago = $this->faker->numberBetween(1, 100) <= $chancesPagar && $vencimento->isPast();

                $despesa = Despesa::create([
                    'rede_id'               => $this->rede->id,
                    'empresa_id'            => $this->empresa->id,
                    'categoria_despesa_id'  => $categoria->id,
                    'nome'                  => "{$nome} ({$j}/{$n})",
                    'fornecedor_nome'       => $fornecedor,
                    'documento'             => $documento,
                    'valor'                 => $valor,
                    'valor_pago'            => $pago ? $valor : 0,
                    'forma_pagamento'       => $pago ? $this->faker->randomElement(FormaPagamento::cases()) : null,
                    'data_emissao'          => $emissao,
                    'data_vencimento'       => $vencimento,
                    'competencia'           => $vencimento->copy()->startOfMonth(),
                    'status'                => $pago ? StatusDespesa::Paga : StatusDespesa::Pendente,
                    'grupo_parcelamento_id' => $grupo,
                    'parcela_numero'        => $j,
                    'parcela_total'         => $n,
                ]);

                if ($pago) {
                    $this->criarBaixaDespesa($despesa, $valor, $despesa->forma_pagamento);
                }
            }
        }
    }

    /**
     * @return array{0: StatusDespesa, 1: float, 2: ?FormaPagamento}
     */
    private function decideStatusDespesa(int $roll, float $valor, Carbon $vencimento): array
    {
        $forma = $this->faker->randomElement(FormaPagamento::cases());

        if ($roll <= 40) return [StatusDespesa::Paga,     $valor,                                                    $forma];
        if ($roll <= 60) return [StatusDespesa::Pendente, 0,                                                         null];
        if ($roll <= 75) return [StatusDespesa::Pendente, round($valor * $this->faker->randomFloat(2, 0.2, 0.7), 2), $forma];
        // 20% pendentes com vencimento passado = vencidas (derivado de data)
        if ($roll <= 92 && $vencimento->isPast()) return [StatusDespesa::Pendente, 0, null];
        return [StatusDespesa::Cancelada, 0, null];
    }

    private function criarBaixaDespesa(Despesa $despesa, float $valor, ?FormaPagamento $forma): void
    {
        BaixaDespesa::create([
            'rede_id'         => $this->rede->id,
            'empresa_id'      => $this->empresa->id,
            'despesa_id'      => $despesa->id,
            'caixa_id'        => null,
            'valor'           => $valor,
            'forma_pagamento' => ($forma ?? FormaPagamento::Pix)->value,
            'data'            => $despesa->data_emissao ?? now(),
            'observacao'      => $this->faker->boolean(20) ? $this->faker->sentence() : null,
        ]);
    }

    private function criarCaixasEMovimentos(): void
    {
        $this->command->info('Criando caixas e movimentos dos últimos ' . self::CAIXAS_DIAS . ' dias…');
        for ($i = self::CAIXAS_DIAS; $i >= 1; $i--) {
            $data = Carbon::today()->subDays($i);
            $this->criarCaixaDia($data, StatusCaixa::Fechado);
        }
        // Caixa de hoje aberto
        $this->criarCaixaDia(Carbon::today(), StatusCaixa::Aberto);
    }

    private function criarCaixaDia(Carbon $data, StatusCaixa $status): void
    {
        $caixa = Caixa::create([
            'rede_id'         => $this->rede->id,
            'empresa_id'      => $this->empresa->id,
            'usuario_id'      => $this->admin->id,
            'data'            => $data->toDateString(),
            'saldo_abertura'  => $this->faker->randomFloat(2, 50, 300),
            'saldo_fechamento'=> null,
            'status'          => $status,
            'observacao'      => null,
        ]);

        $qtdMov = $this->faker->numberBetween(4, 18);
        for ($m = 0; $m < $qtdMov; $m++) {
            $tipo = $this->faker->randomElement([
                TipoMovimentoCaixa::Entrada, TipoMovimentoCaixa::Entrada, TipoMovimentoCaixa::Entrada,
                TipoMovimentoCaixa::Saida, TipoMovimentoCaixa::Saida,
                TipoMovimentoCaixa::Sangria,
                TipoMovimentoCaixa::Reforco,
            ]);
            $valor = match ($tipo) {
                TipoMovimentoCaixa::Entrada => $this->faker->randomFloat(2, 30, 500),
                TipoMovimentoCaixa::Saida   => $this->faker->randomFloat(2, 20, 300),
                TipoMovimentoCaixa::Sangria => $this->faker->randomFloat(2, 50, 400),
                TipoMovimentoCaixa::Reforco => $this->faker->randomFloat(2, 50, 400),
            };
            MovimentoCaixa::create([
                'caixa_id'        => $caixa->id,
                'tipo'            => $tipo,
                'valor'           => $valor,
                'descricao'       => $this->descricaoMovimento($tipo),
                'forma_pagamento' => $tipo === TipoMovimentoCaixa::Entrada
                    ? $this->faker->randomElement(FormaPagamento::cases())->value
                    : null,
            ]);
        }

        if ($status === StatusCaixa::Fechado) {
            $entradas = MovimentoCaixa::where('caixa_id', $caixa->id)
                ->whereIn('tipo', [TipoMovimentoCaixa::Entrada, TipoMovimentoCaixa::Reforco])->sum('valor');
            $saidas = MovimentoCaixa::where('caixa_id', $caixa->id)
                ->whereIn('tipo', [TipoMovimentoCaixa::Saida, TipoMovimentoCaixa::Sangria])->sum('valor');
            $caixa->update([
                'saldo_fechamento' => (float) $caixa->saldo_abertura + (float) $entradas - (float) $saidas,
                'fechado_em'       => $data->copy()->endOfDay(),
                'fechado_por'      => $this->admin->id,
            ]);
        }
    }

    private function descricaoMovimento(TipoMovimentoCaixa $tipo): string
    {
        return match ($tipo) {
            TipoMovimentoCaixa::Entrada => 'Recebimento ' . $this->faker->randomElement(['atendimento', 'venda', 'pacote']),
            TipoMovimentoCaixa::Saida   => 'Pagamento ' . $this->faker->randomElement(['fornecedor', 'taxa', 'despesa']),
            TipoMovimentoCaixa::Sangria => 'Sangria para cofre',
            TipoMovimentoCaixa::Reforco => 'Reforço de troco',
        };
    }
}
