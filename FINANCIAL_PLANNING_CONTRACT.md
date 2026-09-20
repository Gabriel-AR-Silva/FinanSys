# Contrato de planejamento e ritmo financeiro

## Estado e autorização

Consolidado a partir das decisões aprovadas pelo Chefe e revisado em 2026-09-20 após a entrega da V1. Este é o contrato canônico das regras de planejamento financeiro; implementação, evidências e pendências operacionais são rastreadas em código, testes, PRs e issues, não em relatórios temporários.

As etapas descritas neste contrato preservam dependências e critérios de aceite. Uma funcionalidade só é considerada publicada depois do fluxo de release e do smoke no ambiente real; CI verde comprova o código testado, não o estado da produção.

A orientação aprovada do Ratsel é cadastro progressivo, atalhos no contexto, explicação das premissas e entregas verificáveis. O contrato OFX permanece separado.

## Decisões aprovadas

- Período mensal, por mês-calendário; renda variável, sem salário fixo presumido.
- Visões atual e projetada separadas. Receita prevista nunca é apresentada como dinheiro disponível.
- Saldo anterior aparece no patrimônio/saldo geral e não financia automaticamente o orçamento mensal. Alocação manual desse patrimônio não integra o escopo aprovado.
- Compromissos fixos são planejados separadamente e vinculados ao pagamento real, sem dupla contagem.
- Recebimentos previstos possuem valor estimado, data, categoria/origem, recorrência opcional e estados previsto, recebido ou cancelado. O vínculo ao recebido substitui a previsão, inclusive se houver diferença de valor.
- Recebimento parcial: somente o valor recebido entra na visão atual; o restante continua previsto na data esperada. O usuário pode remarcar o residual ou encerrar a previsão se não for recebê-lo. Após o vencimento, o residual é sinalizado como atrasado, sem mudança automática de mês. Encerrar a previsão não apaga valores já recebidos.
- Complemento aprovado em 2026-09-08: recebimento igual ou superior ao previsto encerra a previsão por cumprimento. Previsão de 1000 recebida em 1200 considera 1200 efetivamente recebidos e zero pendente; os 200 excedentes são apenas informação, nunca uma segunda receita. Exclusão, estorno e remarcação do vínculo permanecem nas pendências específicas da revisão.
- Complemento aprovado posteriormente: desfazer um recebimento devolve seu valor à pendência da previsão; 1000 previstos, 600 recebidos e depois desfeitos voltam a 1000 pendentes. Se a previsão tiver sido encerrada manualmente ou remarcada, exigir confirmação do destino da pendência antes de reabrir ou definir datas. Preservar o histórico. Essa aprovação não declara o vínculo implementado.
- Verba protegida opcional, padrão zero, por valor ou percentual (0% a 100%), mutuamente exclusivos. Percentual sobre recebimentos confirmados na visão atual; na projeção, confirmados mais previstos ainda não realizados. A proteção não reduz o patrimônio.
- Essenciais variáveis aceitam categorias livres: alimentação e combustível são exemplos. Realizados consomem/substituem a previsão correspondente sem duplicidade.
- Projeção por essencial: usar o maior valor entre orçamento informado e projeção pelo ritmo observado, ambos como totais do período que incluem o realizado. Não somar as duas estimativas. Extraordinários ficam fora da extrapolação e são considerados uma vez. A interface explica que compras concentradas no início do mês podem superestimar o ritmo.
- Essencial e fixo são características independentes. Não presumir que toda movimentação de uma categoria tenha o mesmo comportamento sem configurar o planejamento.
- Transferências próprias são neutras para gastos. Pagamento de fatura não duplica compras. Estornos/reembolsos corrigem despesas relacionadas; tarifas e juros são despesas.
- Regra de parcelas aprovada: a compra registra o compromisso completo; cada parcela compromete o orçamento do mês de seu vencimento; pagar a fatura liquida o compromisso sem gerar outra despesa. Total assumido e parcelas futuras permanecem acessíveis. Esta decisão substitui a formulação anterior ambígua sobre contabilizar a compra na data da compra.
- Pagamento parcial reduz a obrigação pendente; atraso mantém o restante visível nos meses seguintes até quitar, sem recriar a despesa original. Juros/multas conhecidos são despesas novas; encargos desconhecidos são sinalizados sem estimativa inventada.
- Dívida entre meses: o residual anterior reserva verba no mês atual como compromisso anterior pendente; o pagamento substitui essa reserva sem novo desconto orçamentário. A despesa original permanece no histórico de seu mês. Juros confirmados são tratados separadamente.
- Antecipação permite selecionar parcelas, transfere seu impacto para o mês do pagamento pelo valor efetivo (incluindo desconto) e libera os compromissos futuros correspondentes. Preservar compra, vencimentos originais e histórico. Exemplo: duas parcelas de 100 antecipadas por 190 comprometem 190 agora e liberam 200 futuros.
- Verba variável restante, essenciais ainda previstos e margem livre são indicadores distintos. Gasto essencial consome a previsão remanescente; somente seu excedente reduz adicionalmente a margem livre.
- Extraordinário/planejado reduz a verba, mas não é extrapolado como gasto diário recorrente.
- Faixas aprovadas: até 90% sob controle; acima de 90% até 100% em equilíbrio; acima de 100% ou déficit fora de controle. A base exata do percentual depende do fechamento do modelo de projeção.
- Nos dois primeiros dias, evitar diagnóstico por média insuficiente; déficit conhecido permanece visível imediatamente.
- Personalidade aprovada para o Chefe: amigo zoeiro, próximo, com emojis e palavrões pontuais. Manter clareza financeira, sem humilhação, julgamento ou promessa de recuperação garantida. Preferências de tom por usuário e palavrões opcionais ficam previstas para eventual SaaS.
- WhatsApp é módulo separado e opcional, preparado para escolha por usuário/cliente em eventual SaaS. Envio somente quando necessário, com gatilhos ainda a definir.

