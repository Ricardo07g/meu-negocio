<?php

namespace Database\Seeders;

use App\Enums\CondicaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\StatusAgendamento;
use App\Enums\StatusCaixa;
use App\Enums\StatusDespesa;
use App\Enums\StatusPagamento;
use App\Enums\StatusParcela;
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
use App\Modules\Despesa\Models\ParcelaDespesa;
use App\Modules\Estoque\Models\MovimentoEstoque;
use App\Modules\Pagamento\Models\Pagamento;
use App\Modules\Pagamento\Models\ParcelaPagamento;
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
use Spatie\Permission\Models\Role;

/**
 * Popula o banco com dados de teste volumosos.
 * Uso: php artisan db:seed --class=DesenvolvimentoSeeder
 * Login: admin@teste.com / password
 */
class DesenvolvimentoSeeder extends Seeder
{
    private const TOTAL_CLIENTES = 500;

    private const TOTAL_SERVICOS = 15;

    private const TOTAL_PRODUTOS = 30;

    private const TOTAL_ATENDENTES = 5;

    private const TOTAL_AGENDAMENTOS = 600;

    private const TOTAL_VENDAS_PACOTE = 60;

    private const TOTAL_VENDAS_PRODUTO = 100;

    private const TOTAL_DESPESAS = 150;

    private const TOTAL_DESPESAS_PARCEL = 30;

    private const CAIXAS_DIAS = 45;

    private const DIAS_PASSADO = 60;

    private const DIAS_FUTURO = 30;

    private $faker;

    private Rede $rede;

    private Empresa $empresa;

    /** @var Empresa[] Empresas adicionais para demo multi-empresa (ME-012). */
    private array $empresasExtras = [];

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

    /** @var Caixa[] */
    private array $caixasPorData = [];

