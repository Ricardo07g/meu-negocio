<?php

declare(strict_types=1);

namespace App\Support\Agenda;

use App\Modules\Usuario\Models\Usuario;
use Illuminate\Validation\Rule;

/**
 * Regras de validacao dos vinculos de um agendamento (cliente, servico,
 * atendente), escopadas por tenant.
 *
 * Existe porque a regra `exists` do Laravel monta a propria query e **ignora o
 * global scope de rede**: `exists:clientes,id` aceita o id de qualquer rede, e
 * bastava trocar o numero no request para o agendamento nascer apontando para
 * fora do tenant. Como as duas portas de criacao validam separado (o form
 * completo e a criacao rapida do calendario), a regra mora aqui para as duas
 * nao divergirem.
 */
class RegrasDeVinculo
{
    /**
     * @param  int  $redeId  rede do usuario logado — nunca vinda do request
     * @param  int|null  $empresaId  empresa em contexto, quando resolvivel
     * @param  bool  $obrigatorio  criacao exige os tres; edicao aceita parcial
     * @return array<string, array<int, mixed>>
     */
    public static function paraAgendamento(int $redeId, ?int $empresaId, bool $obrigatorio = true): array
    {
        $presenca = $obrigatorio ? 'required' : 'nullable';

        return [
            'cliente_id' => [$presenca, 'integer', self::daRede('clientes', $redeId)],
            'servico_id' => [$presenca, 'integer', self::daRede('servicos', $redeId)],
            'atendente_id' => [$presenca, 'integer', self::atendente($redeId, $empresaId)],
        ];
    }

    /** Catalogo (cliente/servico) e rede-level: basta pertencer a mesma rede. */
    private static function daRede(string $tabela, int $redeId): mixed
    {
        return Rule::exists($tabela, 'id')
            ->whereNull('deleted_at')
            ->where('rede_id', $redeId);
    }

    /**
     * Atendente e mais estreito que "usuario da rede": tem de atender E poder
     * operar a empresa do agendamento (pivot `empresa_usuario`, ou Role Admin).
     * Sem a empresa em contexto, sobra a rede + a flag `atende`.
     */
    private static function atendente(int $redeId, ?int $empresaId): mixed
    {
        if ($empresaId === null) {
            return Rule::exists('usuarios', 'id')
                ->whereNull('deleted_at')
                ->where('rede_id', $redeId)
                ->where('atende', true);
        }

        return Rule::in(Usuario::atendentesDaEmpresa($empresaId)->pluck('id')->all());
    }
}
