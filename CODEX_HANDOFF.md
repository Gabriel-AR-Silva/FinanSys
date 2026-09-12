# Handoff do desenvolvimento — FinanSys

Atualizado em: 2026-09-12  
Branch de origem: `copilot_mod_v1`  
Branch de continuidade: `codex/copilot-mod-v1-hardening`  
Base original: `main@182c06003663067d8b2292fb83a67ff8dd76f29a`  
Commit do Copilot analisado: `f7c192bed83cdf7bbfaafdffb5094672e2f3c4c1`

## Objetivo deste arquivo

Este é o ponto único de continuidade para o próximo Codex. Ele substitui o diário `Modification_copilot.md`, que misturava etapas concluídas, observações intermediárias e pendências já superadas. Antes de continuar, leia também `AGENTS.md`, `.ai/rules/index.md` e os contratos financeiros aplicáveis.

## O que o Copilot entregou

O commit `f7c192b` expandiu o FinanSys de um ledger financeiro básico para um primeiro domínio de planejamento financeiro. A entrega inclui:

- configurações financeiras mensais, proteção de renda e orçamentos essenciais;
- previsões de recebimento, recorrência, liquidação parcial, excedente informativo, desvínculo e revínculo explícito;
- cartões de crédito, compras parceladas, parcelas, pagamentos e alocação determinística;
- reembolsos ligados à despesa original;
- cálculo das visões atual e projetada;
- fechamento financeiro diário, reconstrução de lacunas, revisões e proveniência;
- alertas financeiros internos deduplicados;
- novas telas Inertia para configurações, previsões, cartões, avaliações e alertas;
- migrations, factories, Actions, Queries e testes para essas áreas;
- contratos `FINANCIAL_PLANNING_CONTRACT.md`, `FINANCIAL_PLANNING_REVIEW.md` e `FINANCIAL_PLANNING_STAGES.md`.

WhatsApp e importação OFX permanecem intencionalmente fora desta publicação.

## Revisão realizada pelo Codex

A revisão comparou `copilot_mod_v1` com `main`, inspecionou contratos, migrations, Actions, Queries, modelos, rotas, testes e artefatos de produção. A branch do Copilot foi preservada sem alterações; todo o trabalho posterior ficou na branch `codex/copilot-mod-v1-hardening`.

### Correções aplicadas

1. **Fronteira mensal no fuso da aplicação**

   `FinancialPlanningOverviewQuery` convertia o início e o fim do mês de Brasília para UTC antes de consultar timestamps armazenados no padrão da aplicação. Isso excluía registros das primeiras três horas do primeiro dia e podia incluir registros das primeiras três horas do mês seguinte. A consulta agora mantém as fronteiras em `America/Sao_Paulo`, com teste cobrindo exatamente o primeiro e o último instante do mês.

2. **Alertas imediatos após compras no cartão**

   Criar uma compra parcelada altera a projeção financeira. `CreateCardPurchase` agora recalcula os alertas depois de persistir e auditar a compra e suas parcelas. Replay idempotente continua retornando antes do recálculo.

3. **Alertas imediatos após pagamento de cartão**

   Pagar parcelas altera o realizado financeiro. `PayCreditCard` agora recalcula os alertas somente depois da criação completa do pagamento, lançamento, alocações e auditorias, dentro da mesma operação transacional. Replay não repete o efeito.

4. **Reativação correta de alerta recuperado**

   Um alerta recuperado mantinha `recovered_at` quando voltava a uma situação adversa. Isso fazia um alerta ativo continuar parecendo recuperado nos filtros. `UpdateInternalAlert` agora limpa `recovered_at` na reincidência, preservando `worst_situation` e `deficit_seen`.

5. **CI e gate de build**

   Foi criado `.github/workflows/ci.yml` com PHP 8.4, Node.js 22, Composer, PHPUnit, Pint, build Vite, validação dos arquivos citados pelo manifest e upload do build como artefato.

6. **Artefatos de produção restaurados**

   A branch do Copilot continha um `public/build/manifest.json` novo, mas quase todos os bundles referenciados estavam ausentes. O build produzido pelo CI foi baixado, validado e publicado: 44 arquivos em `public/build`. A regra `/public/build` saiu do `.gitignore` porque o fluxo atual de hospedagem depende de artefatos compilados versionados; novos hashes agora aparecem no Git em vez de serem silenciosamente ignorados.

