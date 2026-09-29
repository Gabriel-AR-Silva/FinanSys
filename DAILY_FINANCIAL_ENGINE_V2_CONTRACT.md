# FinanSys V2 — Contrato unificado do Motor Financeiro Diário

> Estado consolidado em 2026-09-28. Este é o documento canônico das decisões financeiras da V2. Ele define semântica, invariantes, UX financeira, critérios de aceite e fronteiras com V3; não substitui regras operacionais do repositório. Antes de qualquer implementação, ler `AGENTS.md`, `.ai/rules/index.md` e as regras específicas apontadas pelo índice. Release/build seguem exclusivamente o fluxo operacional ali definido.

## 1. Objetivo e princípio de segurança

O FinanSys deve responder perguntas financeiras simples sem misturar conceitos diferentes. Nenhum indicador pode fazer o usuário parecer mais rico, mais seguro ou com mais dinheiro livre do que realmente está.

Anti-padrão central: **falsa disponibilidade / falsa tranquilidade**. Uma obrigação conhecida não pode desaparecer de uma visão apenas para o dashboard parecer saudável.

Todo card, gráfico, alerta ou progresso financeiro deve pertencer explicitamente a uma destas naturezas:

1. **Caixa:** dinheiro existente agora.
2. **Consumo:** gasto efetivamente realizado.
3. **Compromisso:** obrigação futura já conhecida.
4. **Projeção:** cenário futuro dependente de fatos ainda não realizados.
5. **Capacidade:** quanto pode ser gasto de forma sustentável depois de considerar recursos e compromissos.
6. **Patrimônio:** ativos menos passivos.

Nenhum componente pode misturar silenciosamente duas naturezas. Se duas telas responderem a mesma pergunta financeira, seus valores devem reconciliar ou explicar claramente a diferença.

## 2. Indicadores e perguntas simples

| Indicador | Pergunta simples | Regra |
| --- | --- | --- |
| Saldo geral | Quanto dinheiro existe agora? | Caixa atual; dívida de cartão não reduz literalmente saldo bancário antes da liquidação. |
| Disponível agora / caixa após compromissos | Quanto do dinheiro atual sobra depois das obrigações conhecidas que precisam ser financiadas por ele? | Conservador; receita prevista não entra antes de recebida. |
| Gasto realizado | Quanto realmente gastei? | Consumo reconhecido, incluindo compras no cartão na data da compra. |
| Comprometido | Quanto ainda tenho de obrigação conhecida pela frente? | Faturas, parcelas, contas e demais compromissos elegíveis, sem duplicar consumo. |
| Consolidado | Qual meu impacto financeiro conhecido? | Combina realizado e comprometido com conjuntos exclusivos e sem dupla contagem. |
| Ritmo realizado | Quanto por dia eu estou gastando? | Média de consumo elegível realizado. |
| Ritmo sustentável | Quanto por dia eu posso continuar gastando considerando minhas contas? | Margem realmente livre ÷ dias restantes, segundo regras determinísticas aprovadas. |
| Orçamento diário | Quanto eu decidi gastar? | Escolha do usuário; capacidade não altera orçamento automaticamente. |
| Projeção | Como o período pode terminar? | Fatos confirmados separados de previsões e premissas. |
| Patrimônio líquido | Quanto possuo depois das dívidas? | Ativos elegíveis menos passivos; não equivale a dinheiro disponível. |

Quando a base de dados necessária não for suficiente, mostrar **“—” / “Sem dados”**, nunca um zero que sugira um resultado financeiro inexistente. Exemplo: taxa de poupança sem base de receita suficiente não é 0%.

## 3. Realizado, Comprometido e Consolidado

As análises financeiras devem poder distinguir três leituras, preferencialmente dentro da própria seção e não como navegação excessiva:

- **Realizado:** consumo efetivamente ocorrido.
- **Comprometido:** obrigações futuras conhecidas.
- **Consolidado:** exposição financeira conhecida combinando os dois sem dupla contagem.

Categorias podem explicar, por exemplo: `R$ 350 consumidos | R$ 180 ainda comprometidos | R$ 530 de impacto total conhecido`.