## Configuração financeira e experiência

Centralizar compromissos fixos, recebimentos previstos, orçamento de essenciais variáveis, verba protegida, preferências de alertas e acesso à configuração do módulo WhatsApp quando disponível.

Permitir começar com poucos dados e completar depois. Ausência de configuração não equivale automaticamente a valor zero confirmado. Mostrar o que falta para interpretar o indicador.

Atalho para adicionar categoria dentro do planejamento, preservando campos e retornando ao formulário original. Respeitar compatibilidade receita/despesa e isolamento do usuário.

Hierarquia proposta para validação visual: situação do mês, média disponível por dia, projeção de fechamento, composição da verba e motivos das mudanças. Detalhes expansíveis no mobile; texto e ícone acompanham cores. Identificar período, atualização e visão selecionada.

O valor diário é média de planejamento, não limite transacional nem autorização para consumo. Uma despesa pontual acima da média diária não determina sozinha a situação do mês.

## Modelo matemático em revisão

Separar saldo financeiro, orçamento mensal, média realizada e projeção. Não reaproveitar automaticamente a média móvel existente no dashboard.

Base funcional a traduzir em contrato técnico com conjuntos mutuamente exclusivos:

```text
receita atual = receitas confirmadas elegíveis do mês
receita projetada = receita atual + previsões ainda não realizadas
fixos considerados = fixos pagos + compromissos fixos pendentes
compromissos anteriores considerados = resíduos anteriores pagos neste mês
                                    + resíduos anteriores ainda pendentes
variáveis pendentes do mês = obrigações variáveis reconhecidas no mês
                           ainda não incluídas nos variáveis realizados
verba variável restante = receita considerada - fixos considerados
                         - compromissos anteriores considerados
                         - proteção - despesas variáveis realizadas
                         - variáveis pendentes do mês
```

