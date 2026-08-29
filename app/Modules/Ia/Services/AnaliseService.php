<?php

declare(strict_types=1);

namespace App\Modules\Ia\Services;

use App\Exceptions\PlanoLimiteException;
use App\Modules\Ia\Contracts\Ia;
use App\Modules\Ia\DTOs\{PedidoIa, RespostaIa};
use App\Modules\Ia\Enums\{StatusAnalise, TipoAnalise};
use App\Modules\Ia\Exceptions\IaIndisponivelException;
use App\Modules\Ia\Models\AnaliseIa;
use App\Support\PlanoVigente;
use Illuminate\Database\Eloquent\Model;

/**
 * Orquestra uma analise por IA. E o unico lugar que conhece a sequencia:
 *
 *     cache -> cota -> provedor -> registro
 *
 * As Actions de cada modulo so entregam o payload mastigado e o schema; nao sabem se
 * houve cache hit, quanto custou nem qual provedor respondeu.
 */
class AnaliseService
{
    public function __construct(private readonly Ia $ia) {}

    /**
     * @throws PlanoLimiteException cota diaria da empresa esgotada
     * @throws IaIndisponivelException provedor desligado, fora do ar ou fora do schema
     */
    public function analisar(Model $analisavel, TipoAnalise $tipo, PedidoIa $pedido): AnaliseIa
    {
        $hash = $this->hash($pedido);

        if ($cacheada = $this->buscarNoCache($analisavel, $tipo, $hash)) {
            return $this->reaproveitar($cacheada);
        }

        if (! $this->ia->estaAtivo()) {
            throw new IaIndisponivelException('provedor nao configurado');
        }

        $empresaId = $this->empresaId();

        if ($this->restanteDoDia($empresaId) <= 0) {
            // A recusa vira linha de proposito: sem isso, "quantas vezes batemos no teto"
            // seria invisivel — e e justamente o numero que diz se a cota esta bem calibrada.
            $this->registrar($analisavel, $tipo, $hash, $pedido, StatusAnalise::RecusadoCota, empresaId: $empresaId);

            throw new PlanoLimiteException('analises por IA no dia');
        }

        try {
            $resposta = $this->ia->analisar($pedido);
        } catch (IaIndisponivelException $e) {
            $this->registrar(
                $analisavel, $tipo, $hash, $pedido, StatusAnalise::Erro,
                empresaId: $empresaId, erro: $e->getMessage(),
            );

            throw $e;
        }

        return $this->registrar(
            $analisavel, $tipo, $hash, $pedido, StatusAnalise::Ok,
            empresaId: $empresaId, resposta: $resposta,
        );
    }

    // ██████╗ ██████╗ ████████╗ █████╗
    // ██╔════╝██╔═══██╗╚══██╔══╝██╔══██╗
    // ██║     ██║   ██║   ██║   ███████║
    // ██║     ██║   ██║   ██║   ██╔══██║
    // ╚██████╗╚██████╔╝   ██║   ██║  ██║
    //  ╚═════╝ ╚═════╝    ╚═╝   ╚═╝  ╚═╝

    /** Tokens ja gastos pela empresa no dia corrente do lojista (inclui erros e recusas). */
    public function consumoDoDia(?int $empresaId = null): int
    {
        $empresaId ??= $this->empresaId();

        return (int) AnaliseIa::query()
            ->where('empresa_id', $empresaId)
            ->doDiaCorrente()
            ->selectRaw('COALESCE(SUM(tokens_entrada), 0) + COALESCE(SUM(tokens_saida), 0) as total')
            ->value('total');
    }

    /** Cota da licenca da empresa em contexto. Zero = plano sem IA. */
    public function limiteDoDia(): int
    {
        $plano = PlanoVigente::resolver();

        return $plano === null ? 0 : (int) $plano->limite_tokens_ia_dia;
    }

    public function restanteDoDia(?int $empresaId = null): int
    {
        return max(0, $this->limiteDoDia() - $this->consumoDoDia($empresaId));
    }

