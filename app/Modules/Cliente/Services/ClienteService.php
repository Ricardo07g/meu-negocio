<?php

namespace App\Modules\Cliente\Services;

use App\Modules\Cliente\Actions\AtualizarClienteAction;
use App\Modules\Cliente\Actions\CriarClienteAction;
use App\Modules\Cliente\DTOs\ClienteData;
use App\Modules\Cliente\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClienteService
{
    public function __construct(
        private CriarClienteAction $criarCliente,
        private AtualizarClienteAction $atualizarCliente,
    ) {}

    public function listar(array $filtros = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Cliente::query()->orderBy('nome');

        if (! empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('nome', 'like', "%{$q}%")
                    ->orWhere('telefone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('cpf', 'like', "%{$q}%")
                    ->orWhere('cidade', 'like', "%{$q}%");
            });
        }

        $this->aplicarSituacaoFinanceira($query, $filtros['situacao_financeira'] ?? null);
        $this->aplicarAtividade($query, $filtros['atividade'] ?? null);

        if (! empty($filtros['aniversariantes'])) {
            $query->whereNotNull('data_nascimento')
                ->whereMonth('data_nascimento', now()->month);
        }

        if (! empty($filtros['com_whatsapp'])) {
            $query->where('telefone_whatsapp', true);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function aplicarSituacaoFinanceira($query, ?string $situacao): void
    {
        $hoje = now()->toDateString();

        match ($situacao) {
            'em_dia' => $query->whereDoesntHave('pagamentos', fn ($q) => $q->where('status', 'pendente')),
            'pendente' => $query->whereHas('pagamentos', fn ($q) => $q->where('status', 'pendente')
                ->where(fn ($sub) => $sub->whereNull('data_vencimento')->orWhereDate('data_vencimento', '>=', $hoje))
            ),
            'vencido' => $query->whereHas('pagamentos', fn ($q) => $q->where('status', 'pendente')->whereDate('data_vencimento', '<', $hoje)
            ),
            default => null,
        };
    }

    private function aplicarAtividade($query, ?string $atividade): void
    {
        if (! $atividade || $atividade === 'todos') {
            return;
        }

        if ($atividade === 'novo') {
            $query->where('created_at', '>=', now()->subDays(30));

            return;
        }

        if ($atividade === 'ativo') {
            $desde = now()->subDays(30);
            $query->where(fn ($q) => $q
                ->whereHas('agendamentos', fn ($sq) => $sq->where('inicio', '>=', $desde))
                ->orWhereHas('vendasPacote', fn ($sq) => $sq->where('created_at', '>=', $desde))
                ->orWhereHas('vendasProduto', fn ($sq) => $sq->where('created_at', '>=', $desde))
            );

            return;
        }

        // sumido_30, sumido_60, sumido_90, sumido_180
        if (str_starts_with($atividade, 'sumido_')) {
            $dias = (int) substr($atividade, 7);
            if ($dias <= 0) {
                return;
            }
            $desde = now()->subDays($dias);
            $query->whereDoesntHave('agendamentos', fn ($q) => $q->where('inicio', '>=', $desde))
                ->whereDoesntHave('vendasPacote', fn ($q) => $q->where('created_at', '>=', $desde))
                ->whereDoesntHave('vendasProduto', fn ($q) => $q->where('created_at', '>=', $desde));
        }
    }

    public function buscar(int $id): Cliente
    {
        return Cliente::findOrFail($id);
    }

    public function criar(ClienteData $data): Cliente
    {
        return $this->criarCliente->executar($data);
    }

    public function atualizar(Cliente $cliente, ClienteData $data): Cliente
    {
        return $this->atualizarCliente->executar($cliente, $data);
    }

    public function excluir(Cliente $cliente): void
    {
        $cliente->delete();
    }
}
