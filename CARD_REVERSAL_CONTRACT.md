# Contrato complementar — Estorno de compra no cartão (V1)

## Status

Decisão operacional adotada em 2026-09-13 para destravar o fechamento do V1, após revisão conceitual pelos papéis Inv, Lia e Atlas. Implementação em fechamento na branch `codex/v1-card-reversal`. Este documento estende o contrato principal e permanece subordinado aos gates de teste, concorrência, aceite visual e deploy do V1.

## Objetivo

Fechar a lacuna de E3 sobre estorno de compra e crédito de cartão sem transformar correção de despesa em renda, sem reescrever caixa passado e sem criar dupla contagem no planejamento.

## Regra central

O estorno possui duas datas semanticamente diferentes:

- **competência corrigida:** permanece vinculada à compra/parcela original;
- **data real do estorno/crédito:** registra quando a correção foi efetivamente reconhecida pelo cartão/sistema.

A despesa é corrigida na competência original para fins de análise de consumo e histórico corrigido. O evento de estorno permanece registrado na data em que ocorreu para auditoria, cronologia e efeitos atuais do cartão.

Crédito de cartão não é receita sustentável, não é entrada de caixa e não deve elevar automaticamente a verba disponível do mês.

## Cenários obrigatórios

### 1. Compra totalmente não paga

Ao estornar uma compra ainda não paga:

- cancelar somente as obrigações/parcelas pendentes elegíveis;
- remover seu comprometimento futuro do planejamento;
- preservar compra, parcelas e evento de estorno em persistência/auditoria, mesmo que a compra deixe de participar do conjunto ativo;
- não criar receita, movimento de caixa ou crédito pago;
- impedir novo pagamento das parcelas canceladas.

### 2. Compra parcialmente paga

Ao estornar compra com parte já paga:

- cancelar a parte ainda pendente;
- criar crédito de cartão somente pelo valor efetivamente pago e elegível para devolução;
- preservar pagamentos já realizados e sua cronologia;
- não apagar movimentos passados;
- não considerar o crédito como renda ou caixa;
- manter vínculo rastreável entre compra, parcelas, pagamentos, estorno e crédito.

### 3. Compra totalmente paga

Ao estornar compra totalmente paga:

- não recriar despesa nem apagar pagamento histórico;
- criar crédito do cartão no valor elegível devolvido;
- corrigir a análise da despesa na competência original;
- registrar o crédito na data real do estorno;
- não alimentar automaticamente orçamento, proteção ou média diária como se fosse recebimento.

### 4. Parcela antecipada com desconto

Se parte da compra tiver sido antecipada com desconto:

- a obrigação nominal liberada continua sendo o bruto antecipado;
- o crédito gerado pelo estorno considera o **valor líquido efetivamente pago** na antecipação;
- o desconto obtido não pode reaparecer como crédito fictício;
- exemplo: parcela nominal 100 antecipada por 95 gera, se elegível ao estorno integral, crédito 95 e não 100.

### 5. Estorno em mês posterior

Exemplo: compra em agosto e estorno em setembro.

- agosto passa a possuir uma revisão/correção vinculada à despesa original para indicadores históricos corrigidos;
- o fechamento originalmente apresentado em agosto não é apagado nem falsificado;
- a revisão posterior usa `revision` e `supersedes_id`, preservando proveniência;
- setembro registra o evento de estorno/crédito, mas não recebe receita sustentável fictícia;
- eventual crédito reduz obrigação de cartão apenas quando explicitamente aplicado.

## Aplicação de crédito

Crédito de cartão é uma compensação específica do cartão, não dinheiro livre.

- crédito permanece disponível enquanto possuir saldo;
- aplicação em outra obrigação exige seleção explícita;
- destino deve pertencer ao mesmo usuário e ao mesmo cartão;
- destinos suportados no V1: parcela pendente ou encargo pendente;
- aplicação parcial conserva saldo residual do crédito;
- não existe lançamento bancário para a aplicação;
- uma mesma chave idempotente não pode consumir crédito duas vezes;
- centavos são conservados deterministicamente;
- aplicação e estorno são atômicos e auditados.

## Estratégia técnica aprovada para o V1

- `card_purchase_reversals` preserva o evento e os totais cancelado/creditado;
- `card_credits` preserva o crédito e seu total já aplicado;
- `card_credit_allocations` registra cada associação explícita do crédito;
- parcelas estornadas recebem estado `reversed`;
- a compra estornada é removida do conjunto ativo por soft delete, preservando linha e auditoria, o que impede sua reutilização pelos fluxos existentes que exigem compra ativa;
- correções retroativas reutilizam `FinancialEvaluation` e criam nova revisão somente quando o resultado recalculado difere do fechamento mais recente;
- overview e alertas atuais são recalculados após mutação efetiva.

## Invariantes

1. `valor_cancelado_pendente + valor_creditado_pago` nunca pode exceder o valor elegível da compra estornada.
2. Em antecipação com desconto, crédito usa desembolso líquido e nunca recria o desconto.
3. Crédito de cartão nunca entra em `receita atual` ou `receita projetada`.
4. Crédito não cria ledger de entrada em conta bancária/caixinha.
5. Pagamento já realizado não é apagado; correção é representada por evento próprio.
6. Compra, estorno, crédito e aplicações preservam trilha de auditoria.
7. Dados permanecem isolados por `user_id` e aplicação exige o mesmo cartão.
8. Replays idempotentes não duplicam estorno, crédito, aplicação, auditoria ou alerta.
9. Falha em qualquer parte obrigatória aborta a operação financeira inteira.
10. Indicadores não podem contar simultaneamente despesa original e correção como duas despesas/duas receitas.
11. Fechamentos diários já apresentados permanecem imutáveis; correções posteriores geram revisão/proveniência.

## Responsabilidades dos agentes

- **Inv:** coerência financeira e risco de interpretar crédito como renda.
- **Lia:** comportamento, mensagens e critérios de aceitação.
- **Atlas:** relacionamentos, estados, contratos HTTP e persistência.
- **Nilo:** implementação dos contratos aprovados.
- **Bento:** matriz de testes e regressões.
- **Íris:** isolamento e autorização.
- **Nexo:** atomicidade, locks, concorrência, replay e recuperação.
- **Maia:** gates, integração, documentação e handoff.

## Matriz mínima de aceite

- compra 300 não paga -> estorno remove 300 pendentes e cria crédito 0;
- compra 300, pago 100 -> estorno remove 200 pendentes e cria crédito 100;
- compra 300 totalmente paga -> estorno cria crédito 300 e pendência zero;
- parcela 100 antecipada por 95 -> estorno elegível credita 95, não 100;
- crédito 300 aplicado em obrigação 180 -> saldo de crédito 120;
- aplicação de crédito não cria ledger bancário;
- replay da mesma chave -> nenhum efeito duplicado;
- mesma chave com parâmetros diferentes -> rejeitar;
- recurso de outro usuário ou outro cartão -> rejeitar sem escrita parcial;
- estorno em mês posterior -> competência corrigida na origem, evento real na data do estorno e nenhuma receita fictícia;
- fechamento diário anterior -> preservado, com revisão posterior identificada;
- alerta/overview -> recalculado após mutação financeira efetiva.

## Fora deste contrato

- devolução em dinheiro para conta bancária;
- chargeback/disputa com emissor;
- cashback, pontos ou milhas;
- conversão automática do crédito em verba mensal;
- WhatsApp e OFX;
- regras do Daily Financial Engine V2.
