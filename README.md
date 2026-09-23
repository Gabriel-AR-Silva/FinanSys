# FinanSys

Sistema de finanças pessoais com Laravel 13, Vue 3, Inertia, Tailwind CSS,
Vite e Lucide. Pinia permanece fora da base até existir estado cliente
compartilhado que realmente justifique uma store global.

## Preparação local

Requisitos: PHP 8.4.1+, Composer, Node.js 22+ e SQLite (padrão local).

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
`local`/`testing`. Configure o SMTP somente no `.env`; testes usam o mailer em
memória definido no `phpunit.xml`.

Durante o desenvolvimento, `DEV_SEED_DEMO_DATA=true` faz o mesmo seeder criar,
por meio de factories, um cenário financeiro local. Mantenha a opção desativada
fora de desenvolvimento; massa de testes nunca deve ser removida ou criada em
produção automaticamente.

O ambiente usa fila `database`. `composer dev` executa um `queue:work`
persistente, limitado a 100 jobs por processo para renovar memória. E-mails de
redefinição de senha são enviados de forma assíncrona, com tentativas e backoff.

## Qualidade

```bash
composer test
vendor/bin/pint --test
npm run build
```

O repositório também possui o workflow **FinanSys CI**, que executa PHPUnit,
Pint, build Vite e validação do manifest publicado. Resultado de SQLite não deve
ser tratado como evidência de concorrência do banco escolhido para produção.

## Regras arquiteturais

- Todo agregado financeiro carrega `user_id`; referências e consultas devem
  preservar isolamento entre usuários.
- Conta e caixinha são entidades diferentes. Uma caixinha pertence a uma conta,
  e saldos são sempre derivados dos lançamentos.
- Lançamentos referenciam conta ou caixinha pelo morph map obrigatório com os
  aliases estáveis `account` e `pocket`; nomes de classes não vazam no banco.
- Valores usam `DECIMAL(19,2)` e cálculos sensíveis usam precisão decimal exata,
  nunca `float`.
- Registros financeiros usam soft delete quando o domínio permite restauração.
  A retenção aprovada está em `config/finansys.php`; histórico financeiro e
  auditoria não são apagados silenciosamente.
- Actions mutáveis preservam escrita financeira, auditoria e efeitos internos
  obrigatórios na mesma fronteira transacional e usam idempotência quando
  aplicável.
- Sessões e snapshots de auditoria dependem da `APP_KEY`; preserve essa chave em
  backup e em qualquer troca de ambiente.
- Tipos fechados vivem em enums PHP e persistem como strings legíveis.
- As decisões financeiras de produto estão em
  `FINANCIAL_PLANNING_CONTRACT.md`; implementação não pode alterar silenciosamente
  uma regra aprovada.

## Estado funcional

- Contas e caixinhas podem ser criadas, editadas, excluídas e restauradas dentro
  da janela de retenção, com saldos derivados do ledger.
- Receitas e despesas manuais possuem listagem, filtros, paginação, criação,
  exclusão, restauração e estorno controlado.
- Categorias de receita/despesa possuem cadastro, edição e controle de status.
- Transferências entre conta/caixinha possuem fluxo de domínio e endpoint HTTP,
  com neutralidade no planejamento financeiro e idempotência coberta por testes.
- Reembolsos de despesas preservam a regra contábil entre mês corrente e meses
  anteriores e não viram renda silenciosamente.
- O Dashboard apresenta patrimônio, movimentações e planejamento financeiro nas
  visões atual e projetada.
- Configuração financeira mensal cobre proteção de renda e orçamentos essenciais.
- Recebimentos previstos cobrem recorrência, parcial, residual, excedente,
  cancelamento, vínculo, desvínculo, revínculo e registro direto do recebimento.
- Compromissos futuros preservam o saldo até o pagamento e suportam pagamento
  parcial, correção e cancelamento com idempotência e auditoria.
- Cartões cobrem cadastro, compras parceladas, distribuição de centavos,
  pagamentos parciais, alocação determinística e juros/multas confirmados como
  obrigações próprias, antecipação, estorno e aplicação de crédito sem criar
  renda ou saldo bancário artificial.
- A importação OFX oferece preview e revisão humana antes da confirmação, trata
  Pix no Crédito como uma compra de cartão com efeito bancário líquido zero e
  protege reimportação/replay contra duplicidade.
- Fechamento financeiro diário, reconstrução de lacunas, revisões e proveniência
  alimentam o histórico financeiro.
- Avisos financeiros internos são deduplicados por visão/período, preservam a
  pior situação observada e são atualizados por mutações financeiras relevantes.
- WhatsApp permanece fora do núcleo da V1 e, se retomado, será módulo opcional.

## Documentação canônica

- `FINANCIAL_PLANNING_CONTRACT.md`: regras de planejamento, projeção e efeitos
  financeiros transversais.
- `CARD_REVERSAL_CONTRACT.md`: estorno, crédito de cartão e aplicação.
- `OFX_IMPORT_CONTRACT.md`: parser, revisão humana, confirmação e conciliação
  OFX.
- `V1_ONBOARDING_CONTRACT.md`: onboarding, pré-requisitos, CTAs e Tsuki.
- `SAFE_DATA_RESET_CONTRACT.md`: limpeza segura de dados financeiros.
- `DAILY_FINANCIAL_ENGINE_V2_CONTRACT.md`: contrato conceitual unificado da V2;
  não altera a V1 nem autoriza implementação antes da revisão técnica.
- `docs/BUILD_BRANCH_WORKFLOW.md`: promoção `develop → build → main`.

## Continuidade de desenvolvimento

Antes de alterar regras financeiras, leia `AGENTS.md`, `.ai/rules/index.md` e o
contrato canônico aplicável. Estado de trabalho, evidências e pendências devem
ficar no PR ou issue correspondente, evitando handoffs permanentes que ficam
desatualizados.
