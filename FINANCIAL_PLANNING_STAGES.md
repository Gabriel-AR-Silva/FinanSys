# Planejamento financeiro — etapas de execução

## Objetivo e fonte de verdade

Este roteiro acompanha a execução do [contrato funcional](FINANCIAL_PLANNING_CONTRACT.md). Ele descreve o estado atual da branch `codex/copilot-mod-v1-hardening`, não o estado histórico de incrementos intermediários.

As decisões P1–P6 foram aprovadas em 2026-09-08. WhatsApp e importação OFX permanecem fora desta publicação e não bloqueiam o fechamento. O detalhamento técnico e as pendências operacionais ficam em [CODEX_HANDOFF.md](CODEX_HANDOFF.md).

Estados usados:

- **Concluída:** implementação e testes automatizados cobrem o escopo contratado da etapa.
- **Parcial:** há implementação utilizável, mas ainda falta parte do contrato ou da aceitação.
- **Pendente:** não há entrega suficiente para declarar a etapa utilizável.
- **Fora da entrega:** escopo explicitamente adiado.

## Estado consolidado

| Etapa | Estado | Entregue | Falta para fechar |
| --- | --- | --- | --- |
| E0 — Contratos verificáveis | Concluída | Regras P1–P6, fórmulas, exemplos e invariantes registrados | Manter documentação sincronizada quando o comportamento mudar |
| E1 — Configuração financeira | Parcial | Configuração mensal, proteção fixa/percentual, essenciais, categoria rápida, isolamento, auditoria e controle de versão | Aceite visual no Edge desktop/mobile |
| E2 — Planejamento e liquidação | Parcial | Previsões, recorrência, edição/remarcação, parcial, residual, excedente, cancelamento, vínculo, desvínculo e revínculo explícito | Atualização imediata de alertas em todo o ciclo e concorrência no banco de produção |
| E3 — Cartões e parcelas | Parcial | Cartões, compras parceladas, parcelas, pagamentos parciais, alocação determinística e dívida carregada | Encargos, antecipação com desconto, estorno de compra e crédito de cartão |
| E4 — Calculadora matemática | Concluída | Proteção, progresso de recebimentos, projeções, essenciais, margem, verba diária, déficit e faixas | Manter matriz de fronteiras ao evoluir regras |
| E5 — Integração e indicadores | Parcial | Adaptadores reais, visões atual/projetada, neutralização de transferências/estornos e dashboard de planejamento | Jornada integrada final, isolamento transversal e aceite visual |
| E6 — Histórico e alertas | Parcial | Fechamento diário, reconstrução, revisões, proveniência e alertas internos deduplicados | Completar gatilhos imediatos, validar recuperação e fechar aceite visual |
| E7 — WhatsApp | Fora da entrega | Contrato de independência preservado | Implementação futura em contrato próprio |

## Dependências de fechamento

```mermaid
flowchart TD
    E2["E2: alertas e concorrência"] --> E5["E5: integração final"]
    E3["E3: ciclo completo do cartão"] --> E5
    E4["E4: matemática concluída"] --> E5
    E5 --> E6["E6: histórico e alertas"]
    E6 --> PUB["Validação e publicação"]
```

E1 pode ser usada independentemente. E2 e E3 alimentam os indicadores de E5. E6 consome os resultados de E5, mas uma falha de histórico ou alerta não deve impedir o registro financeiro principal.

## E0 — Contratos verificáveis

### Entregue

- Competência versus pagamento, valores decimais exatos e calendário de Brasília.
- Invariantes de vínculo, residual, idempotência, exclusão, restauração e correção.
- Regras de proteção, essenciais, projeção, cartões, histórico e alertas.
- Exclusão explícita de WhatsApp e OFX da publicação atual.

### Gate permanente

Nenhuma mudança de código pode alterar silenciosamente uma decisão contratada. Ambiguidade de produto volta ao contrato antes da implementação dependente.

## E1 — Configuração financeira

### Entregue

- Configuração por mês, distinguindo ausência de dado de zero confirmado.
- Proteção fixa ou percentual e orçamentos essenciais por categoria.
- Cadastro rápido de categoria de despesa sem perder o formulário.
- Isolamento por usuário, transação, auditoria, soft delete e controle de versão.

### Pendente

- Validar no Microsoft Edge desktop e em viewport móvel real.
- Confirmar teclado, foco, zoom, valores longos e persistência visual do formulário.

## E2 — Previsões, compromissos e liquidação

### Entregue

- Cadastro unitário e série recorrente mensal, inclusive retorno ao dia 31 após fevereiro.
- Edição da ocorrência ou das futuras ocorrências elegíveis da série.
- Remarcação do residual sem mover recebimentos já realizados.
- Vários recebimentos parciais por previsão e uma única previsão ativa por receita real.
- Pendência, cumprimento e excedente informativo sem duplicar renda.
- Cancelamento preservando valores já recebidos.
- Desvínculo por estorno/exclusão e revínculo somente por confirmação explícita.
- Idempotência, versão otimista, auditoria, isolamento e rollback nos fluxos cobertos.

### Pendente

- Recalcular alertas imediatamente após criação, edição, cancelamento e vínculo quando houver mudança semântica.
- Validar locks, unicidade e retries no mesmo mecanismo de banco escolhido para produção.
- Executar aceite visual dos modais, recorrência e revínculo.

