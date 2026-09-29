# Deploy produtivo na Vercel

## Estado

O backend está preparado para homologação e para o deploy produtivo depois que as dependências externas forem provisionadas. O deploy não deve ser considerado saudável enquanto `GET /api/v1/readiness` não retornar HTTP 200 com todos os checks `ok`.

## Configuração da Vercel

- Root Directory: `backend`
- Framework preset: Other
- Build command: `composer install --no-dev --optimize-autoloader`
- Install command: `composer install --no-dev --optimize-autoloader`
- Não usar SQLite, JSON gravável ou filesystem local como persistência.

## Variáveis obrigatórias

Configurar no ambiente Production da Vercel, sem versionar valores:

```text
APP_ENV=production
APP_DEBUG=false
APP_KEY=<gerado com php artisan key:generate --show>
APP_URL=https://api.<dominio>
CORS_ALLOWED_ORIGINS=https://<frontend>
DB_CONNECTION=pgsql
DB_HOST=<host>
DB_PORT=5432
DB_DATABASE=<database>
DB_USERNAME=<username>
DB_PASSWORD=<password>
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<access-key>
AWS_SECRET_ACCESS_KEY=<secret-key>
AWS_DEFAULT_REGION=<region>
AWS_BUCKET=<bucket>
QUEUE_CONNECTION=sync
CACHE_STORE=database
SESSION_DRIVER=database
```

`QUEUE_CONNECTION=sync` é adequado apenas enquanto não houver jobs assíncronos do produto. Quando houver jobs, usar um worker persistente externo; funções Vercel não substituem worker contínuo.

## Banco e migrations

Executar contra o PostgreSQL de produção antes de liberar tráfego:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

Não executar `migrate:fresh` em produção.

## Validação pós-deploy

```bash
curl -i https://api.<dominio>/up
curl -i https://api.<dominio>/api/v1/health
curl -i https://api.<dominio>/api/v1/readiness
```

O readiness deve indicar `database`, `app_key`, `cors` e `storage` como `ok`. Em produção, `storage` somente passa com `FILESYSTEM_DISK=s3`.

Depois validar login, troca obrigatória de senha, permissões dos cinco perfis, estoque, solicitação, entrega, QR, PDF e isolamento por indústria.

## Limitações e dependências externas

- PostgreSQL gerenciado, bucket S3 e credenciais precisam ser provisionados pelo responsável da infraestrutura.
- Backups devem ser enviados para armazenamento externo e testados por restauração.
- O cenário legado `tests/scenarios.php` depende do MariaDB antigo e não valida o backend Laravel.
- Integração de e-mail/SMTP permanece fora do escopo atual.
- Lecom exige API key, portal e processo publicados no ambiente correto.
