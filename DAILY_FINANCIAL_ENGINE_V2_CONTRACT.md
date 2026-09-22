# FinanSys V2 — Contrato unificado do Motor Financeiro Diário

> Estado em 2026-09-21: **contrato conceitual de produto, com decisões do usuário aprovadas; contrato matemático/técnico e implementação ainda sujeitos à revisão dos agentes**. Não declarar a V2 implementada ou publicada. Até aprovação técnica, `FINANCIAL_PLANNING_CONTRACT.md` permanece o contrato vigente da V1. Este é o **único documento de referência da V2**, consolidando a proposta original e as decisões posteriores; não criar documentos paralelos de decisões. Em conflito, as decisões explícitas mais recentes deste documento prevalecem sobre alternativas da proposta, sem revogar invariantes da V1.

## 1. Objetivo e contexto

Evoluir o planejamento mensal existente (atual/projetado, fixos, variáveis, essenciais, proteção, previsões, cartões e histórico) de uma única verba/média diária para um motor determinístico de decisão pessoal. Distinguir **quanto minha vida custa**, **quanto consumi**, **quanto escolhi gastar**, **quanto posso gastar** e **quanto sobrou em relação ao plano**. Uma receita de R$ 5.000, fixos de R$ 2.000 e proteção de R$ 1.000 deixa R$ 2.000 de verba variável: R$ 66,66/dia em 30 dias é capacidade teórica, enquanto R$ 48/dia de gasto observado é comportamento; não apresentar como sinônimos.

## 2. Glossário e indicadores

| Indicador | Pergunta simples | Regra/estado |
| --- | --- | --- |
| Custo estrutural diário | Quanto custa manter minha vida por dia? | Equivalência dos compromissos elegíveis (moradia, internet, financiamento, assinaturas); não cria lançamentos diários. Base temporal exata pendente de Inv. |
| Gasto variável realizado | Quanto gastei no cotidiano? | Despesas variáveis elegíveis efetivamente realizadas, por dia, categoria, mês e média de N dias; neutralizar transferências, pagamento de fatura e correções conforme V1. |
| Custo total equivalente | Quanto custa minha vida incluindo hábitos? | Estrutural diário + variável normalizado; normalização e período dependem de validação matemática. |
| Orçamento diário planejado | Quanto decidi gastar? | Referência voluntária, não limite transacional nem saldo bancário. Preservar orçamento aplicável ao dia no histórico. |
| Capacidade diária | Quanto meus recursos e compromissos comportam? | Indicador calculado, separado do orçamento; pode mudar com fatos novos sem alterar automaticamente a escolha pessoal. |
| Folga diária | Quanto fiquei acima/abaixo do plano? | Orçamento diário aplicável − gasto elegível realizado; pode ser positiva ou negativa. |
| Folga líquida acumulada | Como está meu resultado frente ao plano? | Soma das folgas de dias elegíveis encerrados; não é saldo bancário. |
| Economia/excesso brutos | Quanto economizei e quanto ultrapassei? | Somar separadamente folgas positivas e valores absolutos das negativas; líquido = economia bruta − excesso bruto. |
| Projeção de fechamento | Como o mês pode terminar? | Separar fatos confirmados de previsões, explicitar datas e premissas. |

Indicadores derivados nunca criam receita, despesa, saldo ou aporte. Não confundir déficit de orçamento com insuficiência de caixa. Média diária positiva usa regras de arredondamento da V1, sem `float`.

## 3. Decisões aprovadas — folga, excesso e capacidade

**Política A aprovada:** acumular folga sem redistribuição automática. Ex.: orçamento R$ 90, gasto R$ 80 → folga +R$ 10; orçamento de amanhã permanece R$ 90. Gasto R$ 110 → folga −R$ 20, podendo tornar o acumulado mensal negativo. **Combinação A + C aprovada para excessos:** economia bruta, excesso bruto e líquido aparecem separadamente. Ex.: dias com gastos R$ 60, R$ 70 e R$ 200 frente a R$ 90/dia → economia R$ 50, excesso R$ 110, líquido −R$ 60; dia seguinte permanece R$ 90. A capacidade e a projeção **podem** ser recalculadas como informação, nunca como redução compulsória do orçamento. O excesso de um dia não prova sozinho déficit mensal. Não transformar folga em receita, novo saldo, verba adicional ou aporte fictício. Alteração voluntária do orçamento exige regra/configuração explícita e motivo rastreável.

