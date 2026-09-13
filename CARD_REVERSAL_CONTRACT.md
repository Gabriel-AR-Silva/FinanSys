# Contrato complementar — Estorno de compra no cartão (V1)

## Status

Decisão operacional adotada em 2026-09-13 para destravar o fechamento do V1, após revisão conceitual pelos papéis Inv, Lia e Atlas. Deve ser tratada como extensão do contrato de planejamento até ser incorporada ao documento principal.

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
- preservar a compra original e o evento de estorno no histórico;
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

### 4. Estorno em mês posterior

Exemplo: compra em agosto e estorno em setembro.

- agosto passa a possuir uma revisão/correção vinculada à despesa original para indicadores históricos corrigidos;
- o fechamento originalmente apresentado em agosto não é apagado nem falsificado; revisão posterior deve possuir proveniência;
- setembro registra o evento de estorno/crédito, mas não recebe receita sustentável fictícia;
- eventual crédito reduz obrigação de cartão apenas quando explicitamente aplicado conforme regra abaixo.

## Aplicação de crédito

Crédito de cartão é um ativo/compensação específico do cartão, não dinheiro livre.

- crédito permanece disponível até uso, expiração/cancelamento suportado ou associação explícita;
- aplicação em outra fatura/obrigação exige seleção explícita;
- não aplicar automaticamente em parcelas ou encargos sem contrato de associação;
- aplicação parcial conserva saldo residual do crédito;
- uma mesma parcela/alocação não pode consumir o mesmo crédito duas vezes;
- centavos devem ser conservados deterministicamente;
- aplicação e estorno devem ser idempotentes e atômicos.

## Invariantes

1. `valor_cancelado_pendente + valor_creditado_pago` nunca pode exceder o valor elegível da compra estornada.
2. Crédito de cartão nunca entra em `receita atual` ou `receita projetada`.
3. Crédito não cria ledger de entrada em conta bancária/caixinha.
4. Pagamento já realizado não é apagado; correção é representada por evento próprio.
5. Compra, estorno, crédito e aplicações preservam trilha de auditoria.
6. Dados permanecem isolados por `user_id`.
7. Replays idempotentes não duplicam estorno, crédito, aplicação, auditoria ou alerta.
8. Falha em qualquer parte obrigatória aborta a operação financeira inteira.
9. Indicadores não podem contar simultaneamente despesa original e correção como duas despesas/duas receitas.
10. Fechamentos diários já apresentados permanecem imutáveis; correções posteriores geram revisão/proveniência.

## Responsabilidades dos agentes

- **Inv:** revisar coerência financeira e risco de interpretação como renda.
- **Lia:** validar comportamento, mensagens e critérios de aceitação.
- **Atlas:** definir relacionamentos, estados, contratos HTTP e estratégia de persistência.
- **Nilo:** implementar somente após os contratos técnicos derivados estarem explícitos.
- **Bento:** construir matriz de testes de estorno, crédito, idempotência, centavos e regressão.
- **Íris:** validar isolamento e autorização de todos os recursos envolvidos.
- **Nexo:** validar atomicidade, locks, concorrência, replay e recuperação.
- **Maia:** coordenar sequência, gates e atualização de `FINANCIAL_PLANNING_STAGES.md` e `CODEX_HANDOFF.md`.

## Matriz mínima de aceite

- compra 300 não paga -> estorno remove 300 pendentes e cria crédito 0;
- compra 300, pago 100 -> estorno remove 200 pendentes e cria crédito 100;
- compra 300 totalmente paga -> estorno cria crédito 300 e pendência zero;
- crédito 300 aplicado em obrigação 180 -> saldo de crédito 120;
- replay da mesma chave -> nenhum efeito duplicado;
- mesma chave com parâmetros diferentes -> rejeitar;
- recurso de outro usuário -> rejeitar sem escrita parcial;
- estorno em mês posterior -> competência corrigida na origem, evento real na data do estorno e nenhuma receita fictícia;
- fechamento diário anterior -> preservado, com revisão posterior identificada;
- alerta/overview -> recalculado uma vez após commit financeiro efetivo.

## Fora deste contrato

- devolução em dinheiro para conta bancária;
- chargeback/disputa com emissor;
- cashback, pontos ou milhas;
- conversão automática do crédito em verba mensal;
- WhatsApp e OFX;
- regras do Daily Financial Engine V2.
