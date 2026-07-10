<?php

declare(strict_types=1);

namespace Tests;

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
}
