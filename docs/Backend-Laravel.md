# Migração do backend para Laravel

## Estado atual

O backend Laravel agora exige PHP 8.4+ porque o `composer.lock` usa Laravel 13, Symfony 8.1 e `endroid/qr-code` 6.1. O backend legado em `app/` continua sendo o destino do deploy raiz até a validação dos fluxos.

## Por que os registros zeravam na Vercel

O backend antigo executava SQLite dentro de funções serverless e tentava sincronizar o arquivo por Blob. Uma função pode iniciar em outra instância, usar outro filesystem temporário ou sobrescrever um snapshot anterior. Isso não oferece as garantias de concorrência e durabilidade necessárias para estoque.

## Decisão estrutural

- PostgreSQL é a fonte única dos dados operacionais.
- JSON não é banco de produção; fica restrito a fixtures, exportação e importação.
- Cada entrada ou saída usa transação e bloqueio pessimista da linha de estoque.
- Cada alteração cria um lançamento imutável em `stock_movements`.
- `idempotency_key` impede duplicidade acidental em reenvios do navegador ou da rede.
- O frontend só será apontado para a API Laravel depois da migração dos fluxos e dos testes.

## Deploy

Para homologação na Vercel, o projeto deve usar `backend` como Root Directory e um PostgreSQL externo. O deploy raiz permanece legado até a conclusão das próximas sprints. Para produção corporativa, recomenda-se hospedar o backend Laravel em um serviço PHP persistente e manter a Vercel para o frontend.

## Validação obrigatória antes da troca

1. executar as migrations em banco vazio;
2. importar uma cópia dos dados antigos;
3. registrar entrada e recarregar a página;
4. registrar saída e recarregar a página;
5. repetir a mesma requisição com a mesma chave de idempotência;
6. testar duas operações simultâneas;
7. conferir saldo, livro e auditoria;
8. só então alterar o roteamento de produção.