**Alternativas não aprovadas para o padrão:** redistribuir automaticamente a folga nos dias restantes (antiga política B) e direcionar automaticamente folga a proteção/meta (antiga política C). São hipóteses futuras, dependentes de aprovação e contrato de alocação; reservar não equivale a transferência bancária.

## 4. Despesas fixas — alternativa C aprovada

Separar **custo diário equivalente** de **pagamento real na data do vencimento**. Parcela da moto de R$ 600 num mês de 30 dias pode ser exibida como custo estrutural equivalente de R$ 20/dia; os R$ 600 vencem e são pagos na data real. O custo diário é uma leitura derivada, não 30 despesas fictícias; a liquidação não conta novamente o compromisso. Preservar reserva por vencimento e distinção entre orçamento e caixa. Base de dias e tratamento de meses/alterações pendentes de revisão.

## 5. Cartões — alternativa C aprovada

Separar **compra, compromisso e pagamento**. Compra de R$ 1.200 em 6 × R$ 200: valor total assumido acessível na data da compra, R$ 200 comprometem o orçamento de cada mês de vencimento, e pagamento da fatura liquida obrigação sem nova despesa de consumo. Não lançar R$ 1.200 integralmente no orçamento do mês da compra *e* novamente as parcelas. Respeitar contrato vigente para parcelas, antecipação com desconto, pagamentos parciais, residual, juros, multas, estornos e reembolsos; juros/multas confirmados são despesas próprias. Despesa reconhecida, obrigação e saída de caixa são visões diferentes do mesmo fluxo, não parcelas somáveis sem critério.

## 6. Extraordinários — B + C aprovadas

Permitir classificação manual como comum ou extraordinário. Separar extraordinários da média comportamental cotidiana e não extrapolá-los como recorrentes; preservar integralmente seu impacto no planejamento, obrigações e caixa. Conserto da moto de R$ 500 não significa hábito diário de R$ 500, mas reduz recursos conforme sua natureza e data. Definir critérios de elegibilidade e evitar diagnosticar categoria por compra concentrada no início do mês. Essencial e fixo são características independentes; classificação extraordinária não neutraliza a despesa.

## 7. Recebimentos — alternativa C aprovada

Separar **disponível hoje** (somente recursos confirmados elegíveis) de **projetado** (confirmados + previsões elegíveis por data, descontados compromissos conforme V1). Ex.: R$ 300 disponíveis e salário de R$ 2.000 previsto: mostrar R$ 300 hoje e cenário simplificado de R$ 2.300 após recebimento, sem tratar previsão como dinheiro já recebido. Estados de UX: previsto, recebido, atrasado, não recebido; mapear esses estados para os estados efetivos do modelo V1 antes de codificar. Ao não receber, retirar o valor pendente da projeção e preservar histórico; ao atrasar, sinalizar pendência e data, sem presumir recebimento. Recebimento parcial: R$ 2.000 previstos, R$ 600 recebidos → R$ 600 na visão atual e R$ 1.400 pendentes na projeção, sem duplicação; respeitar remarcação, encerramento, reversão e diferença de valor do contrato V1. Mostrar cenários com/sem recebimento e com atraso na análise avançada; orçamento voluntário não muda automaticamente.

## 8. Correções retroativas — alternativa C aprovada

Lançamento esquecido de R$ 80 há cinco dias pertence à data correta. Recalcular apenas períodos/indicadores afetados, preservar snapshot/avaliação anterior, instante, motivo, versão de regra e proveniência; não alterar silenciosamente o orçamento originalmente aplicável ao dia. Histórico deve distinguir valor apresentado no fechamento e valor corrigido. Definir persistência versus reconstrução, idempotência, concorrência e retenção antes da implementação.

