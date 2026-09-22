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
9. Correção retroativa de 80 → data correta, recálculo e auditoria sem sobrescrever versão anterior.
10. Login com dois dias pendentes → modal, abertura, confirmação individual/lote e zero explícito; dia sem confirmação excluído da média/folga fechada.
11. Duas abas com indicadores distintos e ajuda acessível por toque; dados incompletos visíveis.
12. Metas e score não implementados antes de aprovação separada.

## 15. Papéis e handoffs

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

## 20. Interface D1a escolhida e fronteira D2 (registro de decisão de 2026-09-21)

O Chefe escolheu preservar os nomes já implementados em `DailyMarginCalculator::calculate(string $month, array $days)`. **Entrada D1a:** competência `YYYY-MM`; dias informados de forma explícita e sem datas duplicadas; para dia `confirmed`, `date`, `status`, `budget` e `spent` são obrigatórios, sendo `budget` e `spent` strings decimais não negativas com até duas casas; para `pending`, somente `date` e `status`, sem montantes. `spent` significa **gasto elegível previamente selecionado**, não saída de caixa genérica. **Saída D1a:** `month`, `days` (`date`, `status`, `margin` decimal ou `null`), `confirmed_days`, `pending_days`, `gross_savings`, `gross_excess` e `net_margin`; dinheiro sempre em strings decimais com duas casas. Dia pendente não contribui para totais. A saída preserva a ordem de entrada e não enumera dias omitidos. `confirmed_days=0` com totais `0.00` significa ausência de dados confirmados, não economia comprovada.

**Fronteira técnica proposta para análise, ainda NÃO aprovada para implementação D2:** um adaptador futuro deve ordenar as datas, fornecer o orçamento voluntário **historicamente aplicável** a cada dia confirmado e selecionar despesas elegíveis sem dupla contagem. A `FinancialPlanningOverviewQuery` V1 calcula `daily.amount` como alocação derivada a partir da margem livre e dos dias restantes; não é o orçamento voluntário fixado da V2. `MonthlyFinancialSetting` não possui campo de orçamento diário voluntário. Snapshots de `CloseFinancialEvaluation` registram situação corrente/projetada, não check-in explícito nem orçamento voluntário diário. Portanto, não inferir `budget` a partir desses valores, nem converter automaticamente fechamento da V1 ou ausência de lançamento em `confirmed`.

**Gate D2 ainda aberto:** definir persistência/versionamento do orçamento e vigência de mudanças; competência temporal e exclusividade entre consumo, obrigação, pagamento de fatura, antecipação, fixos, extraordinários, reembolso e estorno; confirmação explícita e correção retroativa; autorização e isolamento por usuário; testes Feature MySQL A/B, cartão/parcelas/fatura, centavos, dia pendente/zero confirmado e regressão V1. Não implementar D2, migrations, UI ou deploy apenas por esta decisão de nomes. OFX permanece módulo separado.