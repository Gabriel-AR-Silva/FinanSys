# FinanSys V2 — Decisões conceituais aprovadas

Status: decisões aprovadas em conversa em 2026-09-21; **não são autorização para implementação nem substituem o contrato vigente**. Referências: `DAILY_FINANCIAL_ENGINE_PROPOSAL.md` e `FINANCIAL_PLANNING_CONTRACT.md`. Revisar com agentes responsáveis e formalizar eventuais mudanças no contrato antes de alterar código.

## Regras aprovadas

1. **Folga — política A:** diferença entre orçamento diário aplicável e gasto elegível realizado; acumular sem redistribuir automaticamente. Folga não é receita, saldo nem aporte. Excesso diário reduz o acumulado, que pode ficar negativo; orçamento do dia seguinte não muda automaticamente.
2. **Indicadores de folga — A + C:** exibir economia bruta, excesso bruto e resultado líquido separadamente, sem duplicar efeitos. Capacidade financeira e projeção podem ser recalculadas como informação; não impor novo orçamento.
3. **Dashboard:** visão geral simples e análise avançada, preferencialmente abas do mesmo dashboard; títulos curtos, hierarquia visual, linguagem acessível, ajuda contextual por hover e toque; evitar métricas redundantes. Explicar de modo simples o significado e a origem de cada indicador.
4. **Fixos — C:** exibir custo diário estrutural equivalente e pagamento na data real, sem dupla contagem e sem lançamentos fictícios. O custo diário não substitui a reserva para vencimentos.
5. **Cartão — C:** separar compra/compromisso/pagamento. Valor total assumido acessível na compra, parcelas comprometem os meses de vencimento e pagamento da fatura liquida obrigação sem criar nova despesa. Respeitar regra vigente de parcelas, antecipações, juros e pagamentos parciais.
6. **Extraordinários — B + C:** classificação manual comum/extraordinário; extraordinários separados da média cotidiana e não extrapolados como recorrentes, mas afetam os recursos e planejamento reais.
7. **Recebimentos — C:** separar disponível hoje e projetado, respeitando datas e compromissos. Previsto, recebido, atrasado e não recebido devem ter comportamento claro; não recebido deixa de compor projeção sem apagar histórico. Preservar tratamento vigente de recebimento parcial, residual, remarcação e cancelamento; definir mapeamento dos estados antes de implementar.
8. **Correções retroativas — C:** recalcular indicadores afetados e preservar avaliação anterior, momento e motivo da revisão. Não alterar silenciosamente o orçamento originalmente aplicável ao dia.
9. **Check-in diário — C:** ausência de lançamento não equivale a gasto zero confirmado. Check-in independente de login; modal ao entrar com dias anteriores pendentes; revisar por dia, confirmar sem outras despesas ou lançar esquecidas; confirmação individual e em lote, com revisão explícita dos dias selecionados. Recalcular somente períodos/indicadores afetados após confirmação. Permitir acessar dashboard com aviso de dados incompletos; lembretes sem repetição excessiva. Dia sem gasto variável confirmado ainda possui custo estrutural. Correção posterior mantém trilha histórica.

## Pendências para revisão dos agentes antes do código

- Delimitar gasto elegível por data, cartão, fixos, transferências, estornos e extraordinários; verificar que indicadores de consumo, obrigação e caixa não se somam indevidamente.
- Definir semântica de dias não confirmados para médias, folga acumulada e projeção: não tratar ausência como zero validado.
- Definir abertura/fechamento e revisão de check-ins, fuso America/Sao_Paulo, recuperação de dias não processados, idempotência, permissões e auditoria.
- Definir tratamento de recebimentos não recebidos versus cancelados e atrasados no modelo existente, inclusive parciais.
- Validar cálculo da capacidade, disponibilidade de caixa e projeção por data, sem afirmar que orçamento é saldo bancário.
- Revisar contratos, agentes e testes de regressão V1; implementação incremental somente após aprovação do contrato técnico.

## Sequência sugerida

1. Revisão de agentes e contrato matemático com casos de referência.
2. Seleção de dados e calculadora determinística com testes de não duplicidade.
3. Histórico/revisões e check-in com testes de idempotência.
4. Visão geral simples, depois análise avançada e notificações.

Não modificar o módulo OFX/XML como parte desta etapa; eventuais correções isoladas devem ser tratadas separadamente.