## 9. Check-in financeiro — alternativa C aprovada

**Login não é check-in; ausência de lançamento não é gasto zero confirmado.** Ao entrar com dias anteriores pendentes, apresentar modal com data, gastos registrados e estado; permitir abrir cada dia, adicionar despesa esquecida, confirmar registros ou confirmar ausência de outras despesas. Permitir confirmação individual e em lote, com seleção explícita e confirmação antes de marcar vários dias sem gastos. Após validação, recalcular somente indicadores/períodos afetados e retornar ao dashboard atualizado. Não bloquear acesso ao dashboard: sinalizar dados incompletos. Dia confirmado sem variável tem variável R$ 0 e ainda pode ter custo estrutural. Correção posterior de dia confirmado mantém auditoria e recalcula. Notificações de pendência devem ser úteis e não repetitivas. Definir semântica de médias/folga para dias pendentes: **não imputar zero validado**. Fechamento no fuso `America/Sao_Paulo`, recuperação de dias não processados, permissões e deduplicação exigem contrato técnico.

## 10. Dashboard e UX — decisão aprovada

Duas abas no mesmo dashboard, quando compatível com arquitetura: **Visão geral** com situação do mês, dinheiro disponível, orçamento, gastos e alertas essenciais; **Análise avançada** com histórico, categorias, orçamento × realizado, economia/excesso/líquido, capacidade, cenários, tendências e explicações. Evitar cards redundantes e poluição de números/porcentagens. Agrupar por títulos curtos como «Meu dinheiro», «Meus gastos», «Meu planejamento». Cada indicador deve responder pergunta diferente, ter nome simples, unidade, período, estado atual/projetado, atualização e ícone de ajuda acessível por mouse **e toque**; tooltip curto explica o que é e por que aparece, com exemplo se necessário. Texto/ícone acompanham cor; detalhes expansíveis no mobile. Exibir incerteza/dados incompletos, não inventar valores. Gráfico principal: orçamento diário aplicável × gasto diário realizado, capacidade opcional e diferença; gráfico secundário: folga líquida acumulada dos dias elegíveis encerrados. Tendência/média móvel é apoio, não substitui indicadores oficiais. Mudança de orçamento deve ser explicável.

## 11. Metas e proteção — proposta futura, ainda não aprovada para implementação

Meta de R$ 3.000 com R$ 600 reservado e 120 dias restantes: R$ 2.400 / 120 = R$ 20/dia teóricos. Capacidade de R$ 110 e reserva sugerida de R$ 20 podem informar referência de R$ 90, **sem criar aporte**. Folga de R$ 32 pode melhorar projeção da meta, mas reserva/aporte só mediante política explícita e operação real quando aplicável. Estimar prazo, adiantamento/atraso e impacto extraordinário apenas após contrato. Aumento de receita pode elevar capacidade sem elevar orçamento escolhido; percentuais futuros devem explicitar base (consumo, proteção, metas/investimento ou tolerância), nunca «porcentagem de gasto» ambígua. Patrimônio anterior não vira renda mensal automaticamente.

## 12. Categorias, comportamento e pontuação — propostas condicionadas

Categorias livres (alimentação, bebidas, combustível, transporte, viagem, lazer, assinaturas etc.); médias por categoria, participação, diferença contra orçamento, tendências 7/30/90 dias, extraordinário versus recorrente e concentração fora do padrão. Período de média e critérios de dados suficientes pendentes. Possível índice de eficiência 0–100 **não entra no primeiro incremento** sem nova aprovação: não premiar privação de essenciais; considerar aderência, compromissos, proteção, metas, estabilidade, relação fixos/receita, duração do déficit e anomalias. Inv deve validar pesos, casos extremos, explicabilidade e dados insuficientes; Lia valida linguagem. Faixas existentes da V1 não são automaticamente o score V2.

## 13. Estados, matemática e invariantes

