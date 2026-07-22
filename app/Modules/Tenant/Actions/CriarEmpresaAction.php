<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Modules\Conta\Services\ContaService;
use App\Modules\FormaPagamento\Services\FormaPagamentoService;
use App\Modules\Tenant\DTOs\EmpresaData;
use App\Modules\Tenant\Models\{Empresa, Rede};

class CriarEmpresaAction
{
    public function __construct(
        private ValidarPlanoAction $validarPlano,
        private ContaService $contaService,
        private FormaPagamentoService $formaPagamentoService,
    ) {}

    public function executar(Rede $rede, EmpresaData $data): Empresa
    {
        $this->validarPlano->executar($rede, 'empresa');

        $empresa = Empresa::create([
            'rede_id' => $rede->id,
            'nome' => $data->nome,
            'documento' => $data->documento,
            'telefone' => $data->telefone,
            'email' => $data->email,
        ]);

        // Toda empresa nasce com suas contas financeiras padrao (Caixa + Banco)
        // e suas formas de pagamento padrao (cada unidade tem suas maquinas/taxas).
        $this->contaService->semearPadrao($rede->id, $empresa->id);
        $this->formaPagamentoService->semearPadrao($rede->id, $empresa->id);

        return $empresa;
    }
}