7. **Alertas sincronizados com mutações de planejamento**

   Criação, edição e cancelamento de previsões, vínculo de recebimentos, exclusão/restauração manual e cascatas de conta ou caixinha agora atualizam os alertas na mesma operação. Replay e edição sem alteração retornam antes do recálculo; cascatas formadas apenas por transferências continuam semanticamente neutras.

8. **Roteiro de etapas consolidado**

   `FINANCIAL_PLANNING_STAGES.md` deixou de funcionar como diário histórico e agora mostra o estado real de E0–E7, separando claramente o que foi entregue do que ainda bloqueia o contrato.

### Testes adicionados

- fronteiras do mês respeitam o fuso configurado da aplicação;
- compra parcelada cria/atualiza alerta projetado sem gerar snapshot;
- pagamento do cartão atualiza alerta atual sem gerar snapshot;
- alerta recuperado volta ao estado ativo quando a situação piora novamente.
- ciclo criar/editar/cancelar previsão atualiza o alerta projetado;
- vínculo com recebimento excedente evita dupla contagem na projeção;
- exclusão/restauração manual e cascatas de conta/caixinha atualizam o alerta atual.

## Evidência de validação

A execução inicial do workflow **FinanSys CI** concluiu com sucesso no GitHub Actions:

- Composer instalado;
- Pint aprovado;
- suíte PHPUnit aprovada;
- dependências frontend instaladas;
- build Vite aprovado;
- manifest e arquivos gerados consistentes;
- artefato `finansys-public-build` produzido.

Execução de referência: <https://github.com/Gabriel-AR-Silva/FinanSys/actions/runs/34701239413>.

Incremento de alertas validado em <https://github.com/Gabriel-AR-Silva/FinanSys/actions/runs/34702296003>: 346 testes e 1.873 assertions, Pint, build Vite e validação do manifest aprovados.

## Pendências reais

Estas pendências não devem ser confundidas com funcionalidades já entregues:

1. Completar o contrato de cartões: encargos, antecipação com desconto, estorno de compra e crédito explicitamente aplicado a outra fatura.
2. Validar concorrência no mesmo banco usado em produção. SQLite comprova regras e atomicidade básica, mas não reproduz locks e deadlocks de MySQL/PostgreSQL.
3. Revisar corrida entre transferência e exclusão/restauração de conta ou caixinha. As consultas atuais não bloqueiam todas as relações desde o início da operação.
4. Atualizar o `README.md`, que ainda afirma que categorias e fluxo HTTP de transferências não existem.
5. Fazer aceite visual no Microsoft Edge, desktop e mobile, principalmente nas novas páginas e modais.
6. Configurar proteção da branch `main` no GitHub. Não há ruleset nem status check obrigatório; isso não é resolvido apenas por código.
7. Planejar a migration de produção com backup e janela de recuperação. As novas tabelas são numerosas e carregam invariantes financeiras.
8. Definir política contábil final para a data de estornos. Atualmente o estorno preserva `occurred_at` da operação original, alterando retroativamente relatórios do período original.

## Ordem recomendada para continuar

1. Confirmar CI verde no último commit.
2. Revisar o diff `copilot_mod_v1...codex/copilot-mod-v1-hardening`.
3. Implementar o ciclo restante de cartões em incrementos contratuais pequenos.
4. Executar testes de concorrência no banco de produção escolhido.
5. Realizar aceite visual das novas telas.
6. Atualizar README e checklist de implantação.
7. Abrir PR para `develop` ou `main`; não fazer push direto na `main`.
8. Antes do deploy: backup, configuração segura do `.env`, preservação de `APP_KEY`, migrations, filas/agendador e verificação do build publicado.

## Comandos de verificação local

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
vendor/bin/pint --test
php artisan test --compact
npm run build
```

Após `npm run build`, confirme que todos os arquivos de `public/build` estão incluídos no commit de entrega.

## Regras que não devem ser quebradas

- valores financeiros permanecem decimais exatos, nunca `float`;
- todo dado financeiro permanece isolado por `user_id`;
- operação mutável usa idempotência e rejeita a mesma chave com parâmetros diferentes;
- escrita financeira, auditoria e efeitos internos obrigatórios permanecem atômicos;
- previsão aceita vários recebimentos, mas uma receita real pertence integralmente a no máximo uma previsão ativa;
- reembolso antigo afeta patrimônio, não renda sustentável do mês;
- avaliações reconstruídas não fingem ter sido apresentadas ao usuário;
- WhatsApp e OFX não bloqueiam a publicação atual.