Atual = fatos confirmados elegíveis; projetado = atual + previsões/obrigações elegíveis ainda não realizados; histórico fechado = avaliação preservada com revisão identificável. Distinguir fatos (receita, despesa, compromisso, pagamento, estorno, reembolso, previsão, aporte real) de indicadores derivados. Conjuntos de seleção mutuamente exclusivos; neutralizar transferências próprias; pagamento de fatura não duplica compra; estorno/reembolso corrige fato relacionado. Não usar `float`; regras monetárias determinísticas com decimal exato, arredondamento explícito, centavos preservados e mesmo resultado para mesmas entradas/versão. Não afirmar caixa negativo apenas por orçamento deficitário. Não gerar diagnósticos fortes com poucos dias ou dados pendentes. Cada mudança de orçamento deve ter origem explicável. Proteger isolamento por `user_id`, privacidade, trilha de auditoria e concorrência. Mês-calendário, dias corridos, fechamento em Brasília e demais regras de calendário seguem contrato V1 até revisão formal.

## 14. Cenários de aceite mínimos

1. Orçamento 90, gasto 80 → folga +10, nenhuma receita ou aporte criado, amanhã 90.
2. Orçamento 90, gasto 110 → folga −20, amanhã 90, projeção informativa recalculável.
3. Gastos 60/70/200 com orçamento 90/dia → economia bruta 50, excesso 110, líquido −60.
4. Nova renda confirmada eleva capacidade 100→150/dia; orçamento voluntário permanece 80/dia.
5. Parcela fixa 600/30 → custo equivalente 20/dia, pagamento 600 na data real, sem segunda despesa.
6. Cartão 1.200 em 6×200 → compra total acessível, 200 por mês comprometido, fatura liquida sem duplicar.
7. Extraordinário 500 → impacta planejamento e caixa, não extrapola média cotidiana.
8. Previsto 2.000, recebido 600 → atual +600, residual previsto 1.400; cancelamento/não recebimento retira residual da projeção sem apagar 600.
9. Correção retroativa de 80 → data correta, recálculo e histórico anterior preservados.
10. Dia sem login/lançamento → pendente, não zero confirmado; check-in individual ou lote confirma; dia sem variável ainda tem estrutural.
11. Meta 2.400/120 → necessidade teórica 20/dia sem aporte fictício.
12. Transferência, estorno, reembolso, antecipação, pagamento parcial e dívida entre meses seguem invariantes V1; testes de centavos, fim de mês, datas, usuário e execução duplicada.

## 15. Agentes e responsabilidades

- **Inv:** matemática, fórmulas, denominadores, não duplicidade, folga, metas/score, cenários de fronteira.
- **Atlas:** fronteiras fato/indicador, seleção, calculadoras puras, contratos de entrada/saída, versionamento e snapshots.
- **Lia:** semântica, UX, nomenclatura, estados incompletos, ajuda contextual e configurações.
- **Bento:** matriz de aceite independente, centavos, calendário, retroatividade, histórico, gráficos e regressão V1.
- **Íris:** isolamento `user_id`, snapshots, privacidade e integrações.
- **Nexo:** fechamento diário, recuperação, idempotência e concorrência.
- **Nilo:** implementação somente após decisões/contratos técnicos aprovados.

Consultar instruções, contratos e agentes existentes no repositório antes de modificar código. Alterações de implementação devem manter rastreabilidade de arquivos e datas no fluxo já adotado pelo projeto, sem criar documentos concorrentes para as decisões da V2.

## 16. Execução incremental e critérios de passagem

**D0 — contrato conceitual:** decisões acima aprovadas; faltam fórmulas, entradas/saídas, elegibilidade e revisão dos agentes. **D1 — calculadora pura:** estrutural, variável, capacidade, orçamento, folga, acumulado e projeções, sem UI/efeitos colaterais, com testes. **D2 — seleção de dados:** conjuntos exclusivos, cartões, previsões, fixos, categorias, estornos e reembolsos. **D3 — histórico/check-in:** snapshots, correções, fechamento, lote, idempotência. **D4 — dashboard:** primeiro visão geral, depois análise avançada, gráficos, explicações e alertas. **D5 — metas:** somente após política específica aprovada. **D6 — score opcional:** somente após validação e aprovação. **D7 — consolidação:** revisão multidisciplinar, suíte completa, regressão V1, aceite desktop/mobile, release e smoke real. Ordem técnica D1/D2 pode ser refinada por Atlas, sem implementar cálculo sobre seleção ambígua.

