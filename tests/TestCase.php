<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\CriaTenant;

abstract class TestCase extends BaseTestCase
{
    use CriaTenant;

    protected function setUp(): void
    {
        parent::setUp();

        // A suite roda sem buildar assets (o CI nao executa `npm run build`),
        // entao neutralizamos o Vite: qualquer view com @vite renderiza sem
        // exigir o public/build/manifest.json.
        $this->withoutVite();
    }

    /**
     * O `auth.session` guarda na sessao o hash da senha de quem esta logado e
     * derruba a sessao se ele nao bate (ADR-0022). Trocar de usuario no meio do
     * teste reaproveita a mesma sessao, entao o hash do anterior derrubaria o novo.
     * No app real nao ha esse caminho: trocar de usuario passa pelo logout, que
     * invalida a sessao inteira.
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        $this->app['session.store']->forget('password_hash_web');

        return parent::actingAs($user, $guard);
    }
}
