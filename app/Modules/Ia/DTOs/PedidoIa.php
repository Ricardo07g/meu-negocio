<?php

declare(strict_types=1);

namespace App\Modules\Ia\DTOs;

use Spatie\LaravelData\Data;

/**
 * O que se pede ao modelo. Note o que NAO esta aqui: nenhuma query, nenhum id cru,
 * nenhuma data. Quem monta o pedido ja fez a conta — o modelo so le e redige.
 */
class PedidoIa extends Data
{
    public function __construct(
        /** Papel e regras do modelo (system prompt). */
        public string $instrucoes,

        /** Dados ja mastigados e classificados pelo PHP. Vira JSON no corpo do prompt. */
        public array $dados,

        /** JSON Schema da resposta. Mantenha achatado: a Cloudflare recusa schema complexo. */
        public array $schema,

        /** Rastreia de qual prompt veio o resultado guardado. */
        public string $versaoPrompt = 'v1',
    ) {}
}
