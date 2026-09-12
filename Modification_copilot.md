# Registro de modificacoes do Copilot

Data: 2026-09-12

## Alteracao realizada

Ajustada a media diaria do ritmo financeiro em `FinancialPlanningMath::ordinaryProjection`.

- A taxa diaria exibida agora usa arredondamento para baixo nos centavos (`RoundingMode::Floor`), conforme o contrato financeiro.
- A projecao continua usando a fracao exata da taxa antes do arredondamento.
- Exemplo coberto: 100 / 3 dias completos retorna media exibida de 33,33.

## Arquivos modificados

- `app/Support/FinancialPlanningMath.php`
  - Alterado o arredondamento de `daily_rate` de `HalfUp` para `Floor`.
- `tests/Unit/Support/FinancialPlanningMathTest.php`
  - Adicionado teste para a media fracionaria de 100 / 3.
- `Modification_copilot.md`
  - Este registro para continuidade do desenvolvimento.

## Validacao

- Testes matematicos e de integracao do planejamento: 64 testes aprovados, 97 assercoes.
- Pint: aprovado.
- Nenhum erro encontrado nos arquivos PHP alterados.

## Observacao para continuidade

A media historica exibida no card `average_daily_expense` de `FinancialOverviewQuery` permanece separada da media de planejamento. O contrato determina que a media movel historica nao seja reutilizada automaticamente como ritmo financeiro mensal.

## Entrega seguinte - media livre diaria

- `app/Queries/FinancialPlanningOverviewQuery.php` agora expoe `planning.daily` com margem disponivel, valor diario, resto de centavos e dias restantes do mes em Brasilia.
- A distribuicao usa a margem livre depois da reserva dos essenciais. Deficit permanece em `daily.available`; somente valor nao negativo e distribuido.
- `resources/js/Pages/Dashboard.vue` passou a exibir a media livre por dia e a quantidade de dias restantes, identificando que os essenciais ja foram reservados.
- O teste de integracao cobre o cenario de R$ 350,00 livres em 28 dias, resultando em R$ 12,50 por dia.

Validacao adicional: 74 testes aprovados, 262 assercoes, build Vite aprovado e Pint aprovado.

## Correcao de diagnostico inicial

- `app/Queries/FinancialPlanningOverviewQuery.php` agora informa explicitamente se o diagnostico de ritmo esta disponivel em `current` e `projected`.
- `resources/js/Pages/Dashboard.vue` nao exibe uma faixa de ritmo nos dois primeiros dias do mes; insuficiencia conhecida continua visivel.
- `tests/Feature/Queries/FinancialPlanningOverviewQueryTest.php` cobre o segundo dia sem diagnostico de ritmo.

Validacao: teste focal com 6 testes e 33 assercoes aprovado.

## Revisao de status E2

A verificacao do codigo mostrou que itens que ainda apareciam como pendentes no roteiro ja foram entregues:

- Recorrencia de previsoes, incluindo preservacao do dia original em meses com menos dias.
- Edicao de uma ocorrencia ou das ocorrencias futuras sem alterar ocorrencias ja liquidadas.
- Revinculo explicito apos restaurar um recebimento, com operacao idempotente.
- Restauracao de lancamento nao reativa vinculo silenciosamente.

Validacao: `ReceiptForecastTest` e `ReceiptForecastReceiptTest`, com 46 testes e 365 assercoes aprovados.

Proxima frente real: E6 (fechamento diario, historico e alertas internos) e os gates finais de publicacao. Nao reimplementar E2 sem uma falha nova reproduzida.

## E6 parcial - fechamento diario

- Criado `FinancialEvaluation` para preservar as visoes `current` e `projected` por usuario, data de Brasilia e versao das regras.
- Criada a acao `CloseFinancialEvaluation`, com transacao, lock do usuario, comparacao por data e replay idempotente sem sobrescrever o resultado ja apresentado.
- Criada a migration, factory, comando `finansys:close-financial-day` e agendamento diario as 00:05 em `America/Sao_Paulo`.
- O primeiro teste de E6 cobre as duas visoes, repeticao do fechamento e preservacao do snapshot apos lancamento posterior.

E6 ainda parcial: falta tela/consulta do historico, origem reconstruida em backfill e avisos internos deduplicados. Concorrencia real em banco de producao e aceite visual continuam pendentes.

Validacao desta fatia: teste focal aprovado com 1 teste e 6 assercoes. Pint e regressao dos indicadores devem ser executados antes da integracao.

## E6.1 - historico consultavel

- Criada `FinancialEvaluationHistoryQuery` com filtro por visao e periodo, paginacao e isolamento pelo usuario autenticado.
- Criado endpoint `financial-evaluations.index` e pagina Inertia `FinancialEvaluations/Index.vue`.
- O historico mostra data, origem registrada/reconstruida, versao de regras, situacao, percentual, base e motivos.
- O dashboard ganhou acesso direto ao historico.
- Testes cobrem usuario estrangeiro, filtro de visao e periodo vazio.