Fixos pagos devem ser excluídos do conjunto variável; despesas extraordinárias são subtraídas uma vez. Parcelas variáveis do mês comprometem verba mesmo antes da quitação, sem classificá-las artificialmente como fixas. A liquidação substitui pendente por liquidado; não aumenta o total comprometido. Se um compromisso de 100 for pago por 120 e encerrado, considerar 120, não 220. Pagamento parcial conserva apenas a obrigação restante, sem somar pagamento de cartão como nova despesa. Compromissos anteriores são reservados separadamente: pendência anterior 200, seguida de pagamento 80, mantém impacto mensal 200 (80 pagos + 120 pendentes). Não incluir esses 80 novamente em fixos ou variáveis do mês. O contrato técnico deve distinguir despesa reconhecida de pagamento e impedir sobreposição dos conjuntos.

Para essenciais variáveis, a previsão remanescente não deve ser subtraída da verba diária total e novamente computada como realizado. Por categoria, remanescente = máximo entre zero e orçamento essencial menos realizado elegível. Margem livre = verba variável restante menos soma dos remanescentes essenciais. Exemplo aprovado: verba 1000 e reserva essencial 600 deixam margem 400; gasto essencial de 100 deixa verba 900, reserva 500 e margem 400. Não compensar automaticamente excesso de uma categoria com previsão de outra.

Déficit deve conservar o valor negativo no resultado; eventual valor gastável zero é uma apresentação separada. Projeção favorável não elimina déficit do orçamento atual. Esse déficit não prova insuficiência de caixa: saldo anterior de 5000, receita mensal zero e despesa paga de 100 podem produzir orçamento -100 e saldo 4900. Não afirmar caixa negativo sem apuração específica de caixa.

Regras numéricas aprovadas: média diária positiva arredondada para baixo nos centavos; resto permanece disponível para recálculo, sem criar dinheiro. Proteção percentual calculada sobre a soma dos recebimentos considerados na respectiva visão e arredondada uma vez para cima nos centavos; não arredondar cada recebimento separadamente. Duas receitas de 0,01 a 50% protegem 0,01, não 0,02. Comparar faixas antes de arredondar o percentual de exibição. Implementação proposta: strings decimais/BigDecimal com precisão exata e modos explícitos; não usar float nas regras.

Calendário aprovado: mês e dias corridos no horário de Brasília, identificador técnico America/Sao_Paulo, rótulo de interface Brasília — Brasil. Hoje integra os dias restantes; no último dia divisor 1. Recorrência em data inexistente cai no último dia do mês e retoma o dia original no mês seguinte. Períodos encerrados não recebem cálculo de limite restante com divisor zero.

Histórico aprovado: registrar avaliação no encerramento de cada dia de Brasília; preservar o resultado que foi apresentado e recalcular indicadores atuais após correção retroativa, identificando a atualização. A execução técnica pode ocorrer após a meia-noite e deve identificar o dia encerrado; retenção e recuperação de execuções perdidas serão especificadas na revisão técnica.

## Alertas e personalidade

Avisar ao piorar de faixa ou surgir déficit; agrupar mudanças da mesma operação, sem repetir uma situação já avisada a cada acesso. Déficit permanece visível; melhora pode gerar mensagem breve. Não transformar cada variação numérica na mesma faixa em novo aviso automático. Definir identidade de evento e proteção contra oscilação na revisão técnica.

Emojis acompanham o texto. Variar vocativos como meu parceiro e meu consagrado sem repetição excessiva. Palavrão é pontual; o conteúdo financeiro permanece correto. Não usar o tom para pressionar consumo nem ridicularizar dificuldade financeira. Exemplos aprovados como direção editorial:

- Atenção: “👀 Meu parceiro, o pé tá pesado nos gastos. Bora conferir?”
- Déficit: “😬 Porra, o mês ficou no vermelho. Bora ver o que ainda tá pendente?”
- Recuperação: “🤝 Aí sim, meu consagrado! Voltamos ao planejado.”
- Folga: “🌿 Boa, criatura! Sobrou mais espaço no mês. Não precisa inventar gasto, hein 😂”
- Compromissos: “🫣 Calma, patrão: esse saldo ainda tem boleto com nome e sobrenome.”

