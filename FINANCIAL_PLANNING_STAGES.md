# Planejamento financeiro — etapas de execução

## Uso deste roteiro

Complementa [o contrato funcional](FINANCIAL_PLANNING_CONTRACT.md). Documentação de execução, sem implementação nesta revisão. Decisões aprovadas são preservadas; lacunas materiais ficam no relatório de revisão antes de implementar a parte dependente.

Atualização de fechamento: o Chefe pediu conclusão da implementação para publicação sem WhatsApp e OFX por enquanto. E7 e importação OFX estão fora desta entrega. O Chefe aprovou conjuntamente P1–P6 em 2026-09-08, após pareceres de Ratsel e Inv. Seguir E2 → E3 → E4 completa → E5 → E6 → validação/publicação, preservando as entregas locais já registradas abaixo.

Independência significa escopo, aceite e ativação próprios. Existem dependências de dados: indicadores não podem ser declarados completos antes dos adaptadores financeiros. Nenhuma etapa exige WhatsApp para funcionar. Ao interromper uma entrega, as já aceitas devem continuar utilizáveis.

Maia integra e mantém a sequência. Cada etapa tem autor e revisor distintos. Propriedade de arquivos concretos é atribuída antes da implementação; dois agentes não editam o mesmo arquivo simultaneamente. Mudanças de contrato atravessando etapas voltam à revisão dos consumidores.

## Mapa de dependências

| Etapa | Depende de | Resultado utilizável |
| --- | --- | --- |
| E0 Contratos verificáveis | Revisão funcional | Exemplos e interfaces para orientar trabalho |
| E1 Configuração | E0 pertinente | Preferências e orçamentos editáveis |
| E2 Planejamento e liquidação | E1 | Previsões e compromissos acompanhados |
| E3 Cartões e parcelas | E0; integração com E2 | Obrigações de cartão acompanhadas |
| E4 Calculadora pura | E0 matemático | Cálculo verificável com entradas de referência |
| E5 Integração e indicadores | E1, E2, E3, E4 | Visão financeira completa do escopo aprovado |
| E6 Histórico e alertas | E5 | Evolução diária e avisos internos |
| E7 WhatsApp | E6; contrato próprio | Canal opcional independente |

E3 e E4 podem ser desenvolvidas em paralelo após E0, com arquivos delimitados. E5 pode ser testada parcialmente com adaptadores prontos, identificando cobertura incompleta; não publicar um diagnóstico completo baseado em dados omitidos.

## E0 — Contratos verificáveis

- Entrada: decisões aprovadas e lacunas da revisão.
- Escopo: formalizar conjuntos exclusivos de valores, competência versus pagamento, fórmulas, significado dos estados, datas e exemplos de referência.
- Entregável: contrato técnico de entrada/saída com valores decimais, mês, instante de avaliação, origem dos dados, versão de regras e motivo do estado. Definir invariantes de vínculo, residual, exclusão e correção.
- Responsáveis: Atlas na estrutura, Lia no comportamento, Inv na coerência; Bento revisa exemplos independentemente.
- Aceite: cada campo e fórmula tem unidade, fonte, casos limite e resultado esperado; questões de produto estão decididas ou bloqueiam explicitamente apenas sua fatia.
- Fora: criar tabelas, endpoints ou alterar saldo nesta etapa.

## E1 — Configuração financeira

- Escopo: aba central, proteção fixa/percentual, orçamento essencial por categoria, cadastro progressivo e atalho de categoria preservando formulário.
- Entrada: usuário, categorias próprias e contrato de vigência das configurações.
- Saída: configuração válida e estado de completude; distinguir ausência de dado de zero confirmado.
- Nilo implementa; Lia revisa experiência, Bento valida comportamento e Íris isolamento.
- Aceite: criar/editar; proteção exclusiva e limites; categoria compatível; salvar/recarregar mantém valores; usuário B não acessa A; mobile sem perda de formulário; mudanças têm escopo temporal definido.
- Ativação: pode funcionar sem calculadora e sem cartões; não apresentar estimativa fictícia.
- Reversibilidade: ocultar a interface preserva dados; migrações de retirada não apagam configurações por padrão.