    /**
     * Consumo do mes corrente para a tela de assinatura.
     *
     * `taxa_cache` e a metrica que diz se o desenho esta funcionando: quanto maior, mais
     * pedidos foram atendidos sem gastar um token — e o cache por hash e justamente o que
     * torna a feature barata.
     *
     * @return array{analises: int, reaproveitamentos: int, taxa_cache: int, tokens: int, custo: float}
     */
    public function estatisticasDoMes(?int $empresaId = null): array
    {
        $empresaId ??= $this->empresaId();

        $linhas = AnaliseIa::query()
            ->where('empresa_id', $empresaId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->get(['tokens_entrada', 'tokens_saida', 'custo_estimado', 'reaproveitacoes']);

        $chamadas = $linhas->count();
        $reaproveitamentos = (int) $linhas->sum('reaproveitacoes');
        $pedidos = $chamadas + $reaproveitamentos;

        return [
            'analises' => $chamadas,
            'reaproveitamentos' => $reaproveitamentos,
            'taxa_cache' => $pedidos > 0 ? (int) round($reaproveitamentos / $pedidos * 100) : 0,
            'tokens' => (int) $linhas->sum(fn (AnaliseIa $a): int => $a->tokensTotais()),
            'custo' => round((float) $linhas->sum('custo_estimado'), 4),
        ];
    }

    /** A feature aparece na tela? Precisa de licenca com cota E de provedor configurado. */
    public function disponivel(): bool
    {
        return $this->limiteDoDia() > 0 && $this->ia->estaAtivo();
    }

    // ██████╗ █████╗  ██████╗██╗  ██╗███████╗
    // ██╔════╝██╔══██╗██╔════╝██║  ██║██╔════╝
    // ██║     ███████║██║     ███████║█████╗
    // ██║     ██╔══██║██║     ██╔══██║██╔══╝
    // ╚██████╗██║  ██║╚██████╗██║  ██║███████╗
    //  ╚═════╝╚═╝  ╚═╝ ╚═════╝╚═╝  ╚═╝╚══════╝

    /**
     * A chave do cache.
     *
     * Inclui modelo e versao do prompt de proposito: trocar qualquer um dos dois muda a
     * resposta esperada, entao deve invalidar o que estava guardado. O que NAO entra aqui
     * e data crua — o payload chega ja classificado justamente para o hash so mudar quando
     * o negocio mudou, e nao a cada virada de dia.
     */
    private function hash(PedidoIa $pedido): string
    {
        return hash('sha256', json_encode([
            'dados' => $pedido->dados,
            'versao' => $pedido->versaoPrompt,
            'modelo' => $this->ia->modelo(),
        ], JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function buscarNoCache(Model $analisavel, TipoAnalise $tipo, string $hash): ?AnaliseIa
    {
        $analise = AnaliseIa::query()
            ->where('analisavel_type', $analisavel->getMorphClass())
            ->where('analisavel_id', $analisavel->getKey())
            ->where('tipo', $tipo->value)
            ->where('hash_entrada', $hash)
            ->where('status', StatusAnalise::Ok->value)
            ->latest('created_at')
            ->first();

        return $analise?->dentroDaValidade() ? $analise : null;
    }

    private function reaproveitar(AnaliseIa $analise): AnaliseIa
    {
        $analise->increment('reaproveitacoes');
        $analise->forceFill(['ultima_reaproveitacao_em' => now()])->save();

        return $analise;
    }

    private function registrar(
        Model $analisavel,
        TipoAnalise $tipo,
        string $hash,
        PedidoIa $pedido,
        StatusAnalise $status,
        ?int $empresaId = null,
        ?RespostaIa $resposta = null,
        ?string $erro = null,
    ): AnaliseIa {
        $precos = (array) config("ia.drivers.{$this->driverAtivo()}.precos", ['entrada' => 0, 'saida' => 0]);

        $custo = $resposta === null ? 0.0 : $resposta->custoEstimado(
            (float) ($precos['entrada'] ?? 0),
            (float) ($precos['saida'] ?? 0),
        );

        return AnaliseIa::create([
            'empresa_id' => $empresaId,
            'usuario_id' => auth()->id(),
            'analisavel_type' => $analisavel->getMorphClass(),
            'analisavel_id' => $analisavel->getKey(),
            'tipo' => $tipo->value,
            'status' => $status->value,
            'hash_entrada' => $hash,
            'versao_prompt' => $pedido->versaoPrompt,
            'modelo' => $this->ia->modelo(),
            'resultado' => $resposta?->dados,
            'erro' => $erro,
            'tokens_entrada' => $resposta === null ? 0 : $resposta->tokensEntrada,
            'tokens_saida' => $resposta === null ? 0 : $resposta->tokensSaida,
            'custo_estimado' => $custo,
            'duracao_ms' => $resposta === null ? 0 : $resposta->duracaoMs,
        ]);
    }

    private function driverAtivo(): string
    {
        return (string) config('ia.driver');
    }

    private function empresaId(): ?int
    {
        return PlanoVigente::empresaId();
    }
}