Selecionar mensagem compatível com a visão: projeção de déficit não pode afirmar que o caixa já está negativo. A explicação numérica fica acessível junto do aviso. Mensagens podem ser templates editoriais; a personalidade não exige integração com modelo de IA.

## Entregas e critérios de passagem

### 1. Configuração e planejamento

Lia fecha comportamentos; Atlas define vínculos e contratos; Nilo implementa após revisão. Bento verifica comportamento e Íris verifica isolamento.

Aceite: cadastro progressivo; criar/editar/cancelar previsões; vínculo previsto-realizado sem duplicar; essenciais configuráveis; categoria criada no contexto preserva o formulário; proteção opcional; isolamento por usuário. Aplicar calendário e recebimentos parciais aprovados, com residual remarcável ou encerrável e atraso explícito.

### 2. Cálculo e testes matemáticos

Inv revisa a coerência, Atlas delimita a calculadora, Nilo implementa e Bento revisa resultados independentemente.

Aceite: modelo sem ambiguidade, calculadora determinística, casos de referência e invariantes aprovados, testes unitários e testes da seleção dos dados passando. Nenhuma regra depende de arredondamento do frontend.

### 3. Indicadores, histórico e alertas internos

Nilo constrói sobre resultados validados; Lia revisa entendimento; Bento valida regressões e experiência mobile. Revisão de acessibilidade e layout inclui conteúdo longo, estados vazios, déficit e atualização retroativa.

Aceite: visão atual/projetada identificada; explicação do cálculo; configuração incompleta reconhecida; linguagem acolhedora; cores não são o único sinal; avisos sem repetição a cada acesso. Simulador não integra a primeira entrega proposta.

### 4. WhatsApp opcional

Contrato próprio de ativação, gatilhos, frequência, conteúdo, custo, desativação e entrega. Íris revisa privacidade e Nexo confiabilidade; Nilo implementa depois dos indicadores internos.

Aceite: módulo desativado não envia mensagens; falha externa não impede registros financeiros; processamento em fila com prevenção de duplicidade, repetição limitada e acompanhamento de entrega. Aprovação desta separação não autoriza contratar serviço pago ou enviar mensagens reais.

## Matriz transversal de testes

Estes testes são requisitos futuros; não foram executados para a nova funcionalidade. E4 cobre matemática pura; E2/E3/E5 cobrem seleção, vínculos, concorrência e liquidação; E6 cobre histórico e avisos. A calculadora não depende de testes de notificações para seu aceite isolado. Consultar o roteiro detalhado.

- Caso aritmético: 3000 de receita - 1000 de fixos - 300 de proteção - 700 variáveis = 1000 restantes.
- 100 / 3 dias: teto exibido 33,33; três tetos não ultrapassam 100; resto de 0,01 é preservado.
- Zero, 0,01, déficit, valores máximos e agregados grandes; nenhuma divisão por zero.
- Meses de 28/29/30/31 dias, ano bissexto, virada de ano, primeiro/último dia e fronteiras do fuso.
- Proteção fixa/percentual, mudança de receita e arredondamento fracionário em centavos.
- Previsto realizado com valor diferente; fixo pago/pendente; vínculo repetido; pagamentos parciais conforme decisão.
- Receita prevista 1000 e recebida 600: atual 600, residual previsto 400, projeção do mesmo mês 1000, não 1600. Encerrar residual deixa 600; remarcar residual move somente 400; atraso não troca sua data automaticamente.
- Essencial orçado 600 e ritmo projetado 750: projeção total 750, não 1350. Com ritmo 450, projeção 600. Realizados não são adicionados novamente ao total projetado.
- Dívida anterior 200: reserva atual 200; pagar 80 conserva impacto mensal de 200 e deixa 120 pendentes. Quitação não cria nova despesa; mês seguinte transporta apenas o residual ainda aberto.
- Essencial planejado 600 e realizado 200: total reservado/consumido 600, não 800; estouro da previsão continua visível.
- Extraordinário descontado uma vez e excluído da extrapolação diária.
- Parcelas e fatura sem duplicidade; estornos/reembolsos não viram renda sustentável; transferência neutra.
- Fatura 500 paga em 300 conserva pendência 200; encargo confirmado 15 gera obrigação 215, com apenas 15 de despesa nova. Transporte mensal não recria despesa.
- Duas parcelas futuras de 100 antecipadas por 190 liberam 200 futuros e comprometem 190 no mês atual.
- Recorrência dia 31 vira último dia de fevereiro e volta ao dia 31 em março; fechamento diário usa Brasília e é idempotente.
- Aviso compatível com atual/projetado; evento repetido não duplica notificação; personalidade não altera cálculo.
- Exclusão/restauração, correções retroativas e isolamento entre usuários.
- Fronteiras exatas de 90% e 100%; denominador zero/negativo; primeiros dois dias; déficit atual com projeção positiva.
- Conservação dos valores; mesma entrada e relógio produzem mesmo resultado; despesa adicional não aumenta verba no mesmo instante e premissas; receita adicional não reduz verba com proteção percentual válida e premissas constantes.

