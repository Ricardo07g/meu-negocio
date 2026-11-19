<?php

declare(strict_types=1);

namespace Ricardo07g\GuardaBot\Console;

use Illuminate\Console\Command;

use function Laravel\Prompts\{confirm, password, select, text};

use Ricardo07g\GuardaBot\{Modo, Turnstile};

/**
 * `guarda-bot:instalar`: configura o pacote pedindo o que falta.
 *
 * Em ambiente `local` grava no `.env` (depois de confirmar). Fora dele so
 * IMPRIME as variaveis: em hospedagem como Railway/Heroku/Fly o `.env` do
 * container nao persiste entre deploys, e o lugar certo e o painel do provedor.
 *
 * Toda pergunta tem uma opcao equivalente, para uso nao interativo (CI, script).
 */
class InstalarGuardaBot extends Command
{
    protected $signature = 'guarda-bot:instalar
        {--modo= : desligado, observar ou ativo}
        {--site-key= : Site key do Cloudflare Turnstile}
        {--secret-key= : Secret key do Cloudflare Turnstile}
        {--sem-turnstile : Nao configurar o Turnstile}
        {--gravar-env : Em ambiente local, grava no .env sem perguntar}
        {--publicar-config : Publica config/guarda-bot.php}';

    protected $description = 'Configura o GuardaBot: modo de operacao e chaves do Cloudflare Turnstile';

    public function handle(): int
    {
        $variaveis = ['GUARDA_BOT_MODO' => $this->modo()];

        if ($this->querTurnstile()) {
            $chaves = $this->chavesTurnstile();

            if ($chaves === null) {
                return self::FAILURE;
            }

            $variaveis += $chaves;
        }

        $this->entregar($variaveis);

        if ($this->option('publicar-config')
            || ($this->input->isInteractive() && confirm('Publicar config/guarda-bot.php para ajustar pesos e detectores?', default: false))) {
            $this->callSilently('vendor:publish', ['--tag' => 'guarda-bot-config']);
            $this->components->info('Config publicada em config/guarda-bot.php.');
        }

        $this->components->info('Pronto. Confira com: php artisan guarda-bot:diagnosticar');

        return self::SUCCESS;
    }

    private function modo(): string
    {
        $opcao = $this->option('modo');

        if (is_string($opcao) && Modo::tryFrom($opcao) !== null) {
            return $opcao;
        }

        if (! $this->input->isInteractive()) {
            return Modo::Observar->value;
        }

        return (string) select(
            label: 'Modo de operacao',
            options: [
                Modo::Observar->value => 'observar: registra no log, nunca bloqueia (recomendado no 1o deploy)',
                Modo::Ativo->value => 'ativo: bloqueia a partir do limiar',
                Modo::Desligado->value => 'desligado: nada e avaliado',
            ],
            default: Modo::Observar->value,
        );
    }

    private function querTurnstile(): bool
    {
        if ($this->option('sem-turnstile')) {
            return false;
        }

        if (filled($this->option('site-key')) || filled($this->option('secret-key'))) {
            return true;
        }

        // Sem interacao e sem chave nas opcoes nao ha de onde tira-las.
        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm('Configurar o Cloudflare Turnstile? (gratis, nao exige o dominio na Cloudflare)', default: true);
    }

    /**
     * @return array{TURNSTILE_SITE_KEY: string, TURNSTILE_SECRET_KEY: string}|null
     */
    private function chavesTurnstile(): ?array
    {
        $site = (string) ($this->option('site-key') ?: text(
            label: 'Turnstile: site key',
            required: true,
            hint: 'Painel da Cloudflare > Turnstile > seu widget',
        ));

        $secret = (string) ($this->option('secret-key') ?: password(
            label: 'Turnstile: secret key',
            required: true,
        ));

        $valida = Turnstile::secretValida($secret);

        if ($valida === false) {
            $this->components->error('A Cloudflare recusou esta secret key. Confira o valor no painel do Turnstile.');

            return null;
        }

        if ($valida === null) {
            $this->components->warn('Nao foi possivel falar com a Cloudflare para validar a secret key; seguindo assim mesmo.');
        }

        return ['TURNSTILE_SITE_KEY' => $site, 'TURNSTILE_SECRET_KEY' => $secret];
    }

    /**
     * @param  array<string, string>  $variaveis
     */
    private function entregar(array $variaveis): void
    {
        $arquivo = $this->laravel->environmentFilePath();

        // Gravar exige ambiente local, .env existente e consentimento: pergunta no
        // terminal ou `--gravar-env` explicito. Sem terminal, nunca grava calado.
        $gravar = $this->laravel->environment('local')
            && is_file($arquivo)
            && ($this->option('gravar-env')
                || ($this->input->isInteractive() && confirm("Gravar estas variaveis em {$arquivo}?", default: true)));

        if ($gravar) {
            $this->gravarNoEnv($arquivo, $variaveis);
            $this->components->info('Variaveis gravadas no .env.');

            return;
        }

        $this->components->info('Cadastre estas variaveis no ambiente da aplicacao (ex.: painel do provedor de hospedagem):');

        foreach ($variaveis as $nome => $valor) {
            // A secret nao volta para o terminal: quem instala acabou de digita-la.
            $this->line('  '.$nome.'='.($nome === 'TURNSTILE_SECRET_KEY' ? '<a secret key que voce informou>' : $valor));
        }
    }

    /**
     * Substitui a linha da variavel quando ela existe (inclusive comentada com
     * `# NOME=`) e acrescenta no fim quando nao existe.
     *
     * @param  array<string, string>  $variaveis
     */
    private function gravarNoEnv(string $arquivo, array $variaveis): void
    {
        $conteudo = (string) file_get_contents($arquivo);

        foreach ($variaveis as $nome => $valor) {
            $linha = $nome.'='.$valor;
            $padrao = '/^#?\s*'.preg_quote($nome, '/').'=.*$/m';

            $conteudo = preg_match($padrao, $conteudo) === 1
                ? (string) preg_replace($padrao, str_replace(['\\', '$'], ['\\\\', '\\$'], $linha), $conteudo, 1)
                : rtrim($conteudo, "\n")."\n".$linha."\n";
        }

        file_put_contents($arquivo, $conteudo);
    }
}