A implementação deve manter conjuntos de seleção explícitos. Pagamento, liquidação, transferência própria, estorno e reembolso não podem reaparecer como novo consumo quando já representam outro estágio do mesmo fluxo.

## 4. Cartões — compra, obrigação e liquidação

Compra no cartão é **consumo na data da compra**. Ela alimenta categorias, gasto realizado, média diária, maior despesa, contagem de movimentos, atividade recente e gráficos de consumo.

A fatura pendente é **compromisso/passivo futuro**, não uma segunda despesa. O pagamento da fatura é **liquidação/saída de caixa**, não novo consumo. Uma compra de R$ 100 seguida do pagamento de R$ 100 continua representando R$ 100 de consumo, não R$ 200.

`Cartão` é meio de pagamento/passivo, não categoria agregadora da fatura. As categorias originais das compras devem ser preservadas.

Se um indicador mede consumo, não pode exibir `Despesas = R$ 0` quando há compras de cartão elegíveis. Se mede apenas saída efetiva de caixa, deve ser rotulado claramente como **Saídas de caixa**.

### Ciclo de fechamento

Com fechamento no dia 5 e vencimento no dia 12, o dia de fechamento é o primeiro dia do novo ciclo:

- compra em 04/09 → vencimento 12/09;
- compra em 05/09 → vencimento 12/10;
- compra em 06/09 → vencimento 12/10.

Testes obrigatórios: dia anterior, dia do fechamento, dia posterior, meses curtos, dia 31 e vencimento numericamente anterior ao fechamento.

## 5. Contas, parcelas e recebimentos previstos

Contas fixas, parcelas e `ExpenseCommitments` reduzem capacidade/disponibilidade financeira conforme sua natureza antes da saída de caixa, sem serem recriados como consumo fictício diário.

Receita esperada é **projeção**, nunca disponibilidade atual. R$ 2.000 previstos para amanhã não podem aumentar o valor que o usuário pode gastar hoje. Recebimentos parciais separam parte confirmada da parte ainda prevista.

O calendário financeiro deve considerar **valor + data**, permitindo visualizar salário, fatura, parcelas e contas na ordem em que afetam o caixa.

## 6. Ritmo financeiro e capacidade

O sistema deve mostrar separadamente:

- **Ritmo realizado:** média do consumo real por dia.
- **Ritmo sustentável:** quanto pode ser gasto por dia daqui para frente depois de compromissos, essenciais e proteções elegíveis.

A pergunta de produto é: **“Quanto por dia eu estou gastando, tendo em vista as minhas contas e o que já está previsto?”**

Comparar os dois e explicar a consequência. Exemplo: `Seu ritmo está R$ 25/dia acima do sustentável.`

Capacidade é análise, não comando. Ela pode mudar com fatos financeiros novos, mas **não altera automaticamente o orçamento definido pelo usuário**.

## 7. Orçamento, folga e reservas

Folga não é receita, saldo novo nem aporte automático. Orçamento diário é referência voluntária e deve preservar o valor aplicável historicamente ao dia.

Reservar R$ 500 para uma meta reduz a disponibilidade para gastar quando a regra de proteção assim determinar, mas não é consumo e não reduz patrimônio líquido apenas por mover/alocar dinheiro entre posições próprias.

Metas e reservas devem usar valores reais vinculados quando houver fonte de verdade. Não criar saldo reservado fictício apenas para completar progresso visual.

## 8. Check-in financeiro — obrigatório para fechamento da V2

O domínio de check-in já existe no backend, mas a V2 não pode ser declarada completa sem uma entrada de UX clara e utilizável.

**Login não é check-in; ausência de lançamento não é gasto zero confirmado.**

O check-in registra um snapshot diário histórico: orçamento aplicável, gasto elegível, margem e revisão. Alterações posteriores não devem apagar silenciosamente o que havia sido confirmado; correções usam revisão/auditoria.