## Revisão seguinte: especificações a tornar verificáveis

As decisões explicitamente aprovadas da rodada estão preservadas. A revisão identificou escolhas complementares de produto; o pacote abaixo reúne essas propostas antes da implementação dependente. Não considerar o contrato inteiro fechado apenas porque as respostas anteriores foram consolidadas. Atlas define contratos e vínculos; Inv e Lia conferem fidelidade às decisões; Bento revisa resultados esperados.

1. Formalizar a projeção aprovada pelo máximo entre orçamento e ritmo por essencial; delimitar o denominador das faixas, conjuntos de extraordinários e dias decorridos, incluindo casos sem base.
2. Modelar fluxo de caixa e orçamento para dívidas anteriores e liquidação de cartão usando substituição da reserva pelo pagamento; provar conservação com casos entre meses.
3. Modelar vínculo parcial, residual, remarcação, encerramento e atraso dos recebimentos; impedir duplicação por repetição da operação.
4. Detalhar deduplicação e oscilação dos alertas, retenção e recuperação do fechamento diário. Reutilizar infraestrutura existente após inspeção.
5. Validar estado vazio, configuração incompleta e coerência visual; a média disponível deve identificar se inclui essenciais, e a margem livre deve permanecer distinta.

WhatsApp permanece em contrato próprio posterior. Não reabrir decisões já aprovadas, salvo contradição demonstrada; alterações materiais exigem explicação ao Chefe.

Propostas de implementação não substituem aprovação de produto. Não declarar cobertura completa apenas porque testes isolados passam; validar também os dados selecionados, vínculos e apresentação.

## Fechamento para publicação — escopo confirmado e proposta complementar

O Chefe confirmou a intenção de concluir a implementação para publicação, sem WhatsApp e OFX por enquanto. Não implementar conectores, importar arquivos OFX, enviar mensagens externas ou exigir credenciais desses módulos nesta entrega. Permanecem em escopo configuração, previsões, compromissos, cartões/parcelas, cálculos, dashboard, histórico, alertas internos e validação para publicação.

**Status das regras a seguir: aprovadas conjuntamente pelo Chefe em 2026-09-08, após pareceres de Ratsel e Inv.** P1–P6 passam a orientar a implementação por etapas. A aprovação não declara o código pronto: cada fatia ainda exige implementação, revisão e evidências. Decisões anteriores sobre parciais, excesso e desfazimento permanecem válidas.

### P1 — Recebimentos e recorrência