### Critérios obrigatórios preservados

- Previsto 1.000 e recebido 600 deixa residual 400.
- Cancelar o residual preserva os 600 realizados.
- Reenvio não duplica ocorrência nem vínculo.
- Vencido não muda de mês automaticamente.
- Restauração do lançamento não reativa vínculo silenciosamente.

## E3 — Cartões, faturas e parcelas

### Entregue

- Cadastro de cartão e compra total parcelada.
- Distribuição exata dos centavos entre parcelas.
- Pagamento total ou parcial limitado à dívida elegível.
- Alocação determinística por vencimento e identificador.
- Dívida anterior carregada e pagamento sem criar uma segunda despesa de consumo.
- Isolamento, idempotência, auditoria e atualização imediata dos alertas para compra e pagamento.

### Pendente

- Registrar juros e encargos sem reapresentar o principal já carregado.
- Antecipar parcelas com desconto, afetando o presente pelo valor efetivamente pago e liberando a obrigação futura.
- Estornar compra ainda não paga, removendo apenas a obrigação pendente.
- Estornar compra já paga, removendo o pendente e criando crédito no cartão pelo valor pago.
- Aplicar crédito a outra fatura somente mediante associação explícita.
- Acrescentar telas, contratos HTTP e testes para esses fluxos.

### Critérios obrigatórios preservados

- Dívida 500 paga em 300 deixa 200.
- Encargo 15 transforma a obrigação restante em 215, sem duplicar os 200.
- Antecipar obrigação 200 por 190 afeta 190 agora e libera 200 futuros.
- Nenhuma parcela pode ser liquidada duas vezes.

## E4 — Calculadora matemática pura

### Entregue

- Cálculos sem consulta ao banco ou efeitos colaterais.
- Strings decimais exatas com Brick Math, sem `float`.
- Proteção fixa/percentual, progresso de recebimento, projeção ordinária, essenciais, margem livre, verba diária, déficit e situação financeira.
- Relógio explícito, dias completos de Brasília, fronteiras 90/100%, centavos e divisão segura.

### Gate permanente

Os adaptadores devem entregar conjuntos exclusivos. Pagamento de obrigação, reembolso, previsão realizada e estorno não podem aparecer duas vezes em uma mesma visão.

## E5 — Seleção de dados e indicadores

### Entregue

- Seleção por usuário e mês para lançamentos, previsões, reembolsos e parcelas.
- Visões atual e projetada com renda, proteção, fixos, variáveis, essenciais, compromissos anteriores, margem e verba diária.
- Transferências internas neutras e estornos considerados semanticamente.
- Fronteiras mensais mantidas no fuso da aplicação.

### Pendente

- Reexecutar o cenário contratual completo pelos endpoints e ações reais.
- Confirmar ausência de vazamento entre usuários em todos os consumidores.
- Cobrir os novos fluxos de encargos, antecipação e crédito após E3.
- Validar estados incompleto, zero, déficit, valores longos e distinção atual/projetado no Edge desktop/mobile.

## E6 — Histórico e alertas internos

### Entregue

- Fechamento diário oficial e reconstrução de lacunas sem fingir apresentação ao usuário.
- Revisões encadeadas preservando resultados anteriores e origem da avaliação.
- Alertas internos por visão e mês, deduplicados e atualizáveis.
- Preservação da pior situação e do histórico de déficit.
- Reativação correta quando uma situação recuperada volta a piorar.
- Atualização imediata após lançamento manual, reembolso, estorno manual, compra e pagamento de cartão.
- CI com PHPUnit, Pint, build Vite e validação do manifest.

### Pendente

- Integrar atualização imediata com previsões e exclusão/restauração de lançamentos, contas e caixinhas.
- Confirmar que replay e operações semanticamente neutras não repetem atualização.
- Validar linguagem, leitura, filtros e responsividade da página de alertas.
- Definir retenção operacional sem apagar silenciosamente histórico ou auditoria.

## E7 — WhatsApp opcional

Fora desta publicação. Quando retomado, deve possuir autorização, preferências, idempotência, tentativas limitadas e falha isolada do núcleo financeiro. Desligado deve enviar zero mensagens.

## Gates para declarar o contrato fechado

- E2, E3, E5 e E6 sem itens funcionais pendentes dentro do escopo aprovado.
- Suíte completa, Pint e build aprovados no commit candidato.
- Testes de concorrência executados no banco compatível com produção.
- Aceite visual no Microsoft Edge desktop/mobile e verificação básica por teclado.
- Migrations ensaiadas com backup, rollback e preservação da `APP_KEY`.
- OAuth, HTTPS, filas, scheduler, secrets e ausência de seeder de desenvolvimento validados.
- Smoke test após a implantação.
- Política contábil da data de estorno registrada como decisão final.

## Registro obrigatório por incremento

Ao concluir trabalho, atualizar este roteiro e o `CODEX_HANDOFF.md` com:

- comportamento efetivamente entregue;
- arquivos alterados;
- testes executados e resultados;
- riscos residuais e dependências liberadas;
- distinção entre evidência SQLite e evidência do banco de produção.

Não marcar uma etapa como concluída apenas porque existem arquivos ou testes genéricos. Cada critério precisa de evidência que detectaria uma regressão concreta.
