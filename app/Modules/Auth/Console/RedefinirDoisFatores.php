<?php

declare(strict_types=1);

namespace App\Modules\Auth\Console;

use App\Modules\Auth\Services\DoisFatoresService;
use App\Modules\Usuario\Models\Usuario;
use Illuminate\Console\Command;

/**
 * Ferramenta do suporte (ADR-0022): redefine o 2FA de um usuario — o unico caminho
 * para o Admin que perdeu o celular e os codigos de recuperacao, ja que nenhum
 * outro Admin da rede pode fazer isso pela tela (UsuarioPolicy).
 *
 * Sem auth no console -> o global scope de rede no-opa e a busca por e-mail
 * alcanca qualquer rede, que e justamente o papel do operador do SaaS. O motivo
 * (numero do chamado, como a identidade foi conferida) vai para a auditoria.
 */
class RedefinirDoisFatores extends Command
{
    protected $signature = 'usuarios:redefinir-2fa
                            {email : E-mail do usuario}
                            {--motivo= : Por que (chamado, como a identidade foi conferida) — obrigatorio}
                            {--operador= : Quem do suporte esta rodando (padrao: usuario do sistema)}';

    protected $description = 'Suporte: redefine a verificacao em duas etapas de um usuario (ex.: Admin que perdeu o celular).';

    public function handle(DoisFatoresService $service): int
    {
        $motivo = trim((string) $this->option('motivo'));

        if ($motivo === '') {
            $this->error('Informe o motivo com --motivo (ex.: "Chamado 123, identidade conferida por video").');

            return self::FAILURE;
        }

        $usuario = Usuario::where('email', $this->argument('email'))->first();

        if ($usuario === null) {
            $this->error('Nenhum usuário com esse e-mail.');

            return self::FAILURE;
        }

        if (! $usuario->temDoisFatoresAtivo()) {
            $this->warn("{$usuario->email} não tem a verificação em duas etapas ativa.");

            return self::FAILURE;
        }

        if (! $this->confirm("Redefinir a verificação em duas etapas de {$usuario->nome} <{$usuario->email}>?")) {
            $this->line('Nada foi alterado.');

            return self::FAILURE;
        }

        $operador = trim((string) $this->option('operador')) ?: (get_current_user() ?: 'desconhecido');
        $service->redefinirPeloSuporte($usuario, $motivo, $operador);

        $this->info('Verificação em duas etapas redefinida. O usuário entra só com a senha até configurar de novo.');

        return self::SUCCESS;
    }
}