    public function run(): void
    {
        config(['activitylog.enabled' => false]);
        $this->faker = FakerFactory::create('pt_BR');

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
            $this->criarCaixas();
            $this->criarAgendamentosEPagamentos();
            $this->criarVendasPacote();
            $this->criarVendasProduto();
            $this->criarDespesas();
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
                'telefone' => $this->faker->cellphoneNumber(),
                'email' => 'contato@teste.com',
            ],
        );

        // ME-012: cria mais 2 empresas para demonstrar o cenario multi-empresa
        // (Rede com N empresas, pivot empresa_usuario, seletor no header).
        foreach (['Filial Norte', 'Filial Sul'] as $nome) {
            $this->empresasExtras[] = Empresa::firstOrCreate(
                ['rede_id' => $this->rede->id, 'nome' => $nome],
                [
                    'documento' => $this->faker->numerify('##.###.###/0001-##'),
                    'telefone' => $this->faker->cellphoneNumber(),
                    'email' => strtolower(str_replace(' ', '', $nome)).'@teste.com',
                ],
            );
        }

        $this->command->info("Rede #{$this->rede->id} criada com 3 empresas (Unidade Central + Filial Norte + Filial Sul).");
    }

    private function criarUsuarios(): void
    {
        $adminRole = Role::where('name', 'Admin')->firstOrFail();

        $this->admin = Usuario::firstOrCreate(
            ['email' => 'admin@teste.com'],
            [
                'rede_id' => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'nome' => 'Administrador Demo',
                'password' => 'password',
                'ativo' => true,
                'atende' => false,
            ],
        );
        $this->admin->syncRoles([$adminRole]);

        // Atendentes da demo recebem o papel Admin para que a navegacao funcione
        // sem permission walls. A flag `atende = true` continua sendo o que
        // determina aparicao no select de atendente da Agenda — autorizacao
        // (Role) e funcao operacional (atende) sao independentes.
        //
        // ME-012: tambem populamos a pivot empresa_usuario com distribuicao
        // variada para demonstrar o cenario multi-empresa. Admin nao precisa
        // de pivot (acessa tudo via Role 'Admin').
        $todasEmpresas = array_merge([$this->empresa], $this->empresasExtras);

        for ($i = 1; $i <= self::TOTAL_ATENDENTES; $i++) {
            $atendente = Usuario::firstOrCreate(
                ['email' => "atendente{$i}@teste.com"],
                [
                    'rede_id' => $this->rede->id,
                    'empresa_id' => $this->empresa->id,
                    'nome' => $this->faker->name(),
                    'password' => 'password',
                    'ativo' => true,
                    'atende' => true,
                ],
            );
            $atendente->syncRoles([$adminRole]);

            // Distribuicao: atendente 1 -> todas; atendente 2 -> Central+Norte;
            // atendente 3 -> Central; atendente 4 -> Sul; atendente 5 -> Norte+Sul.
            $empresasParaAtendente = match ($i) {
                1 => $todasEmpresas,
                2 => [$todasEmpresas[0], $todasEmpresas[1]],
                3 => [$todasEmpresas[0]],
                4 => [$todasEmpresas[2]],
                5 => [$todasEmpresas[1], $todasEmpresas[2]],
                default => [$this->empresa],
            };
            // Pivot empresa_usuario tem rede_id obrigatorio — passamos via array.
            $sync = collect($empresasParaAtendente)
                ->mapWithKeys(fn ($e) => [$e->id => ['rede_id' => $this->rede->id]])
                ->all();
            $atendente->empresas()->sync($sync);

            $this->atendentes[] = $atendente;
        }

        $this->command->info('Usuários: 1 admin + '.count($this->atendentes).' atendentes (com pivot multi-empresa).');
    }

    private function criarCategoriasEProdutos(): void
    {
        foreach (['Cabelo', 'Corpo', 'Rosto', 'Unhas', 'Consumíveis', 'Suplementos', 'Outros'] as $n) {
            $this->categoriasProduto[] = CategoriaProduto::firstOrCreate(
                ['rede_id' => $this->rede->id, 'descricao' => $n],
                ['ativo' => true],
            );
        }

        foreach (['Aluguel', 'Energia', 'Água', 'Internet', 'Salário', 'Fornecedor', 'Imposto', 'Marketing', 'Manutenção', 'Outros'] as $n) {
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
                'rede_id' => $this->rede->id,
                'categoria_produto_id' => $cat->id,
                'nome' => ucfirst($this->faker->words(2, true)).' '.$this->faker->word(),
                'codigo' => 'P'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'codigo_barras' => $this->faker->ean13(),
                'descricao' => $this->faker->sentence(8),
                'quantidade' => $this->faker->numberBetween(0, 80),
                'valor_custo' => $custo,
                'valor_venda' => $venda,
                'estoque_minimo' => $this->faker->numberBetween(2, 10),
                'unidade' => $this->faker->randomElement(['un', 'cx', 'kg', 'L']),
                'ativo' => true,
            ]);
        }

        $this->command->info('Categorias e '.count($this->produtos).' produtos criados.');
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
                'rede_id' => $this->rede->id,
                'nome' => $s[0],
                'duracao' => $s[1],
                'valor' => $s[2],
                'tipo' => TipoServico::Avulso,
            ]);
        }

        foreach ([
            ['Pacote Massagem 10 Sessões', 60, 1000, 10],
            ['Pacote Drenagem 8 Sessões', 60, 720, 8],
            ['Pacote Depilação Laser 6 Sessões', 30, 1200, 6],
            ['Pacote Limpeza de Pele 4 Sessões', 60, 520, 4],
        ] as $p) {
            $this->servicos[] = Servico::create([
                'rede_id' => $this->rede->id,
                'nome' => $p[0],
                'duracao' => $p[1],
                'valor' => $p[2],
                'tipo' => TipoServico::Pacote,
                'qtd_sessoes' => $p[3],
            ]);
        }

        $this->command->info('Serviços: '.count($this->servicos).'.');
    }

    private function criarClientes(): void
    {
        $this->command->info('Criando '.self::TOTAL_CLIENTES.' clientes…');
        for ($i = 0; $i < self::TOTAL_CLIENTES; $i++) {
            $this->clientes[] = Cliente::create([
                'rede_id' => $this->rede->id,
                'nome' => $this->faker->name(),
                'telefone' => $this->faker->cellphoneNumber(),
                'telefone_whatsapp' => $this->faker->boolean(70),
                'email' => $this->faker->boolean(60) ? $this->faker->safeEmail() : null,
                'data_nascimento' => $this->faker->boolean(80) ? $this->faker->dateTimeBetween('-70 years', '-18 years') : null,
                'cpf' => $this->faker->boolean(60) ? $this->faker->cpf(false) : null,
                'sexo' => $this->faker->randomElement(['M', 'F', 'outro', null]),
            ]);
        }
    }

    private function criarCaixas(): void
    {
        $this->command->info('Criando caixas dos últimos '.self::CAIXAS_DIAS.' dias…');
        for ($i = self::CAIXAS_DIAS; $i >= 1; $i--) {
            $data = Carbon::today()->subDays($i);
            $caixa = Caixa::create([
                'rede_id' => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'usuario_id' => $this->admin->id,
                'data' => $data->toDateString(),
                'saldo_abertura' => $this->faker->randomFloat(2, 50, 300),
                'status' => StatusCaixa::Fechado,
                'fechado_em' => $data->copy()->endOfDay(),
                'fechado_por' => $this->admin->id,
            ]);
            $this->caixasPorData[$data->toDateString()] = $caixa;
        }
        $hoje = Carbon::today();
        $caixaHoje = Caixa::create([
            'rede_id' => $this->rede->id,
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $this->admin->id,
            'data' => $hoje->toDateString(),
            'saldo_abertura' => 200.00,
            'status' => StatusCaixa::Aberto,
        ]);
        $this->caixasPorData[$hoje->toDateString()] = $caixaHoje;
    }

    private function caixaParaData(Carbon $data): ?Caixa
    {
        return $this->caixasPorData[$data->toDateString()] ?? null;
    }

    private function criarAgendamentosEPagamentos(): void
    {
        $this->command->info('Criando '.self::TOTAL_AGENDAMENTOS.' agendamentos…');
        $avulsos = array_values(array_filter($this->servicos, fn ($s) => $s->tipo === TipoServico::Avulso));

        for ($i = 0; $i < self::TOTAL_AGENDAMENTOS; $i++) {
            $cliente = $this->faker->randomElement($this->clientes);
            $servico = $this->faker->randomElement($avulsos);
            $atendente = $this->faker->randomElement($this->atendentes);

            $diasOffset = $this->faker->numberBetween(-self::DIAS_PASSADO, self::DIAS_FUTURO);
            $hora = $this->faker->numberBetween(8, 18);
            $minuto = $this->faker->randomElement([0, 30]);
            $inicio = Carbon::now()->startOfDay()->addDays($diasOffset)->setTime($hora, $minuto);
            $fim = $inicio->copy()->addMinutes($servico->duracao);

            $status = $this->escolherStatusAgendamento($diasOffset);

            $ag = Agendamento::create([
                'rede_id' => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'cliente_id' => $cliente->id,
                'servico_id' => $servico->id,
                'atendente_id' => $atendente->id,
                'inicio' => $inicio,
                'fim' => $fim,
                'status' => $status,
            ]);

            if ($status === StatusAgendamento::Finalizado) {
                $this->criarPagamentoAvulso($ag);
            }
        }
    }

    private function escolherStatusAgendamento(int $diasOffset): StatusAgendamento
    {
        if ($diasOffset < -1) {
            return $this->faker->randomElement([
                StatusAgendamento::Finalizado, StatusAgendamento::Finalizado, StatusAgendamento::Finalizado,
                StatusAgendamento::Cancelado,
            ]);
        }
        if ($diasOffset <= 1) {
            return $this->faker->randomElement([
                StatusAgendamento::Confirmado, StatusAgendamento::Finalizado, StatusAgendamento::Cancelado,
            ]);
        }

        return $this->faker->randomElement([
            StatusAgendamento::Agendado, StatusAgendamento::Agendado, StatusAgendamento::Confirmado, StatusAgendamento::Cancelado,
        ]);
    }

    private function criarPagamentoAvulso(Agendamento $ag): void
    {
        $valor = (float) $ag->servico->valor;
        $dataVenda = Carbon::parse($ag->inicio);

        // 70% à vista, 30% a prazo (2-4 parcelas)
        if ($this->faker->boolean(70)) {
            $this->criarPagamentoAVista(
                valorTotal: $valor,
                mesReferencia: $dataVenda,
                dataVenda: $dataVenda,
                clienteId: $ag->cliente_id,
                agendamentoId: $ag->id,
            );
        } else {
            $this->criarPagamentoAPrazo(
                valorTotal: $valor,
                numParcelas: $this->faker->numberBetween(2, 4),
                mesReferencia: $dataVenda,
                primeiroVencimento: $dataVenda->copy()->addDays(30),
                clienteId: $ag->cliente_id,
                agendamentoId: $ag->id,
            );
        }
    }

    private function criarVendasPacote(): void
    {
        $pacotes = array_values(array_filter($this->servicos, fn ($s) => $s->tipo === TipoServico::Pacote));
        if (empty($pacotes)) {
            return;
        }

        $this->command->info('Criando '.self::TOTAL_VENDAS_PACOTE.' vendas de pacote…');
        for ($i = 0; $i < self::TOTAL_VENDAS_PACOTE; $i++) {
            $pacote = $this->faker->randomElement($pacotes);
            $cliente = $this->faker->randomElement($this->clientes);
            $atendente = $this->faker->randomElement($this->atendentes);
            $dataVenda = Carbon::now()->subDays($this->faker->numberBetween(1, self::DIAS_PASSADO));

            $vp = VendaPacote::create([
                'rede_id' => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'cliente_id' => $cliente->id,
                'servico_id' => $pacote->id,
                'atendente_id' => $atendente->id,
                'data' => $dataVenda,
                'valor_total' => $pacote->valor,
                'qtd_sessoes' => $pacote->qtd_sessoes,
                'status' => StatusVendaPacote::Ativo,
            ]);

            // Pacote: 40% à vista, 60% a prazo (3-12 parcelas)
            if ($this->faker->boolean(40)) {
                $this->criarPagamentoAVista(
                    valorTotal: (float) $pacote->valor,
                    mesReferencia: $dataVenda,
                    dataVenda: $dataVenda,
                    clienteId: $cliente->id,
                    vendaPacoteId: $vp->id,
                );
            } else {
                $this->criarPagamentoAPrazo(
                    valorTotal: (float) $pacote->valor,
                    numParcelas: $this->faker->numberBetween(3, 12),
                    mesReferencia: $dataVenda,
                    primeiroVencimento: $dataVenda->copy()->addDays(30),
                    clienteId: $cliente->id,
                    vendaPacoteId: $vp->id,
                );
            }
        }
    }

    private function criarVendasProduto(): void
    {
        $this->command->info('Criando '.self::TOTAL_VENDAS_PRODUTO.' vendas de produto…');
        for ($i = 0; $i < self::TOTAL_VENDAS_PRODUTO; $i++) {
            $cliente = $this->faker->randomElement($this->clientes);
            $usuario = $this->faker->randomElement($this->atendentes);
            $dataVenda = Carbon::now()->subDays($this->faker->numberBetween(0, self::DIAS_PASSADO));

            $itensQtd = $this->faker->numberBetween(1, 4);
            $subtotal = 0;

            $vp = VendaProduto::create([
                'rede_id' => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'cliente_id' => $cliente->id,
                'usuario_id' => $usuario->id,
                'data' => $dataVenda,
                'subtotal' => 0,
                'valor_total' => 0,
                'status' => StatusVendaProduto::Ativa,
            ]);

            for ($j = 0; $j < $itensQtd; $j++) {
                $produto = $this->faker->randomElement($this->produtos);
                $qtd = $this->faker->numberBetween(1, 4);
                $unit = (float) $produto->valor_venda;
                $sub = round($qtd * $unit, 2);
                $subtotal += $sub;

                DB::table('venda_produto_itens')->insert([
                    'venda_produto_id' => $vp->id,
                    'produto_id' => $produto->id,
                    'descricao' => $produto->nome,
                    'quantidade' => $qtd,
                    'valor_unitario' => $unit,
                    'desconto' => 0,
                    'acrescimo' => 0,
                    'subtotal' => $sub,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                MovimentoEstoque::create([
                    'rede_id' => $this->rede->id,
                    'empresa_id' => $this->empresa->id,
                    'produto_id' => $produto->id,
                    'tipo' => TipoMovimentoEstoque::Saida,
                    'quantidade' => $qtd,
                ]);
                $produto->decrement('quantidade', $qtd);
            }

            $vp->update(['subtotal' => $subtotal, 'valor_total' => $subtotal]);

            // Produto: 80% à vista, 20% a prazo (2-3 parcelas)
            if ($this->faker->boolean(80)) {
                $this->criarPagamentoAVista(
                    valorTotal: $subtotal,
                    mesReferencia: $dataVenda,
                    dataVenda: $dataVenda,
                    clienteId: $cliente->id,
                    vendaProdutoId: $vp->id,
                );
            } else {
                $this->criarPagamentoAPrazo(
                    valorTotal: $subtotal,
                    numParcelas: $this->faker->numberBetween(2, 3),
                    mesReferencia: $dataVenda,
                    primeiroVencimento: $dataVenda->copy()->addDays(30),
                    clienteId: $cliente->id,
                    vendaProdutoId: $vp->id,
                );
            }
        }
    }

    private function criarPagamentoAVista(
        float $valorTotal,
        Carbon $mesReferencia,
        Carbon $dataVenda,
        ?int $clienteId = null,
        ?int $agendamentoId = null,
        ?int $vendaPacoteId = null,
        ?int $vendaProdutoId = null,
    ): void {
        $forma = $this->faker->randomElement([FormaPagamento::Pix, FormaPagamento::Dinheiro, FormaPagamento::Cartao]);

        $pagamento = Pagamento::create([
            'rede_id' => $this->rede->id,
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $clienteId,
            'agendamento_id' => $agendamentoId,
            'venda_pacote_id' => $vendaPacoteId,
            'venda_produto_id' => $vendaProdutoId,
            'valor_total' => $valorTotal,
            'condicao_pagamento' => CondicaoPagamento::AVista,
            'mes_referencia' => $mesReferencia->copy()->startOfMonth(),
            'status' => StatusPagamento::Pago,
        ]);

        $parcela = ParcelaPagamento::create([
            'rede_id' => $this->rede->id,
            'empresa_id' => $this->empresa->id,
            'pagamento_id' => $pagamento->id,
            'numero' => 1,
            'total' => 1,
            'valor' => $valorTotal,
            'valor_pago' => $valorTotal,
            'data_vencimento' => $dataVenda,
            'forma_pagamento' => $forma,
            'status' => StatusParcela::Pago,
        ]);

        $caixa = $this->caixaParaData($dataVenda);
        if ($caixa) {
            $baixa = BaixaPagamento::create([
                'rede_id' => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'parcela_pagamento_id' => $parcela->id,
                'caixa_id' => $caixa->id,
                'valor' => $valorTotal,
                'forma_pagamento' => $forma,
                'data' => $dataVenda,
            ]);

            MovimentoCaixa::create([
                'caixa_id' => $caixa->id,
                'tipo' => TipoMovimentoCaixa::Entrada,
                'valor' => $valorTotal,
                'descricao' => "Recebimento venda #{$pagamento->id}",
                'forma_pagamento' => $forma,
                'baixa_pagamento_id' => $baixa->id,
            ]);
        }
    }

    private function criarPagamentoAPrazo(
        float $valorTotal,
        int $numParcelas,
        Carbon $mesReferencia,
        Carbon $primeiroVencimento,
        ?int $clienteId = null,
        ?int $agendamentoId = null,
        ?int $vendaPacoteId = null,
        ?int $vendaProdutoId = null,
    ): void {
        $pagamento = Pagamento::create([
            'rede_id' => $this->rede->id,
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $clienteId,
            'agendamento_id' => $agendamentoId,
            'venda_pacote_id' => $vendaPacoteId,
            'venda_produto_id' => $vendaProdutoId,
            'valor_total' => $valorTotal,
            'condicao_pagamento' => CondicaoPagamento::APrazo,
            'mes_referencia' => $mesReferencia->copy()->startOfMonth(),
            'status' => StatusPagamento::Pendente,
        ]);

        $valorParcela = round($valorTotal / $numParcelas, 2);
        $valorUltima = round($valorTotal - $valorParcela * ($numParcelas - 1), 2);

        $pagas = 0;
        for ($i = 1; $i <= $numParcelas; $i++) {
            $venc = $primeiroVencimento->copy()->addMonths($i - 1);
            $valor = $i === $numParcelas ? $valorUltima : $valorParcela;

            // Primeiras parcelas com mais chance de já estarem pagas
            $chance = max(10, 75 - ($i - 1) * 20);
            $foiPaga = $venc->isPast() && $this->faker->boolean($chance);

            $status = $foiPaga ? StatusParcela::Pago : StatusParcela::Pendente;
            $formaBaixa = $foiPaga
                ? $this->faker->randomElement([FormaPagamento::Pix, FormaPagamento::Dinheiro, FormaPagamento::Cartao])
                : null;

            $parcela = ParcelaPagamento::create([
                'rede_id' => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'pagamento_id' => $pagamento->id,
                'numero' => $i,
                'total' => $numParcelas,
                'valor' => $valor,
                'valor_pago' => $foiPaga ? $valor : 0,
                'data_vencimento' => $venc,
                'forma_pagamento' => $formaBaixa,
                'status' => $status,
            ]);

            if ($foiPaga) {
                $pagas++;
                $caixa = $this->caixaParaData($venc);
                if ($caixa) {
                    $baixa = BaixaPagamento::create([
                        'rede_id' => $this->rede->id,
                        'empresa_id' => $this->empresa->id,
                        'parcela_pagamento_id' => $parcela->id,
                        'caixa_id' => $caixa->id,
                        'valor' => $valor,
                        'forma_pagamento' => $formaBaixa,
                        'data' => $venc,
                    ]);
                    MovimentoCaixa::create([
                        'caixa_id' => $caixa->id,
                        'tipo' => TipoMovimentoCaixa::Entrada,
                        'valor' => $valor,
                        'descricao' => "Parcela {$i}/{$numParcelas} do pagamento #{$pagamento->id}",
                        'forma_pagamento' => $formaBaixa,
                        'baixa_pagamento_id' => $baixa->id,
                    ]);
                }
            }
        }

        if ($pagas === $numParcelas) {
            $pagamento->update(['status' => StatusPagamento::Pago]);
        } elseif ($pagas > 0) {
            $pagamento->update(['status' => StatusPagamento::Parcial]);
        }
    }

    private function criarDespesas(): void
    {
        $this->command->info('Criando '.(self::TOTAL_DESPESAS + self::TOTAL_DESPESAS_PARCEL).' despesas…');

        // Despesas simples (à vista)
        for ($i = 0; $i < self::TOTAL_DESPESAS; $i++) {
            $categoria = $this->faker->randomElement($this->categoriasDespesa);
            $valor = $this->faker->randomFloat(2, 50, 3000);
            $emissao = Carbon::now()->subDays($this->faker->numberBetween(0, self::DIAS_PASSADO));
            $vencimento = $emissao->copy()->addDays($this->faker->numberBetween(0, 30));

            $this->criarDespesaAVista(
                categoriaId: $categoria->id,
                nome: $categoria->descricao.' — '.$this->faker->words(2, true),
                valor: $valor,
                dataEmissao: $emissao,
                vencimento: $vencimento,
                mesReferencia: $emissao,
                fornecedor: $this->faker->boolean(75) ? $this->faker->company() : null,
                documento: $this->faker->boolean(60) ? 'DOC-'.$this->faker->numerify('######') : null,
            );
        }

        // Despesas parceladas
        for ($i = 0; $i < self::TOTAL_DESPESAS_PARCEL; $i++) {
            $categoria = $this->faker->randomElement($this->categoriasDespesa);
            $n = $this->faker->numberBetween(3, 12);
            $valorTotal = $this->faker->randomFloat(2, 600, 6000);
            $emissao = Carbon::now()->subDays($this->faker->numberBetween(0, 15));
            $primeiroVenc = $emissao->copy()->addDays($this->faker->numberBetween(0, 20));

            $this->criarDespesaAPrazo(
                categoriaId: $categoria->id,
                nome: $categoria->descricao.' — '.$this->faker->words(2, true),
                valorTotal: $valorTotal,
                numParcelas: $n,
                dataEmissao: $emissao,
                primeiroVencimento: $primeiroVenc,
                mesReferencia: $emissao,
                fornecedor: $this->faker->company(),
                documento: 'DOC-'.$this->faker->numerify('######'),
            );
        }
    }

    private function criarDespesaAVista(
        int $categoriaId,
        string $nome,
        float $valor,
        Carbon $dataEmissao,
        Carbon $vencimento,
        Carbon $mesReferencia,
        ?string $fornecedor = null,
        ?string $documento = null,
    ): void {
        $paga = $vencimento->isPast() && $this->faker->boolean(60);
        $status = $paga ? StatusDespesa::Paga : StatusDespesa::Pendente;
        $forma = $paga ? $this->faker->randomElement([FormaPagamento::Pix, FormaPagamento::Dinheiro, FormaPagamento::Boleto]) : null;

        $despesa = Despesa::create([
            'rede_id' => $this->rede->id,
            'empresa_id' => $this->empresa->id,
            'categoria_despesa_id' => $categoriaId,
            'nome' => $nome,
            'fornecedor_nome' => $fornecedor,
            'documento' => $documento,
            'valor_total' => $valor,
            'condicao_pagamento' => CondicaoPagamento::AVista,
            'mes_referencia' => $mesReferencia->copy()->startOfMonth(),
            'data_emissao' => $dataEmissao,
            'status' => $status,
        ]);

        $parcela = ParcelaDespesa::create([
            'rede_id' => $this->rede->id,
            'empresa_id' => $this->empresa->id,
            'despesa_id' => $despesa->id,
            'numero' => 1,
            'total' => 1,
            'valor' => $valor,
            'valor_pago' => $paga ? $valor : 0,
            'data_vencimento' => $vencimento,
            'forma_pagamento' => $forma,
            'status' => $paga ? StatusParcela::Pago : StatusParcela::Pendente,
        ]);

        if ($paga) {
            $caixa = $this->caixaParaData($vencimento);
            if ($caixa) {
                $baixa = BaixaDespesa::create([
                    'rede_id' => $this->rede->id,
                    'empresa_id' => $this->empresa->id,
                    'parcela_despesa_id' => $parcela->id,
                    'caixa_id' => $caixa->id,
                    'valor' => $valor,
                    'forma_pagamento' => $forma,
                    'data' => $vencimento,
                ]);
                MovimentoCaixa::create([
                    'caixa_id' => $caixa->id,
                    'tipo' => TipoMovimentoCaixa::Saida,
                    'valor' => $valor,
                    'descricao' => "Pagamento despesa #{$despesa->id}",
                    'forma_pagamento' => $forma,
                    'baixa_despesa_id' => $baixa->id,
                ]);
            }
        }
    }

    private function criarDespesaAPrazo(
        int $categoriaId,
        string $nome,
        float $valorTotal,
        int $numParcelas,
        Carbon $dataEmissao,
        Carbon $primeiroVencimento,
        Carbon $mesReferencia,
        ?string $fornecedor = null,
        ?string $documento = null,
    ): void {
        $despesa = Despesa::create([
            'rede_id' => $this->rede->id,
            'empresa_id' => $this->empresa->id,
            'categoria_despesa_id' => $categoriaId,
            'nome' => $nome,
            'fornecedor_nome' => $fornecedor,
            'documento' => $documento,
            'valor_total' => $valorTotal,
            'condicao_pagamento' => CondicaoPagamento::APrazo,
            'mes_referencia' => $mesReferencia->copy()->startOfMonth(),
            'data_emissao' => $dataEmissao,
            'status' => StatusDespesa::Pendente,
        ]);

        $valorParcela = round($valorTotal / $numParcelas, 2);
        $valorUltima = round($valorTotal - $valorParcela * ($numParcelas - 1), 2);

        $pagas = 0;
        for ($i = 1; $i <= $numParcelas; $i++) {
            $venc = $primeiroVencimento->copy()->addMonths($i - 1);
            $valor = $i === $numParcelas ? $valorUltima : $valorParcela;
            $chance = max(15, 70 - ($i - 1) * 20);
            $foiPaga = $venc->isPast() && $this->faker->boolean($chance);
            $status = $foiPaga ? StatusParcela::Pago : StatusParcela::Pendente;
            $forma = $foiPaga ? $this->faker->randomElement([FormaPagamento::Pix, FormaPagamento::Boleto, FormaPagamento::Dinheiro]) : null;

            $parcela = ParcelaDespesa::create([
                'rede_id' => $this->rede->id,
                'empresa_id' => $this->empresa->id,
                'despesa_id' => $despesa->id,
                'numero' => $i,
                'total' => $numParcelas,
                'valor' => $valor,
                'valor_pago' => $foiPaga ? $valor : 0,
                'data_vencimento' => $venc,
                'forma_pagamento' => $forma,
                'status' => $status,
            ]);

            if ($foiPaga) {
                $pagas++;
                $caixa = $this->caixaParaData($venc);
                if ($caixa) {
                    $baixa = BaixaDespesa::create([
                        'rede_id' => $this->rede->id,
                        'empresa_id' => $this->empresa->id,
                        'parcela_despesa_id' => $parcela->id,
                        'caixa_id' => $caixa->id,
                        'valor' => $valor,
                        'forma_pagamento' => $forma,
                        'data' => $venc,
                    ]);
                    MovimentoCaixa::create([
                        'caixa_id' => $caixa->id,
                        'tipo' => TipoMovimentoCaixa::Saida,
                        'valor' => $valor,
                        'descricao' => "Parcela {$i}/{$numParcelas} da despesa #{$despesa->id}",
                        'forma_pagamento' => $forma,
                        'baixa_despesa_id' => $baixa->id,
                    ]);
                }
            }
        }

        if ($pagas === $numParcelas) {
            $despesa->update(['status' => StatusDespesa::Paga]);
        } elseif ($pagas > 0) {
            $despesa->update(['status' => StatusDespesa::Parcial]);
        }
    }
}