## 17. Questões abertas obrigatórias antes do código

1. Base de dias para custo estrutural (mês-calendário, restantes ou outra).
2. Momento em que orçamento diário é fixado, e efeito de mutação/receita no meio do dia.
3. Elegibilidade do gasto por data, cartão, fixos, estornos, transferências e extraordinários; distinguir consumo, obrigação e caixa.
4. Como dias não confirmados afetam média, folga e projeção; abertura/fechamento de check-in, lote e correção posterior.
5. Fórmula da capacidade atual/projetada por data, caixa disponível, compromissos e recebimentos parciais.
6. Mapeamento «atrasado/não recebido» para estados existentes, cancelamento, reversão e remarcação.
7. Fórmula e normalização do custo total; janelas 7/30/90 e mês com poucos dados.
8. Persistência versus reconstrução, versionamento, auditoria, retenção, jobs perdidos, idempotência e concorrência.
9. Metas: proteção/reserva versus recomendação, patrimônio anterior, política de aporte e escopo do MVP.
10. Score: pesos, dados insuficientes, testes extremos e aprovação explícita antes de implementar.
11. Conflitos específicos com o contrato V1, matriz de regressão, limites de arredondamento e critérios de release.

## 18. Fora do escopo e futura IA

Fora deste incremento: módulo Business/contabilidade empresarial, estoque, WhatsApp, importação OFX, importação/exportação JSON geral e cálculos financeiros livres por IA. **OFX é um módulo isolado**; eventuais correções próprias não devem bloquear a V2. Futuro assistente pode consultar e explicar métricas, resumir tendências e executar ações sob contratos explícitos, **nunca inventar saldo, verba, média, projeção ou score**; números vêm do motor determinístico versionado. Ex.: orçamento 74, gasto hoje 32, restante de referência 42 e projeção do mês 286 abaixo do orçamento atual são dados do motor, não geração livre.

## 19. Gate para contrato técnico e implementação

Inv aprova matemática/invariantes; Atlas aprova arquitetura e contratos; Lia aprova semântica e UX; Bento possui cenários independentes; Íris e Nexo revisam isolamento e confiabilidade; conflitos com V1 são enumerados; questões abertas resolvidas ou explicitamente adiadas; escopo do primeiro incremento definido. **Este documento é o contrato conceitual consolidado, não evidência de implementação nem autorização para declarar a V2 pronta.**

## 20. Atualização de escopo e auditoria coordenada pela Maia — 2026-09-22

**Natureza desta seção:** consolidação dos esclarecimentos do Chefe e achados de leitura dos contratos e caminhos relevantes de código V1/V2. A equipe em `AGENTS.md` é conceitual: consultar as skills da Maia, Lia, Inv, Atlas, Íris, Nexo e Bento **não equivale a executar agentes independentes nem a obter seus pareceres**. A revisão até aqui foi dirigida, não exaustiva de todos os arquivos, permissões, endpoints, execução simultânea ou produção. Nenhuma proposta abaixo constitui aprovação automática de fórmulas, implementação de metas, migration, merge ou deploy. Preservar §§1–19 e o contrato V1; a seção esclarece a integração sem revogar as decisões anteriores.

### 20.1 Intenção de produto esclarecida pelo Chefe

O motor deve transformar receitas confirmadas, recebimentos previstos/fixos, despesas fixas/previstas, compras comuns e extraordinárias, parcelas de cartão, financiamento e dívidas anteriores em uma **leitura diária coerente do custo de vida e do poder de gasto ao longo do mês**, para planejar o fechamento sem ultrapassar recursos elegíveis. Todas essas classes afetam a leitura que lhes corresponde; excluir um extraordinário da média habitual **não o exclui** dos compromissos ou da capacidade. O termo coloquial «gasto diário» não identifica sozinho se o usuário está vendo consumo realizado, custo equivalente, compromisso mensal dividido pelos dias ou capacidade restante. Cada indicador deve indicar base, período e estado atual/projetado.