E6 continua parcial: revisoes retroativas, backfill reconstruido e alertas internos deduplicados ainda faltam.

## E6.2 - recuperacao de dias sem fechamento

- Criado o comando `finansys:rebuild-financial-days` com intervalo `--from`/`--to` em Brasilia.
- O comando reconstrói as duas visoes somente para lacunas, preserva snapshots registrados e e seguro para repeticao.
- O intervalo e validado e limitado a no maximo 367 dias para evitar processamento acidentalmente amplo.
- Teste cobre preenchimento de dias ausentes, preservacao do resultado registrado e rejeicao de intervalos invalidos.

Limite registrado antes da revisao formal: reconstrucoes existentes ainda nao tinham cadeia de revisao. Esse limite foi tratado na entrega E6.3 abaixo.

Validacao desta fatia: 3 testes e 13 assercoes aprovados.

## E6.3 - revisao formal de snapshots

- Migration aditiva adiciona `revision` e `supersedes_id` aos fechamentos.
- Reconstrucao repetida preserva a primeira revisao; fechamento oficial posterior cria uma nova revisao vinculada, sem sobrescrever o resultado anterior.
- Replay do fechamento oficial retorna a mesma revisao e nao cria nova linha.
- O historico passa a exibir o numero da revisao junto da origem.

Validacao: 4 testes e 18 assercoes aprovados no teste focal. Alertas internos deduplicados ainda sao a proxima fatia de E6.

## E6.4 - alertas internos estruturados

- Criado `InternalAlert` com identidade por usuario, data de Brasilia e visao.
- Fechamentos oficiais adversos ou com deficit criam/atualizam um unico alerta; replay nao cria duplicata.
- Reconstrucoes nao disparam alertas.
- O alerta preserva a pior situacao, o primeiro deficit observado, a avaliacao aplicada e o payload numerico.
- Recuperacao de uma revisao oficial atualiza o mesmo alerta e registra `recovered_at`.

Escopo ainda parcial: falta consulta/tela dos alertas, mensagens editoriais e evidencia de concorrencia no banco de producao.

## E6.5 - consulta de alertas

- `worst_situation` agora acompanha pioras de faixa sem apagar o historico consolidado.
- Criadas `InternalAlertQuery`, rota `internal-alerts.index`, controller e filtros por visao, periodo, estado e deficit.
- A navegacao autenticada ganhou acesso a Avisos financeiros.
- A tela mostra situacao atual, pior situacao, deficit, recuperacao e motivos sem inferir saldo bancario.

Validacao focal pendente: executar testes HTTP, Pint e build apos esta fatia.

## E6.6 - deficit explicito e recuperacao

- O planejamento agora expõe `deficit` por visão, separado da `base` orçamentária.
- Alertas deixaram de inferir déficit a partir da base e passam a consumir o campo explícito.
- Recuperação oficial atualiza o mesmo alerta, limpa o déficit atual, preserva `deficit_seen` e `worst_situation`, e não duplica no replay.
- Teste cobre a transição `outside_plan` para `under_control` e a preservação do histórico.

Validacao: regressao focal com 20 testes e 244 assercoes aprovada; Pint aprovado.

## E6.7 - atualizacao imediata parcial

- Criada `RefreshCurrentInternalAlert`, que recalcula as visoes sem criar snapshot historico.
- `CreateManualLedgerEntry` atualiza alertas apos uma criacao real e ignora replay idempotente.
- Teste comprova que um deficit aparece antes do fechamento diario e que nenhuma avaliacao e criada nesse caminho.

Escopo parcial: transferencias, cartoes, reembolsos, estornos, exclusoes e restauracoes ainda precisam integrar a mesma acao apos suas operacoes completas.

Validacao: regressao com 56 testes e 500 assercoes aprovada; Pint aprovado.

## E6.8 - atualizacao imediata de reembolsos

- `CreateExpenseRefund` agora atualiza os alertas dentro da mesma transacao, depois da criacao do reembolso e auditorias.
- Replay de reembolso retorna antes do refresh.
- Teste comprova recuperacao do alerta existente, preservacao do mesmo ID e ausencia de snapshot historico.

Escopo ainda parcial: transferencias, cartoes, estornos, exclusoes e restauracoes permanecem para fatias especificas.

Validacao: regressao com 27 testes e 279 assercoes aprovada; Pint aprovado.

## E6.9 - atualizacao imediata de estornos

- `ReverseLedgerOperation` atualiza alertas apos estorno manual confirmado, fora da transacao financeira.
- Replay nao dispara refresh novamente.
- Estorno de transferencia permanece neutro e nao recalcula alertas, conforme o contrato.
- Rollback e as duas pernas de transferencia existentes continuam protegidos pelos testes atuais.

Escopo ainda parcial: pagamentos de cartao, transferencias (sem efeito semantico hoje), exclusoes e restauracoes ainda precisam de avaliacao propria.

Validacao: regressao com 32 testes e 298 assercoes aprovada; Pint aprovado.
