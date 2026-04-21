<?php

namespace App\Modules\Despesa\Actions;

use App\Modules\Despesa\DTOs\DespesaData;
use App\Modules\Despesa\Models\Despesa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CriarDespesaParceladaAction
{
    public function executar(DespesaData $data): Collection
    {
        return DB::transaction(function () use ($data) {
            $n = (int) $data->numero_parcelas;
            $valorTotal = (float) $data->valor;
            $valorParcela = round($valorTotal / $n, 2);
            $valorUltima = round($valorTotal - ($valorParcela * ($n - 1)), 2);

            $primeiroVenc = Carbon::parse($data->data_vencimento);
            $emissao = Carbon::parse($data->data_emissao);
            $grupo = (string) Str::uuid();

            $despesas = new Collection();

            for ($i = 1; $i <= $n; $i++) {
                $vencimento = $primeiroVenc->copy()->addMonths($i - 1);
                $competencia = $vencimento->copy()->startOfMonth();

                $despesas->push(Despesa::create([
                    'categoria_despesa_id' => $data->categoria_despesa_id,
                    'nome' => "{$data->nome} ({$i}/{$n})",
                    'fornecedor_nome' => $data->fornecedor_nome,
                    'documento' => $data->documento,
                    'observacoes' => $data->observacoes,
                    'valor' => $i === $n ? $valorUltima : $valorParcela,
                    'valor_pago' => 0,
                    'data_emissao' => $emissao,
                    'data_vencimento' => $vencimento,
                    'competencia' => $competencia,
                    'status' => 'pendente',
                    'grupo_parcelamento_id' => $grupo,
                    'parcela_numero' => $i,
                    'parcela_total' => $n,
                ]));
            }

            return $despesas;
        });
    }
}
