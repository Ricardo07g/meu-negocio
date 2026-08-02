<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Tenant\DTOs\CriarRedeData;
use App\Modules\Tenant\Models\{Empresa, Plano};
use App\Modules\Tenant\Services\RedeService;
use App\Modules\Usuario\DTOs\UsuarioData;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Rede em teste gratuito, para reproduzir o ciclo do trial em desenvolvimento.
 * Uso: php artisan db:seed --class=TrialSeeder
 * Login: trial@teste.com / password
 *
 * Passa pelo `RedeService` — o mesmo caminho de `POST /registrar` — em vez de criar a
 * Empresa na mao: quem define o prazo do teste e o `CriarEmpresaAction`, e o cenario so
 * vale se exercitar o codigo real do registro. (E por isso que a Rede Demo do
 * `DesenvolvimentoSeeder`, que cria as unidades direto, nunca produz um trial.)
 *
 * Rodar de novo re-arma o teste na rede existente (volta ao Pro com o prazo cheio), para
 * repetir o cenario quantas vezes quiser sem precisar de `migrate:fresh`.
 */
class TrialSeeder extends Seeder
{
    private const NOME_REDE = 'Rede Teste';

    private const EMAIL_ADMIN = 'trial@teste.com';

    public function run(): void
    {
        $empresa = $this->rearmar() ?? $this->registrar();

        $this->command->info('');
        $this->command->info('✅ Rede em teste gratuito pronta.');
        $this->command->info('   Login ........ '.self::EMAIL_ADMIN.' / password');
        $this->command->info("   Unidade ...... {$empresa->nome} — plano {$empresa->plano->nome}");
        $this->command->info(
            "   Teste ........ expira em {$empresa->trial_expira_em->format('d/m/Y')} "
            ."({$empresa->diasRestantesTrial()} dias restantes)"
        );
    }

    /**
     * Reabre o teste na rede ja semeada. Devolve null quando ela ainda nao existe.
     *
     * Reatribuir o Pro (e nao so a data) importa: depois de `assinaturas:expirar-trial`
     * a unidade esta no Gratis, e um trial no Gratis nao e o estado que queremos testar.
     */
    private function rearmar(): ?Empresa
    {
        $admin = Usuario::where('email', self::EMAIL_ADMIN)->first();

        if ($admin === null) {
            return null;
        }

        $empresa = Empresa::where('rede_id', $admin->rede_id)->orderBy('id')->firstOrFail();

        $empresa->update([
            'plano_id' => Plano::where('slug', Plano::PRO)->firstOrFail()->id,
            'trial_expira_em' => now()->addDays(Empresa::DIAS_DE_TRIAL),
        ]);

        $this->command->info('Teste re-armado na rede existente (sem recriar dados).');

        return $empresa->fresh(['plano']);
    }

    /** Registro de verdade: rede + primeira unidade em trial + Admin + catalogo enxuto. */
    private function registrar(): Empresa
    {
        $rede = app(RedeService::class)->criar(
            new CriarRedeData(nome: self::NOME_REDE),
            new UsuarioData(
                nome: 'Admin Teste',
                email: self::EMAIL_ADMIN,
                password: 'password',
                papel: 'Admin',
            ),
        );

        return Empresa::with('plano')->where('rede_id', $rede->id)->firstOrFail();
    }
}
