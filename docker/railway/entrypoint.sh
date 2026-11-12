#!/usr/bin/env sh
# Entrypoint de producao (Railway). Um unico entrypoint para varios papeis,
# selecionado pela variavel PROCESS (web | worker | scheduler). Mesma imagem/codigo,
# comandos diferentes — o padrao do Railway (um processo em primeiro plano por servico).
set -e

ROLE="${PROCESS:-web}"

# SQLite: o banco e um arquivo num volume persistente. Precisa existir antes de
# qualquer comando artisan que abra conexao — inclusive no worker/scheduler.
if [ "${DB_CONNECTION}" = "sqlite" ] && [ -n "${DB_DATABASE}" ]; then
    mkdir -p "$(dirname "${DB_DATABASE}")"
    [ -f "${DB_DATABASE}" ] || touch "${DB_DATABASE}"
fi

if [ "$ROLE" = "worker" ]; then
    echo "==> [worker] processando fila (driver de QUEUE_CONNECTION)"
    exec php artisan queue:work --tries=3 --sleep=3 --max-time=3600
fi

if [ "$ROLE" = "scheduler" ]; then
    echo "==> [scheduler] agendador (schedule:work)"
    exec php artisan schedule:work
fi

# ---------------------------------------------------------------------------
# web (padrao)
# ---------------------------------------------------------------------------
export PORT="${PORT:-8080}"

# Configuracao de producao errada aparece aqui, uma vez por deploy. Com APP_ENV de
# producao o app ja ignora o APP_DEBUG ligado (App\Support\GuardaDeDebug); sem ele
# a trava nao tem como saber que e producao — por isso os dois avisos.
if [ "${APP_ENV}" != "production" ]; then
    echo "!! ATENCAO: APP_ENV='${APP_ENV}' — em producao deve ser 'production'."
fi
if [ "${APP_DEBUG}" = "true" ] || [ "${APP_DEBUG}" = "1" ]; then
    echo "!! ATENCAO: APP_DEBUG ligado — defina APP_DEBUG=false nas variaveis do servico."
fi

echo "==> Limpando caches de config/rotas..."
php artisan config:clear || true
php artisan route:clear || true

echo "==> Rodando migrations..."
# Nao derruba o container se o banco ainda nao estiver pronto — o log mostra a causa.
php artisan migrate --force || echo "!! migrate falhou (veja o log acima)"

echo "==> Seed essencial (planos + permissoes) — idempotente (updateOrCreate/firstOrCreate)..."
php artisan db:seed --force || echo "!! seed falhou (veja o log acima)"

echo "==> Criando link de storage..."
php artisan storage:link || true

echo "==> Servindo em 0.0.0.0:${PORT} (FrankenPHP)"
# O Caddyfile le a porta de ${PORT} e serve /app/public com cache nos estaticos.
exec frankenphp run --config /etc/frankenphp/Caddyfile
