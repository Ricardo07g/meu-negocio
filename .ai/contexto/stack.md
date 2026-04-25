# Stack Tecnica

## Backend

- **PHP** ^8.3
- **Laravel** ^13.0
- **MySQL** 8.0
- **Redis** (cache/session)

## Pacotes PHP obrigatorios

| Pacote | Versao | Uso |
|--------|--------|-----|
| spatie/laravel-permission | ^7.2 | Papeis e permissoes (RBAC) |
| spatie/laravel-data | ^4.20 | DTOs (Data Transfer Objects) |
| spatie/laravel-activitylog | ^4.12 | Log de atividades/auditoria |

## Frontend

- **Vite** ^8.0 (build tool)
- **Tailwind CSS** ^4.0
- **@toast-ui/calendar** ^2.1.3 (calendario de agendamentos)
- **Axios** ^1.11 (HTTP client)
- **Bootstrap 5** (via template Duralux)
- **SweetAlert2** (confirmacoes)
- **Input masks** (telefone, CPF, CEP, data)
- **ViaCEP** (auto-preenchimento de endereco)

## Template

- **Duralux Admin 1.0.0**
- Local: `/home/ricardo/Documentos/Projetos/TEMAS/Duralux-admin-1.0.0/`
- Nunca criar layout novo — sempre integrar o template existente

## Docker (desenvolvimento)

| Servico | Container | Porta |
|---------|-----------|-------|
| PHP app | meu-negocio-app | — |
| Nginx | meu-negocio-nginx | 8080 |
| MySQL | meu-negocio-mysql | 3306 |
| Redis | meu-negocio-redis | 6379 |

Config: `docker-compose.yml` na raiz
Dockerfiles: `docker/php/`, `docker/nginx/`, `docker/mysql/`

## Comandos

```bash
docker compose up -d          # subir ambiente
docker compose exec app bash  # acessar container PHP
composer dev                  # servidor dev (concurrent: php, queue, logs, vite)
php artisan migrate           # rodar migrations
npm run dev                   # vite dev server
npm run build                 # build producao
```

## Providers importantes

- **AppServiceProvider** — registra PapelPolicy para Role, configura verbos de rota em portugues (novo/editar)
- **ModuleServiceProvider** — auto-descobre modulos em `app/Modules/`, carrega views e migrations de cada um