- Cada lançamento real de receita pode ser vinculado integralmente a uma única previsão. Uma previsão aceita vários recebimentos parciais. Neste MVP não repartir um lançamento entre previsões. Vincular não cria receita nova; apenas associa registros do mesmo usuário.
- Pendência = máximo entre zero e previsto menos soma dos recebimentos vinculados ativos. Excedente é informativo. Ao desfazer 1200 recebidos para previsão 1000, a pendência retorna a 1000, não 1200. Essa fórmula torna precisa a regra já aprovada de desfazimento.
- Restaurar um lançamento não restaura silenciosamente o vínculo: confirmar destino e conferir versão/estado. Encerramento manual ou remarcação seguem a confirmação já aprovada. Efeitos financeiros e do vínculo devem ser atômicos; cancelar a confirmação deixa ambos intactos.
- Após um parcial, remarcar afeta somente o residual; valores e datas de recebimentos efetivos ficam preservados. A visão mensal seleciona cada recebido por sua data efetiva e o residual por sua data prevista, sem mover dinheiro retrospectivamente.
- Recorrência oferece esta ocorrência ou esta e próximas sem liquidação. Ocorrências parcialmente ou totalmente liquidadas ficam excluídas da edição em lote, com indicação explícita. Guardar dia original (31 → 28/29 → 31); a mesma série/data não gera duplicata.

### P2 — Reembolsos e essenciais

- Reembolso de despesa do mesmo mês reduz a despesa vinculada, sem ser renda nova. Reembolso de mês anterior aumenta patrimônio/caixa, mas não a renda disponível do mês atual nem a base da proteção. Preservar avaliações históricas e identificar correções. Total reembolsado não ultrapassa a despesa elegível; excesso exige classificação separada, não conversão silenciosa em receita.
- Cancelar obrigação não paga somente libera sua reserva, sem gerar entrada de caixa.
- Orçamento essencial é o total da categoria, incluindo extraordinários e parcelas/obrigações variáveis. Total projetado da categoria = `max(orçamento, ordinário projetado + extraordinários + obrigações fora do ritmo)`. Esses conjuntos não se sobrepõem.
- Exemplo: orçamento 600, ordinário projetado 450 e extraordinário 300 resultam em 750, não 900. Um pagamento substitui obrigação pendente pelo realizado correspondente, sem aumentar o total considerado.

### P3 — Projeção e faixas

- Base variável = receita considerada na visão − proteção − fixos considerados − compromissos anteriores considerados. Não subtrair variáveis dessa base antes da comparação. Percentual = variável total projetado / base variável. Receita 3000, fixos 2000 e variáveis projetadas 850 dão 85%, não 95%.
- Separar visão atual (recebido) e projetada (recebido + previsto remanescente). Receitas futuras não ocultam déficit atual. Saldo patrimonial não substitui essa base.
- Taxa ordinária = realizados ordinários dos dias completos / número de dias completos de Brasília. Ordinário projetado = realizado dos dias completos + máximo entre realizado hoje e taxa + taxa × dias após hoje. Hoje não alimenta a taxa até encerrar; o valor observado substitui a estimativa de hoje quando maior. Em mês de 30 dias, dois dias completos somando 200 e hoje 150 projetam 3050; hoje zero projeta 3000.
- Essa fórmula de ritmo vale somente para o mês corrente. Mês encerrado mostra total realizado/reconhecido, sem extrapolar; mês futuro mostra planejamento, sem inventar taxa observada ou diagnóstico por ritmo.
- Aplicar o ritmo tanto aos essenciais ordinários quanto aos demais variáveis ordinários. Fixos, extraordinários e parcelas identificadas não entram na taxa. Usar precisão exata até a agregação e arredondar o total projetado para cima uma única vez nos centavos; não arredondar taxas intermediárias.
- Dias 1 e 2 não têm diagnóstico por ritmo; mostrar fatos e compromissos. Déficit conhecido é mostrado imediatamente. Configuração incompleta gera indicação de dados faltantes, não interpretação como zero confirmado.
- Base positiva permite percentual: até 90% sob controle; acima de 90% até 100% equilíbrio; acima de 100% fora da faixa planejada. Comparar sem arredondamento visual. Base zero com variáveis zero não tem base para comparação; base não positiva com gastos ou déficit conhecido mostra insuficiência orçamentária, sem percentual artificial. Projeção não prova saldo bancário negativo.