A UX deve permitir confirmar um dia, corrigir lançamento esquecido e tratar pendências sem bloquear o dashboard. Confirmação em lote exige seleção e confirmação explícitas. Dias pendentes não podem ser imputados como zero validado.

Esse histórico é base para tendências, ritmo, consistência e gráficos de evolução.

## 9. Dashboard, gráficos e explicabilidade

Cada indicador importante deve responder uma pergunta diferente, ter período, natureza financeira e composição explicáveis.

Ao tocar/clicar em um número importante, o usuário deve conseguir entender de onde ele veio. Alertas devem explicar consequência, não apenas usar cor.

### Gráficos

Usar **gráficos de linha** quando a pergunta for evolução/aumento/queda no tempo, especialmente:

- gastos ao longo dos dias;
- ritmo realizado × ritmo sustentável;
- saldo/disponibilidade ao longo do tempo;
- evolução patrimonial quando aplicável.

Projeções devem ser visualmente distinguíveis de fatos realizados. Barras são adequadas para comparação de categorias; composição/distribuição deve usar visual apropriado sem poluição.

A auditoria de indicadores inclui cards, gráficos, barras de progresso, distribuições, médias, projeções, comparações e alertas — não apenas cards numéricos.

## 10. Ativos e Investimentos — núcleo enxuto da V2

**Ativos/Investimentos é separado de Patrimônio.** Ativos registra as posições; Patrimônio agrega a visão superior.

A V2 deve manter um núcleo manual e durável, suficiente para não precisar ser refeito quando chegar Open Finance:

- ativo/tipo/ticker ou nome;
- data de compra;
- quantidade;
- preço de compra;
- taxas quando aplicáveis;
- movimentos essenciais;
- custo médio;
- total investido;
- posição/valor atual quando houver fonte confiável ou valor manual claramente identificado.

Não exigir reconstrução completa de dividendos históricos desconhecidos. Rendimentos antigos só entram quando houver dado conhecido; o sistema não inventa histórico. Rendimento recebido e posteriormente gasto pode permanecer como fato histórico quando conhecido, mas não aumenta patrimônio atual.

Investimentos alimentam Patrimônio, **não Caixa nem Disponível para gastar**, salvo quando uma posição realmente se transforma em caixa por evento registrado.

## 11. Patrimônio

Patrimônio é uma camada de consolidação: contas e liquidez + investimentos + outros ativos elegíveis − passivos, incluindo dívidas de cartão elegíveis.

Liquidez e patrimônio não são sinônimos. Uma posição de investimento valorizada aumenta patrimônio conforme a fonte de avaliação adotada, mas não deve aparecer como dinheiro livre para despesas.

Após a implementação do contrato financeiro atual e do check-in, executar auditoria específica de **Metas + Ativos/Investimentos + Patrimônio** antes de declarar a V2 encerrada.

## 12. Open Finance e fronteira da V3

Open Finance pertence à V3. A arquitetura da V2 deve preparar modelos e contratos para receber dados externos sem tornar a integração necessária para o funcionamento atual.

Na V3, Open Finance pode sincronizar posições, saldos, transações, rendimentos e demais informações que o provedor efetivamente disponibilizar. Não presumir que Open Finance substitui uma API de mercado.

Responsabilidades distintas:

- **Open Finance:** fonte dos dados financeiros do usuário disponibilizados pela instituição/provedor.
- **API de mercado opcional:** enriquecimento de cotação atual, histórico de preços e dados de mercado que Open Finance não fornecer adequadamente.

Se o provedor de Open Finance entregar todos os dados necessários para determinada avaliação, não criar uma segunda dependência sem necessidade.

## 13. Correções retroativas e histórico

Lançamento esquecido pertence à data financeira correta. Recalcular apenas períodos/indicadores afetados e preservar proveniência, revisão e histórico anterior quando necessário.

Mesmas entradas + mesma versão de regra devem produzir o mesmo resultado. Valores monetários não usam `float`; preservar centavos e arredondamento explícito.

## 14. Isolamento, segurança e não duplicidade

Todas as consultas financeiras são escopadas por `user_id`. Testes obrigatórios devem garantir que dados do usuário B nunca apareçam para o usuário A.