## E2 — Previsões, compromissos e liquidação

- Escopo: receitas previstas, fixos, recorrência, vínculo ao realizado, parcial, residual, remarcação e encerramento. Calendário Brasília com ajuste de datas inexistentes.
- Entrada: E1 e lançamentos existentes; contrato define vínculo e capacidade remanescente.
- Saída: ocorrências do mês, realizados vinculados e pendências identificadas, aptos à agregação.
- Nilo implementa; Bento verifica fluxos, Íris acesso, Nexo transações/repetição.
- Aceite: previsto 1000/recebido 600 deixa residual 400; cancelar residual preserva 600; reenvio não duplica; vencido não muda de mês automaticamente; recorrência dia 31 volta a 31 após fevereiro; execução repetida não gera nova ocorrência.
- Falhas: vínculo inválido não deixa escrita parcial; edição/exclusão/restauração de vínculo segue regra fechada em E0; concorrência é verificada no banco compatível com produção.
- Ativação: planejamento útil mesmo com indicadores desativados.

## E3 — Cartões, faturas e parcelas

- Motivo: o inventário atual de app/Models contém contas, caixinhas e lançamentos, mas não modelos de cartão/fatura. Esse domínio exige implementação explícita; não tratá-lo como mero ajuste no gráfico.
- Escopo: registrar compra total, parcelas e vencimentos, acompanhar fatura, liquidar total/parcial, carregar dívida, registrar encargos confirmados e antecipar parcelas com desconto.
- Entrada: regras de competência aprovadas e contratos de vínculo de E2; Atlas especifica agrupamento e rateios antes do código.
- Saída: compromissos por mês, pagamentos e residuais; histórico original preservado.
- Nilo implementa; Inv/Lia revisam cenários, Bento testa conservação e Íris isolamento.
- Aceite: 500 pagos em 300 deixam 200; 15 de encargos levam obrigação a 215 sem nova despesa de 200; antecipar 200 por 190 afeta apenas 190 agora e libera 200 futuros; pagar não gera outra despesa; nenhuma parcela é liquidada duas vezes.
- Dependências a fechar: fechamento/vencimento, ordem de alocação de pagamentos, desconto por parcela e estorno após liquidação.
- Ativação: telas de cartão podem ser aceitas antes do dashboard novo. Desativar a UI não elimina obrigações já existentes dos cálculos integrados.

## E4 — Calculadora matemática pura

- Escopo: operar sobre fatos normalizados e relógio explícito, sem consultar banco, enviar alertas ou escrever lançamentos.
- Entrada: contrato E0, incluindo previsões remanescentes, compromissos anteriores, essenciais, extraordinários e proteção.
- Saída: verba, margem, projeção, médias, déficit, faixas e motivos, com origem atual/projetada explícita.
- Nilo implementa; Inv revisa coerência e Bento revisa/roda os testes matemáticos.
- Aceite: matriz do contrato, centavos conservados, dias 28–31, fronteiras 90/100%, zero/negativos, limite sem divisão por zero, nenhuma duplicidade entre realizado e previsto. Testar invariantes com premissas fixas.
- Independência: dados de referência permitem validar sem E2/E3; esse teste não comprova a integração com registros reais.

## E5 — Seleção de dados e indicadores

- Escopo: adaptar registros de E1–E3 ao contrato da calculadora, neutralizar transferências/estornos semanticamente e apresentar os resultados.
- Aceite de integração: mesmo cenário de referência criado via fluxos reais produz os mesmos resultados de E4; inclui residual, antecipação, exclusão/restauração e dados de outro usuário sem vazamento.
- Aceite visual: configuração incompleta, zero, déficit e valores longos; atual/projetado identificados; essencial reservado separado de margem livre; cor com texto/ícone; teclado e formulários utilizáveis em mobile real.
- Nilo implementa; Bento valida, Lia revisa compreensão, Íris isolamento. Fluxo mede consultas quando houver hipótese concreta.
- Ativação: manter dashboard existente utilizável durante a entrega. Não chamar déficit de orçamento de saldo bancário negativo.

