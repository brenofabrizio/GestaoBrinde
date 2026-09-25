# Dados JSON de homologação

Esta pasta é o formato legível de fixture/exportação do Controle de Brindes.

- O banco operacional continua sendo SQLite/MySQL/MariaDB.
- Os arquivos JSON não substituem transações, locks de estoque ou auditoria.
- Snapshots reais são ignorados pelo Git para não publicar dados pessoais.
- Use `php bin/export-json.php` para exportar cada tabela em um arquivo separado.
- Para começar do zero em homologação, use `php bin/install.php --fresh` com `APP_ENV=local` ou `APP_ENV=testing`.
- Para gerar dados de demonstração, use `php bin/install.php --fresh --demo` somente em ambiente não produtivo.

## Arquivos

- `fixtures/perfis.json`: matriz de perfis, permissões e menus esperados.
- `*.json`: snapshots gerados pelo exportador, quando houver um banco configurado.

O exportador remove `password_hash`, `session_version`, tokens e campos equivalentes antes de gravar usuários.