Cobrir regressões de:

- compra de cartão + fatura + pagamento;
- estorno/reembolso;
- pagamento parcial;
- parcelas entre meses;
- compromisso futuro;
- receita prevista ainda não recebida;
- check-in ausente/corrigido;
- combinações entre cartão, contas, orçamento, categorias, metas e reservas.

Não adicionar índices, migrations ou persistência nova “por precaução”; mudanças estruturais exigem necessidade demonstrada.

## 15. Gate de completude da V2

Antes de declarar a V2 concluída:

1. Criar matriz final de cada card/gráfico/indicador: pergunta simples, natureza financeira, fonte de dados, entradas permitidas, exclusões, comportamento sem dados e testes.
2. Validar regras cruzadas entre cartão, contas, parcelas, receita prevista, categorias, orçamento, reservas e metas, sem dupla contagem ou falsa disponibilidade.
3. Executar cenários de fronteira: compra antes/no/depois do fechamento; fatura paga/pendente; parcela; estorno; recebimento previsto não recebido; conta futura; ausência de movimentos; combinações e isolamento A/B.
4. Fazer varredura final de todas as telas financeiras procurando informação escondida, zerada incorretamente, contraditória ou omitida.
5. Executar o **teste de falsa tranquilidade**: nenhuma tela pode aparentar situação saudável apenas porque uma obrigação conhecida ficou fora da leitura.
6. Validar explicabilidade e reconciliação entre telas.
7. **Inv** dá a palavra final sobre coerência matemática/financeira; **QA/Bento** valida regressões e cenários.

Depois de aprovado, este contrato permanece fechado. Divergência encontrada durante implementação é tratada primeiro como bug/lacuna de implementação; só muda o contrato quando houver decisão explícita de negócio.

## 16. Ordem de implementação restante

1. Implementar e validar o contrato financeiro consolidado desta versão nos componentes/consultas existentes.
2. Implementar a UX faltante do check-in e validar snapshots/correções.
3. Implementar/auditar o núcleo enxuto de Ativos/Investimentos.
4. Auditar Metas + Patrimônio e integração dos ativos à visão patrimonial.
5. Executar matriz/gates finais, stress/regressão e revisão Inv + QA.
6. Encerrar V2 somente depois desses gates.

## 17. Processo de desenvolvimento e release

Este documento **não é fonte de verdade do Git/deploy**. Antes de qualquer implementação, manutenção ou release, consultar obrigatoriamente:

- `AGENTS.md`;
- `.ai/rules/index.md`;
- as regras específicas apontadas pelo índice, incluindo `.ai/rules/release-build.md` quando aplicável;
- `docs/BUILD_BRANCH_WORKFLOW.md` para o procedimento operacional de build/release.

O processo operacional deve ser obtido desses arquivos no momento da execução, e não da memória de uma conversa. O contrato apenas impõe o gate de que nenhuma implementação da V2 pode ignorar as regras vigentes do repositório.

## 18. Responsabilidades

- **Maia:** coordena etapas e garante leitura das fontes vigentes antes do trabalho.
- **Inv:** matemática, invariantes, não duplicidade, capacidade, disponibilidade conservadora e gate financeiro final.
- **Atlas:** arquitetura, fronteiras fato/indicador, contratos e seleção de dados.
- **Lia:** semântica, nomenclatura, UX e explicabilidade.
- **Bento/QA:** cenários independentes, calendário, centavos, isolamento, gráficos e regressões.
- **Íris:** isolamento, privacidade e integrações.
- **Nexo:** idempotência, concorrência e fechamento/histórico.
- **Nilo:** implementação somente após leitura das regras e contratos aplicáveis.

## 19. Fora do escopo atual

Permanecem fora desta implementação: Open Finance completo, automação de reconstrução histórica de dividendos sem fonte confiável, score financeiro/comportamental não aprovado, WhatsApp, importação OFX geral e decisões financeiras livres geradas por IA.

A IA pode explicar indicadores e tendências, mas números oficiais vêm do motor determinístico e de fontes rastreáveis.