## E6 — Histórico e alertas internos

- Escopo: fechamento diário de Brasília, avaliação preservada, correção identificada, transições de estado e personalidade aprovada.
- Entrada: resultado validado de E5 e versão das premissas.
- Saída: histórico consultável e eventos internos deduplicados; futura entrega externa consome eventos sem mudar o cálculo.
- Nilo implementa; Nexo revisa recuperação, Bento testes e Lia linguagem.
- Aceite: repetir fechamento não duplica; falha é recuperável; backfill não finge avaliação que foi exibida; correção preserva versão anterior; acesso repetido não repete aviso; projeção ruim não vira mensagem falsa sobre saldo real; primeiras 48 horas civis do mês seguem regra de dias 1 e 2, não contagem desde cadastro.
- Personalidade: frases curtas, emojis e zoeira conforme contrato; seleção de texto não interfere na matemática.
- Ativação: falha no histórico/aviso não impede registro financeiro. Definir retenção sem exclusão silenciosa.

## E7 — WhatsApp opcional

- Escopo futuro: habilitar canal por usuário/cliente, preferências e eventos elegíveis; política de custo/conteúdo em contrato próprio.
- Entrada: eventos de E6, autorização de envio e configuração válida.
- Nilo implementa; Íris revisa privacidade e Nexo/Bento entrega e repetição.
- Aceite: desligado envia zero; falha de API não bloqueia finanças; tentativas limitadas; desativação vale também para mensagens pendentes; status recebido/entregue distinguido; dados mínimos nas mensagens e logs.
- Independência: sistema inteiro permanece funcional sem conta Meta ou canal ativo. Não realizar contratação ou envio nesta revisão.

## Registro obrigatório ao concluir cada etapa

Registrar escopo efetivamente entregue, arquivos alterados, comandos e resultados dos testes, limitações, dependências liberadas e parecer de revisor distinto do autor. Não marcar etapa completa por orçamento de uso ou pela existência de arquivos; critérios de aceite precisam de evidência.

## Entrega parcial E1 — 2026-09-08

- Implementado localmente: configuração por mês explícito, proteção fixa ou percentual, orçamento de categorias essenciais e cadastro rápido de categoria de despesa. A navegação inclui Configuração financeira.
- Persistência: MonthlyFinancialSetting e EssentialBudget, migração própria, validação, isolamento por usuário, transação, auditoria e controle de versão contra sobrescrita desatualizada. Remover um orçamento faz soft delete; reinseri-lo reutiliza o registro.
- Salvar afeta somente o mês selecionado. Não há propagação automática, cálculo de indicadores, alteração de lançamentos ou publicação em produção neste incremento.
- Os novos campos monetários aceitam até R$ 9.999.999.999,99, com teste de persistência exata no SQLite; percentual aceita de 0 a 100. Configuração ausente é distinguida de configuração explicitamente zerada.
- Evidências: suíte PHPUnit completa aprovada, 192 testes e 1.172 assertions; Pint aprovado; build Vite aprovado. Migração aplicada somente ao ambiente local.
- Bento revisou independentemente e apontou precisão no SQLite, rollback de orçamento e categoria inativa já vinculada; as correções e os testes correspondentes foram incluídos.
- Aceite visual ainda pendente: Edge indisponível nesta sessão; navegador interno redirecionou para login, sem sessão autenticada para inspecionar o formulário. Não foi comprovado funcionamento em dispositivo móvel real.
- Próxima sequência: concluir o aceite visual de E1 e fechar os contratos de vínculo de E2 antes de implementar previsões e liquidações. E1 não está declarada integralmente concluída.

## Entrega parcial E4 — primitivas matemáticas, 2026-09-08

