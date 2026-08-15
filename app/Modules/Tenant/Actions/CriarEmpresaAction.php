<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Actions;

use App\Exceptions\NegocioException;
use App\Modules\Conta\Services\ContaService;
use App\Modules\FormaPagamento\Services\FormaPagamentoService;
use App\Modules\Tenant\DTOs\EmpresaData;
use App\Modules\Tenant\Models\{Empresa, Plano, Rede};
use App\Modules\Tenant\Services\ExpedienteService;
use Illuminate\Support\Carbon;

/**
 * Cria uma unidade — ou seja, contrata uma licenca.
 *
 * Nao e mais acao do tenant: contratar unidade e ato comercial do operador do SaaS
 * (painel de superusuario, futuro; hoje pelo comando `empresa:contratar`). Esta Action
 * e a costura unica que ambos consomem, e e o que garante que a unidade nasce operante
 * (com contas e formas de pagamento).
 */
class CriarEmpresaAction
{
    public function __construct(
        private ContaService $contaService,
        private FormaPagamentoService $formaPagamentoService,
        private ExpedienteService $expedienteService,
    ) {}

    public function executar(Rede $rede, EmpresaData $data, ?Plano $plano = null): Empresa
    {
        $plano ??= Plano::where('slug', Plano::PRO)->firstOrFail();

        $this->validarLicencaGratis($rede, $plano);

        $empresa = Empresa::create([
            'rede_id' => $rede->id,
            'plano_id' => $plano->id,
            'trial_expira_em' => $this->trialDaPrimeiraUnidade($rede, $plano),
            'nome' => $data->nome,
            'documento' => $data->documento,
            'telefone' => $data->telefone,
            'email' => $data->email,
        ]);

        // Toda empresa nasce com suas contas financeiras padrao (Caixa + Banco),
        // suas formas de pagamento padrao (cada unidade tem suas maquinas/taxas)
        // e seu expediente — sem ele a agenda nao teria janela para validar, e
        // qualquer horario passaria como se nada estivesse configurado.
        $this->contaService->semearPadrao($rede->id, $empresa->id);
        $this->formaPagamentoService->semearPadrao($rede->id, $empresa->id);
        $this->expedienteService->semearPadrao($rede->id, $empresa->id);

        return $empresa;
    }

    /**
     * O teste gratuito e aquisicao, nao brinde por unidade: so a primeira unidade da
     * rede (a do registro) nasce em trial. Unidade contratada depois ja nasce paga.
     */
    private function trialDaPrimeiraUnidade(Rede $rede, Plano $plano): ?Carbon
    {
        if ($plano->slug !== Plano::PRO || $rede->empresas()->exists()) {
            return null;
        }

        return now()->addDays(Empresa::DIAS_DE_TRIAL);
    }

    /**
     * O Gratis vale para uma unica unidade por rede — senao bastaria abrir N empresas
     * gratuitas para ter a rede inteira de graca.
     */
    private function validarLicencaGratis(Rede $rede, Plano $plano): void
    {
        if ($plano->slug !== Plano::GRATIS) {
            return;
        }

        if ($rede->empresas()->where('plano_id', $plano->id)->exists()) {
            throw new NegocioException(
                'O plano Grátis vale para uma única unidade por rede. '
                .'Contrate o plano Pro para adicionar outra unidade.'
            );
        }
    }
}
