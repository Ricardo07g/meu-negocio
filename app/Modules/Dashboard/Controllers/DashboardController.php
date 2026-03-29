<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\TratamentoErros;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use TratamentoErros;

    public function index(): View|RedirectResponse
    {
        try
        {
            return view('dashboard::dashboard');
        } catch (\Throwable $e) {
            return $this->tratarErro($e, 'Erro ao listar dashboard');
        }
    }
}