- Arquivos: `app/Support/FinancialPlanningMath.php` e `tests/Unit/Support/FinancialPlanningMathTest.php`.
- Escopo independente aprovado: proteção fixa ou percentual sobre recebimentos agregados, arredondamento único para cima; distribuição diária de valor não negativo, arredondada para baixo e com resto explícito; dias restantes no mês corrente da avaliação em Brasília, incluindo hoje.
- Entradas monetárias em strings decimais, sem formatação localizada e com até duas casas. O cálculo usa a biblioteca Brick Math já instalada, sem nova dependência. Rejeita valores negativos/precisão excedente, percentuais acima de 100 e divisores fora de 1 a 31.
- Exemplo verificável: 100 em três dias retorna 33,33 por dia e resto 0,01; dois recebimentos de 0,01 a 50% retornam proteção 0,01.
- Fronteira explícita: o chamador deverá selecionar recebimentos elegíveis e não sobrepostos de uma única visão. Esta base não busca lançamentos, não trata reembolso como renda, não calcula faixas, não altera dados, não emite alertas nem muda o dashboard.
- Déficits deverão ser preservados pelo cálculo de orçamento ainda pendente, não passados à distribuição positiva; períodos encerrados não são aceitos como divisão por zero. O calendário recebe instante explícito, sem relógio global.
- E4 continua parcial: seleção de dados, projeção, essenciais extraordinários e denominador das faixas ainda dependem de E0/R06–R08. E2 mantém os bloqueios de vínculo já registrados; não foram decididos implicitamente por este incremento.
- Evidência: suíte completa aprovada (227 testes, 1.207 assertions); focal com 35 testes/35 assertions, também reexecutado após corrigir fixture de quebra de linha; Pint e diff sem erros. Bento revisou independentemente, executou o focal e não encontrou falha bloqueante no escopo delimitado.

## Complemento matemático de recebimentos — 2026-09-08

- Após aprovação do excedente pelo Chefe, `receiptProgress` calcula realizado, pendente, excedente informativo e cumprimento da previsão. Casos 1000/600 e 1000/1200 retornam respectivamente pendência 400 e zero; o segundo mantém 1200 realizados, sem adicionar os 200 novamente.
- É uma primitiva pura para previsões abertas com valor positivo e recebimentos já deduplicados pelo futuro adaptador. Não persiste encerramento nem escolhe o mês do recebimento; não implementa vínculo, cancelamento, exclusão, estorno ou remarcação.
- Verificação focal após a alteração: 47 testes e 47 assertions aprovados; Pint aprovado. Os 12 casos novos incluem recebimento exato, parcial, excedente, fronteiras de centavos, agregado grande e valores inválidos.
- Revisão independente: Bento executou os 47 testes e não encontrou bloqueios matemáticos; isso não aprova persistência ou deduplicação de vínculos ainda não implementadas.

## Entrega parcial E2 — cadastro de previsões, 2026-09-08

- Implementado: Recebimentos previstos acessível pela Configuração financeira, filtro mensal, listagem paginada, cadastro em modal com categoria de receita ativa/valor/data, indicação de atraso em Brasília e cancelamento preservando o registro. Não há exclusão física ou alteração de saldo.
- Backend: ReceiptForecast/ReceiptForecastStatus, factory, migração, CreateReceiptForecast/CancelReceiptForecast, FormRequest, controller e três rotas autenticadas. UI: ReceiptForecasts/Index.vue e link na configuração. Testes: ReceiptForecastTest.php.
- Criação é transacional e auditada, com operação UUID única por usuário; replay idêntico não duplica, payload divergente é recusado. Cancelar novamente não duplica auditoria. Testes cobrem isolamento, precisão, falha de auditoria com rollback, filtro no último dia do mês, paginação e replay após cancelamento.
- Evidências: suíte completa aprovada (259 testes, 1.355 assertions); teste focal reexecutado após ajuste final (20 testes, 136 assertions); build Vite, Pint e diff aprovados. Migração aplicada exclusivamente no ambiente local SQLite. Nenhuma publicação.
- Revisão: implementação dividida entre backend/frontend e revisão de integração pela raiz, com testes de comportamento escritos separadamente. Os agentes atingiram limite de uso durante ajustes finais; raiz concluiu mensagem de conflito e proteção de rascunho. Os testes SQLite não comprovam concorrência MySQL em produção.
- Aceite visual pendente: tentativa de controle do Edge retornou navegador indisponível. Navegação com rascunho e comportamento dos modais precisam de validação manual, inclusive mobile real; build não substitui essa evidência.
- Não implementado: edição/remarcação, recorrência, vínculo ao lançamento real, recebimento parcial, encerramento por cumprimento e desfazimento/restauração. As decisões aprovadas para esses fluxos estão preservadas no contrato, mas a matemática isolada não equivale à integração. Próxima entrega: vínculo transacional previsto-realizado, acompanhado da atualização do cancelamento para preservar recebimentos já vinculados.