Exemplo de aceite de comportamento: conserto extraordinário da moto de R$ 300 em dez parcelas de R$ 30. O valor total assumido é R$ 300; uma parcela de R$ 30 compromete cada mês de vencimento (respeitando antecipações, descontos, estornos e parcelas anteriores pendentes); em mês de trinta dias representa equivalência informativa de R$ 1/dia. A compra integral, a parcela e o pagamento da fatura não são três custos acumuláveis. A despesa extraordinária **não** se converte em hábito recorrente de R$ 300/dia. Um real a mais por dia por trinta dias representa R$ 30 adicionais no mês, e R$ 300 adicionais correspondem a R$ 10/dia no mesmo intervalo.

### 20.2 Fronteiras obrigatórias e invariantes já compatíveis com V1

| Visão | Entra | Não pode ser confundido com |
| --- | --- | --- |
| Recursos confirmados / caixa | Entradas efetivamente recebidas, saldos e saídas bancárias vinculadas, conforme fontes elegíveis e contrato V1 | Receita prevista ou folga orçamentária criada como dinheiro |
| Recursos projetados | Confirmados mais residual de previsões elegíveis, inclusive salário/recebimento fixo futuro, nas datas e cenários próprios | Disponível hoje; recebimento parcial contado duas vezes |
| Compromissos do mês | Fixos e boletos previstos elegíveis, parcelas por vencimento, extraordinários parcelados, encargos confirmados, resíduos anteriores e antecipações atribuíveis, com pago e pendente mutuamente exclusivos | Somar compra integral, parcela, liquidação e adiantamento como quatro despesas |
| Custo diário equivalente | Alocação informativa do compromisso mensal elegível pela base de dias definida para o indicador | Lançamento diário fictício, débito repetido ou gasto habitual observado |
| Consumo variável / folga | Fatos elegíveis efetivamente atribuídos ao dia, com classificação, competência, estornos e cobertura verificáveis | Somar novamente obrigação já reservada; incluir previsões não realizadas como consumo confirmado |
| Capacidade diária atual/projetada | Recursos elegíveis da visão menos compromissos e consumo pertinente uma única vez; divisor e data-base explícitos | Orçamento voluntário, garantia de caixa ou autorização para gastar |
| Metas futuras | Necessidade teórica diária, folga potencial e aportes efetivamente confirmados como fatos distintos | Tratar folga como transferência, reserva ou patrimônio sem operação real |

**Proposta de formalização, ainda não aprovada:** apresentar compromisso mensal equivalente e gasto variável efetivo em indicadores distintos, e calcular poder de gasto/capacidade após reservar uma única vez os compromissos. Se o orçamento diário voluntário representa **verba variável já livre de fixos e parcelas**, não subtrair o custo equivalente novamente ao computar a folga do check-in. Se ele representa um teto **total incluindo compromissos**, o denominador e a fórmula de folga precisam de outro contrato explícito antes do uso. A escolha sobre a base do orçamento voluntário permanece com o Chefe; não alimentar `eligible_spent` por soma de todas as visões do D2.

### 20.3 Achados de código com escopo delimitado — não equivalem a sistema integral auditado

