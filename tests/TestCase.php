<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\CriaTenant;

abstract class TestCase extends BaseTestCase
{
    use CriaTenant;
}