### P4 — Cartões, parcelas e pagamentos

- Calendário de fechamento/vencimento sugere a fatura; o usuário confirma o primeiro vencimento da compra. Compra no dia do fechamento sugere a próxima fatura. Alterar calendário não reescreve compras existentes.
- Pagamento parcial aloca primeiro às obrigações de vencimento mais antigo; empate por identificador estável. Encargos confirmados são itens próprios, sem prioridade implícita. Exibir distribuição antes de confirmar. Rejeitar pagamento maior que a dívida selecionada neste MVP, sem criar crédito artificial.
- Exemplo: duas parcelas de 100, pagamento de 150 → primeira quitada, segunda com 50 pendentes. Preservar essa alocação para reversão; não recalcular reversão pela ordenação atual.
- Antecipação seleciona parcelas explicitamente. Ratear desconto proporcional aos resíduos selecionados, arredondar descontos para baixo e distribuir centavos restantes pelas maiores frações, desempate por ID. Desconto deve estar entre zero e total selecionado. Manter bruto, desconto, líquido e vencimentos originais. Duas parcelas de 100 antecipadas por 190 resultam em 95 líquidos por parcela, 190 atuais e 200 liberados no futuro.
- Estorno de compra reduz primeiro a parte pendente da obrigação identificada. Parte já paga torna-se crédito do cartão, não renda nem dinheiro em conta. Aplicar crédito a outra fatura exige associação explícita. Compra 100, paga em 60, estornada em 100: elimina pendência 40 e cria crédito 60, sem aumentar caixa. Estorno/reembolso acumulado não ultrapassa o valor elegível.

### P5 — Histórico e avisos internos

- Fechamento diário identificado por usuário, data de Brasília e versão das regras. Reexecução não duplica. Correção cria revisão e preserva resultado registrado; reconstrução tardia é identificada como reconstruída, nunca como algo exibido no passado.
- Não apagar automaticamente avaliações nesta entrega; retenção e exclusão da conta exigem política específica antes de eventual SaaS. Isso não altera a janela de restauração de registros financeiros já aprovada.
- Um aviso interno consolidado por usuário/dia/visão para mudanças de situação, atualizado com situação atual e pior faixa observada. Não criar aviso por acesso, replay ou cada oscilação. Déficit conhecido atualiza imediatamente o aviso. Recuperação atualiza o mesmo item, sem fingir que o déficit anterior não existiu.
- Mostrar período, visão atual/projetada, horário e motivos; cores sempre acompanhadas de texto. Personalidade e emojis aprovados permanecem, sem confundir orçamento com caixa. Nenhum envio externo nesta entrega.

### P6 — Critérios de conclusão e publicação

- Cada fatia termina com testes de regras e falhas; integração compara cenários reais persistidos com resultados de referência. Matriz inclui parcial, excesso, desfazimento, residual entre meses, estorno, crédito, antecipação, zero, déficit e centavos.
- Validar isolamento por usuário em todos os endpoints, transações/auditoria, repetição e concorrência no mecanismo de banco usado na hospedagem; SQLite sozinho não comprova esse último requisito.
- Validar navegação, botões, formulários, mensagens, modais, teclado e layouts em desktop e celular real. Build não substitui validação visual. Não declarar controles operantes apenas por existirem na tela.
- Antes de publicar: revisar diff e segredos, executar testes/build, conferir migrações em banco de teste compatível, preparar backup e retorno de versão, verificar autenticação Google/logout/HTTPS, agendador e filas necessários. Não executar seeder de desenvolvimento, apagar dados ou alterar credenciais de produção como atalho de implantação.
- Publicar somente o conjunto verificado e executar smoke test depois. WhatsApp e OFX não bloqueiam nem entram implicitamente no deploy. Conclusão exige evidências; não há promessa de ausência absoluta de defeitos.
