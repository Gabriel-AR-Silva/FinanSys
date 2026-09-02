# FinanSys

Fundação do sistema de finanças pessoais com Laravel 13, Vue 3, Inertia,
Tailwind CSS, Vite e Lucide. Pinia permanece fora da base até existir estado
cliente compartilhado que realmente justifique uma store global.

## Preparação local

Requisitos: PHP 8.3+, Composer, Node.js 22+ e SQLite (padrão local).

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run build
composer dev
```

Para criar o usuário local, defina `DEV_USER_PASSWORD` apenas no `.env` e rode:

```bash
php artisan db:seed --class=DevelopmentSeeder
```

O e-mail padrão é `gabriel@gmail.com`, configurável por `DEV_USER_EMAIL`. O
seeder é idempotente, sempre gera hash e recusa ambientes diferentes de
`local`/`testing`. Configure o SMTP do Mailtrap somente no `.env`; testes usam o
mailer em memória (`array`) definido no `phpunit.xml`.

Durante o desenvolvimento, `DEV_SEED_DEMO_DATA=true` faz o mesmo seeder criar,
por meio de factories, um cenário financeiro local com contas, caixinhas e
lançamentos. Esse cenário também é idempotente. Mantenha a opção desativada em
outros ambientes; a remoção da massa de testes antes do uso real deve ser uma
operação explícita e confirmada, nunca automática.

O ambiente usa fila `database`. `composer dev` executa um `queue:work`
persistente, limitado a 100 jobs por processo para renovar memória. E-mails de
redefinição de senha são enviados de forma assíncrona, com tentativas e backoff.

## Qualidade

```bash
composer test
vendor/bin/pint --test
npm run build
```

## Limites arquiteturais do Sprint 0

- Todo agregado financeiro carrega `user_id`; referências passam pelo
  `ReferenceResolver`, que recusa IDs de outro usuário.
- Conta e caixinha são entidades diferentes. Uma caixinha pertence a uma conta,
  e a resolução também confirma o dono da conta pai.
- Lançamentos referenciam conta ou caixinha pelo morph map obrigatório com os
  aliases estáveis `account` e `pocket`; nomes de classes não vazam no banco.
- Valores usam `DECIMAL(19,2)`. Não há coluna de saldo: os saldos são projeções
  derivadas dos lançamentos.
- Registros financeiros têm soft delete. A retenção aprovada está em
  `config/finansys.php` (30 dias); o expurgo aguarda requisitos operacionais.
- Actions de escrita mantêm alteração e auditoria na mesma transação. O
  `AuditRecorder` também recusa registrar modelos pertencentes a outro usuário.
- Senhas e tokens de redefinição usam hash irreversível. Sessões e snapshots de
  auditoria são criptografados com `APP_KEY`; preserve essa chave em backups.
- Valores financeiros continuam consultáveis para projeções. A proteção do
  banco completo em repouso pertence à configuração do ambiente de implantação.
- Tipos fechados vivem em enums PHP e persistem como strings legíveis.

## Estado funcional

- Contas podem ser criadas com saldo inicial não negativo, renomeadas, excluídas
  e restauradas durante a retenção de 30 dias.
- A listagem de contas calcula o saldo a partir dos lançamentos e nunca de uma
  coluna de saldo mutável.
- O Dashboard apresenta saldo geral, saldos por tipo, totais mensais e os cinco
  lançamentos mais recentes do usuário autenticado. O gráfico de evolução do
  saldo geral permite períodos de 7, 15, 30, 60 e 365 dias, diferencia variações
  positivas e negativas e compara o saldo inicial ao final do período.
- Mensagens temporárias globais apresentam confirmações, alertas e erros no
  canto inferior direito; erros de formulário continuam indicados nos campos.
- Receitas e despesas manuais podem ser listadas, filtradas, paginadas, criadas,
  excluídas e restauradas por 30 dias. O valor é sempre positivo e o tipo define
  seu efeito no saldo; lançamentos internos permanecem somente leitura.
- Caixinhas podem ser criadas zeradas em uma conta ativa, renomeadas, excluídas
  com seus lançamentos e restauradas por 30 dias. O saldo disponível da conta e
  o total guardado em caixinhas são apresentados separadamente e derivados dos
  lançamentos, sem duplicar o saldo geral.
- Transferências já possuem action de domínio testada, mas ainda não têm fluxo
  HTTP ou interface.
- Recursos como categorias e recorrências permanecem fora do MVP atual.