- **V1 / `FinancialPlanningOverviewQuery` em `develop`:** consulta receita confirmada e residual de `ReceiptForecast`, despesas lançadas, parcelas, encargos e antecipações, calculando margem e valor diário a partir dos dias restantes. O `fixed` observado na consulta é composto por despesas fixas lançadas; a leitura revisada **não comprovou** seleção completa de todos os boletos fixos futuros. Exigir rastreabilidade cadastro → previsão/obrigação → liquidação → projeção antes de declarar cobertura integral. `RecalculateReceiptForecast` vincula recebimento parcial à pendência; auditar remarcação, cancelamento, estorno e mudanças de mês.
- **D2 / `DailyFinancialFactsQuery` no PR #79:** envelopes separados de `ledger`, `purchase`, `due`, `advance`, `reversal` e `settlement`; `eligible_spent=null`, `as_of_unsupported_views=['due']`. É correto não publicar soma provisória. `DailyCardDueCommitmentQuery` soma somente `Ordinary` em `ordinary_due_total`; extraordinários e fixos continuam compromissos financeiros conforme §§4–6, portanto **esse campo não é o total mensal ou diário de todas as obrigações**. `DailyCardAdvanceImpactQuery` também retorna somente parcelas ordinárias; créditos aplicados (`ApplyCardCredit` na V1), reembolsos e demais ajustes ainda demandam matriz de integração.
- **D2 / integridade:** `DailyCardPaymentSettlementQuery` confere vínculo de pagamento/lançamento e chama `DailyCardPaymentAllocationIntegrityQuery`, que verifica a soma das alocações e edição posterior. A soma correta **não comprova** titularidade, mesmo cartão/obrigação, saldo original ou ausência de sobrealocação de cada destino. Exigir validação por `user_id` em todas as relações e testes de destinos adulterados, incluindo MySQL.
- **D1/D3:** PR #77 contém calculadora pura de folga; PR #79 mantém orçamento versionado e um `DailyConfirmedDayInput` que recebe gasto elegível externo. Não há integração reconciliada para confirmar um dia, nem check-in persistido ou histórico de vencimentos completamente reconstruível. CI verde valida somente cenários exercitados, não esse contrato integrado nem produção.

### 20.4 Handoffs da Maia e critérios de aceite antes de alterar fórmulas

1. **Lia + Inv (produto/matemática consultiva):** apresentar ao Chefe exemplos lado a lado de orçamento variável versus teto total; definir base de dias do custo equivalente (mês-calendário), divisor da capacidade restante, fixo previsto sem pagamento, extraordinário à vista/parcelado, renda fixa prevista e meta versus financiamento. Somente a decisão expressa do Chefe aprova a regra nova.
2. **Atlas (arquitetura/seleção):** mapear todas as fontes V1 de fixos, boletos previstos, receitas, cartões, créditos, antecipação, encargos, resíduos e reembolsos por identificador de origem, competência, estado e efeito exclusivo em cada visão. Delimitar mudanças mínimas/aditivas na V2 e contrato de `eligible_spent`; não reescrever a V1 por iniciativa própria.
3. **Íris + Nexo (segurança e consistência):** conferir propriedade de cada vínculo de alocação e destinatário; idempotência de previsão→realizado, versão de orçamento no check-in, concorrência, auditoria de correções e histórico com estados não reconstruíveis.
4. **Bento (evidência independente):** testar cenários de R$ 300/10×30, fixo previsto pago/parcial/atrasado, salário previsto/recebido parcialmente, antecipação/desconto, juros, crédito/estorno, despesa retroativa, extraordinário sem recorrência, mês de 28/29/30/31 dias, centavos e usuário A/B; demonstrar que mover pendente→pago ou previsto→realizado não duplica impacto. Diferenciar suíte PHP geral de lista limitada no job MySQL e teste manual no navegador.
5. **Nilo (implementação):** atuar somente após contrato técnico revisado e decisões pendentes resolvidas; commits pequenos com cobertura, sem migração destrutiva, merge ou deploy presumido.

**Gates e rastreabilidade:** issue #78 registra a auditoria e os bloqueios; PR #77 (D1) e PR #79 (D2 draft) não são versões publicadas. Manter `FINANCIAL_PLANNING_CONTRACT.md` intacto como contrato V1 e preservar propostas históricas enquanto houver referências a elas; não excluir documentos por suposta obsolescência sem mapear dependências e obter autorização. OFX é módulo isolado, não importação XML, e não deve ser alterado por este recorte. Nenhum agente autônomo foi executado nem parecer independente obtido nesta atualização.