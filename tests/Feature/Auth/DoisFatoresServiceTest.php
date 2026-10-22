<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Auth\Services\DoisFatoresService;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DoisFatores;
use Tests\TestCase;

/**
 * Corrida no anti-replay (review do ADR-0022): duas requisicoes simultaneas
 * carregam o usuario ANTES de qualquer uma gravar. A segunda decidia com o
 * estado velho e aceitava o mesmo codigo. Duas instancias do mesmo usuario
 * reproduzem isso de forma deterministica.
 */
class DoisFatoresServiceTest extends TestCase
{
    use DoisFatores;
    use RefreshDatabase;

    public function test_mesmo_codigo_em_requisicoes_simultaneas_so_vale_uma_vez(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $this->ativarDoisFatores($usuario);
        $codigo = $this->codigoAtual($usuario);

        $requisicaoA = Usuario::find($usuario->id);
        $requisicaoB = Usuario::find($usuario->id);
        $service = app(DoisFatoresService::class);

        $this->assertTrue($service->verificarCodigo($requisicaoA, $codigo));
        $this->assertFalse($service->verificarCodigo($requisicaoB, $codigo));
    }

    public function test_codigo_de_recuperacao_em_requisicoes_simultaneas_so_vale_uma_vez(): void
    {
        $usuario = $this->criarRede()['usuario'];
        $codigos = $this->ativarDoisFatores($usuario);

        $requisicaoA = Usuario::find($usuario->id);
        $requisicaoB = Usuario::find($usuario->id);
        $service = app(DoisFatoresService::class);

        $this->assertTrue($service->usarCodigoRecuperacao($requisicaoA, $codigos[0]));
        $this->assertFalse($service->usarCodigoRecuperacao($requisicaoB, $codigos[0]));
    }
}