## Complemento E2 — edição e remarcação, 2026-09-08

- Disponível em cada previsão aberta: Editar / remarcar, mudando categoria, valor e data. Remarcar move a previsão ao mês escolhido, sem mover dinheiro nem gerar lançamento. Previsões canceladas não podem ser editadas ou reabertas por essa operação.
- Nova ação UpdateReceiptForecast, request, rota PUT e controle de versão persistido por migração aditiva. Auditoria conserva antes/depois; edição sem mudanças não gera nova versão. Categoria inativa já vinculada pode ser mantida, mas não escolhida em outro vínculo.
- Versão protege tanto edição quanto cancelamento: confirmação antiga não cancela silenciosamente um valor/data alterado em outra aba. Replay de cancelamento já concluído permanece sem nova auditoria.
- Bento revisou separadamente e identificou ausência da versão no cancelamento; correção aplicada com casos de teste para conflito e versão ausente. Focal: 30 testes e 222 assertions aprovados; Pint aprovado. Migração aplicada somente no ambiente local SQLite, sem apagar dados.
- A validação visual/manual e concorrência MySQL continuam pendentes; testes HTTP não cobrem teclado/mobile. Vínculos reais, parciais, recorrência e desfazimento permanecem não implementados. Este complemento substitui apenas a pendência de edição/remarcação sem recebimentos vinculados do registro anterior.
- Verificação final: 269 testes e 1.441 assertions na suíte completa; build e diff aprovados. Bento releu a correção do cancelamento e não encontrou outro defeito concreto. Nova tentativa de inspeção do Edge retornou navegador indisponível.

## Complemento E2 — vínculo previsto-realizado, 2026-09-08

- Primeiro incremento de P1 implementado: uma receita real existente pode ser vinculada integralmente a uma previsão pendente; o vínculo não cria lançamento nem altera o saldo. Uma previsão aceita vários recebimentos parciais e passa a concluída quando a soma atinge ou supera o previsto.
- `receipt_forecast_links` preserva histórico explícito, usuário, operação idempotente, versão, instante de vínculo e motivo de desvínculo. FKs compostas impedem associação entre usuários e a unicidade do lançamento impede que a mesma receita pertença a duas previsões.
- A interface mostra previsto, recebido, pendente e excedente informativo, além dos recebimentos vinculados. Só lista receitas próprias, efetivas, não estornadas e ainda não associadas.
- Exclusão individual ou em cascata e estorno desativam o vínculo na mesma transação e recalculam a previsão. Restaurar o lançamento não restaura silenciosamente a associação. O revínculo confirmado permanece para um incremento futuro, juntamente com recorrência.
- Evidências: teste focal com 9 casos cobrindo parcial, excedente, idempotência, isolamento, versão desatualizada, estorno, exclusão/restauração individual e em cascata, seleção da tela e rollback de auditoria. Regressão dirigida: 87 testes e 611 assertions. Suíte completa: 278 testes e 1.522 assertions. Pint, build Vite e diff aprovados; migration aplicada no SQLite local.
- Limitações: a serialização de comandos usa lock do usuário e restrições únicas, mas concorrência real ainda precisa de evidência no banco de produção. Recorrência, revínculo confirmado e validação em dispositivo móvel real não fazem parte deste incremento.
