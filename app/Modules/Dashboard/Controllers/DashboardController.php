<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Services\DashboardService;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use TratamentoErros;

    public function __construct(
        private DashboardService $service,
    ) {}

    public function index(): View|RedirectResponse
    {
        try {
            return view('dashboard::dashboard', $this->service->indicadores());
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao carregar dashboard');
        }
    }
}
