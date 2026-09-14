# Revisão do contrato financeiro

## Escopo e resultado

Maia consolidou revisões independentes de Inv (finanças), Lia (produto) e Bento (verificabilidade). Foram lidos o contrato e as skills pertinentes; Maia também inventariou modelos, enums e ações existentes para verificar a separação do domínio de cartões. Não houve implementação, teste automatizado ou publicação nesta revisão.

O anúncio anterior de contrato fechado significava encerramento da rodada de respostas, mas foi amplo demais para expressar prontidão de implementação. As decisões permanecem aprovadas; a revisão identificou lacunas reais, algumas com escolha de produto. Não afirmar ausência de lacunas.

Referências: [contrato funcional](FINANCIAL_PLANNING_CONTRACT.md) e [etapas detalhadas](FINANCIAL_PLANNING_STAGES.md).

## Achados e encaminhamento

| ID | Achado e evidência | Tratamento | Etapas afetadas |
| --- | --- | --- | --- |
| R01 | Parcela variável 200 vence no mês; receita 1000. Fórmula anterior podia liberar 1000 por só subtrair variáveis realizadas. | Corrigido no contrato: obrigação pendente do mês também compromete verba; conjuntos exclusivos. Aceite deve retornar 800 antes e depois de pagar a parcela. | E3–E5 |
| R02 | Patrimônio anterior 5000, despesa mensal 100 e receita zero não demonstram caixa negativo. | Corrigida nomenclatura: orçamento -100 não equivale a caixa negativo; mensagem precisa respeitar origem do indicador. | E4–E6 |
| R03 | Matriz da calculadora exigia testes de histórico/avisos de entrega posterior. Cartões não possuíam fatia própria. | Corrigido roteiro: E3 cartões, E4 cálculo puro, E5 integração, E6 histórico; testes atribuídos por responsabilidade. | Todas |
| R04 | Duas receitas 0,01 a 50% geram proteção 0,01 sobre total ou 0,02 se arredondadas separadamente. | Explicitado arredondamento único sobre base agregada, coerente com percentual dos recebimentos. | E4 |
| R05 | Despesa em agosto reembolsada em setembro: caixa aumenta, mas destino orçamentário não foi aprovado. | Decisão complementar: separar reembolso em dinheiro de cancelamento de obrigação e definir se alimenta verba do mês ou apenas patrimônio. Não tratar como receita sustentável automaticamente. | E2–E5 |
| R06 | Receita 3000, fixos 2000, variáveis projetadas 850: total/renda dá 95%; variáveis/verba variável dá 85%. | Formalizar proposta alinhada à comparação variável aprovada: numerador variável projetado e denominador verba destinada a variáveis antes de realizá-las; definir dívidas, proteção, zero/negativos e exemplo para revisão. Não usar verba restante como denominador por acidente. | E0, E4–E6 |
| R07 | Essencial orçado 600 inclui extraordinário 300; ordinário projetado 450. max(600,450)+300=900; max(600,450+300)=750. | Confirmar se orçamento inclui extraordinários. Recomendação de Inv a formalizar por Lia: orçamento total inclusivo e extraordinário somado uma vez dentro do total comparado, sem extrapolá-lo. | E0, E1, E4 |
| R08 | Ritmo observado não define dias decorridos nem projeção dos não essenciais. Reserva usa orçamento e projeção usa máximo, sem contrato completo para parcelas pendentes. | Especificar entradas/saídas e fórmulas exatas, classificando cada evento. Estados sem base, dados incompletos e denominador zero precisam resultados esperados. | E0, E4–E5 |
| R09 | Recebimento de 600 frente a 1000 pode quitar previsão ou deixar 400; exclusão após remarcação e sobrepagamento não têm efeito explícito. | Fechar jornada parcial versus encerrado, excesso, desvínculo/exclusão/restauração; não alterar residual silenciosamente. Preservar parciais aprovados. | E2, E5 |
| R10 | Edição de recorrência não diz ocorrência versus série; alteração de proteção/orçamento não tem vigência definida. | Apresentar alcance temporal explícito. Propor mudanças futuras preservando realizados; alterações retroativas exigem indicação. Materializar política antes da UI dependente. | E1–E2, E6 |
| R11 | Fatura parcial reúne parcelas, juros e categorias; ordem de alocação e desconto de antecipação influenciam resíduos e relatórios. | Atlas propõe contratos de alocação com conservação de centavos; Lia/Inv validam efeitos. Fechamento/vencimento de cartão e estorno após pagamento também precisam critérios. | E3–E5 |
| R12 | Duas requisições distintas podem consumir simultaneamente o mesmo residual; replay sozinho não cobre concorrência. | Exigir vínculos atômicos, limites de alocação, idempotência e testes do banco compatível com produção. Definir conflitos e garantir ausência de escrita parcial. | E2–E3, E5 |
| R13 | Dia sem fechamento, editado antes da recuperação, não pode ser reconstruído fielmente só com dados atuais. | Registrar proveniência: avaliação registrada versus reconstruída. Preservar versões existentes, não afirmar que resultado reconstruído foi exibido; definir retenção, recuperação e revisão de configuração usada. | E6 |
| R14 | Faixa oscila entre duas situações; cada transição pode disparar apesar de replay protegido. | Especificar identidade, janela de agrupamento e resolução do aviso, preservando estados visíveis. WhatsApp mantém contrato próprio posterior. | E6–E7 |

## Propostas que precisam decisão complementar

Atualização de R09 em 2026-09-08: o Chefe aprovou que recebimento acima do previsto encerra a previsão, considerando integralmente o realizado uma única vez (1000 previstos/1200 recebidos: 1200 realizados, zero pendente). Isso fecha somente excesso; exclusão, desvínculo e restauração continuam pendentes.

Complemento posterior de R09: aprovado devolver à pendência o recebimento desfeito; se houve encerramento manual ou remarcação, pedir confirmação do destino antes de reabrir/mudar datas. Histórico preservado. Ainda não há implementação do vínculo ou de sua restauração; a primeira entrega de E2 se restringe a cadastrar, listar e cancelar previsões sem recebimentos vinculados.

Atualização de implementação em 2026-09-08: o primeiro incremento do vínculo foi entregue localmente. Parciais, excedente, deduplicação, isolamento, exclusão/estorno e restauração sem reativação silenciosa têm testes automatizados. O revínculo explícito após restauração e a recorrência continuam pendentes; portanto R09 não está integralmente encerrado.

Não perguntar novamente o que já foi aprovado. Levar ao Chefe apenas mudanças materiais, em exemplos curtos:

1. R05: um reembolso recebido em outro mês financia gastos daquele mês? Recomendação inicial conservadora: patrimônio, com correção vinculada à despesa original; cancelamento de obrigação aberta libera somente a obrigação correspondente. Essa recomendação ainda não é regra.
2. R07: orçamento essencial representa o total da categoria incluindo extraordinários? Recomendação: sim; extraordinário compõe o total uma vez e não é extrapolado.
3. R09–R11: equipe deve preparar propostas concretas de alcance de edição, liquidação e excesso antes de pedir decisões; escolhas que mudam compromissos não são mero detalhe de banco.

R06 e R08 exigem especificação matemática e exemplos antes de implementação, mesmo sem mudar a intenção já aprovada. Autonomia técnica não autoriza alterar o significado do diagnóstico.

## Casos de aceite prioritários

- E3/E5: dívida 500 no mês A, pago 300; B reserva 200, paga 80; C carrega 120. Histórico da despesa original preservado.
- E2/E5: previsão 1000, recebido 600; replay mantém 400; remarcação move apenas residual; dois pagamentos concorrentes seguem política de alocação fechada.
- E4: verba 1000, essencial 600, gasto 100 deixa 900/500/400; projeção 750 não adiciona novamente gasto 100.
- E4: 0,01 + 0,01 a 50% protege 0,01; 100/3 conserva resto; testar exatamente 90%, 100% e imediatamente acima sem usar arredondamento visual.
- E4/E5: parcela pendente do mês não desaparece da verba; fixo/variável/extraordinário são identificados por evento e vínculo, não apenas categoria.
- E6: preservar avaliação registrada, marcar reconstrução, não duplicar fechamento, não repetir alerta por acesso ou replay.
- E1–E7: independência verificada pelos aceites de cada etapa, falha do canal externo não bloqueia lançamentos e canal desligado não envia.

## Próxima execução coordenada

Pacote de fechamento: P1–P6 foram reunidas no anexo “Fechamento para publicação” do contrato, com pareceres consultivos de Inv, Atlas e Ratsel, e aprovadas conjuntamente pelo Chefe em 2026-09-08. R05–R14 têm direção funcional resolvida pelo pacote, mas só serão encerradas após implementação e testes. WhatsApp e OFX foram explicitamente retirados da publicação atual pelo Chefe.

Maia mantém documentação e atribui arquivos antes de cada trabalho. Atlas prepara E0 com Inv e Lia consultados; Bento revisa os resultados de referência. Nilo começa somente a fatia com contrato suficiente. Íris e Nexo revisam as fronteiras de acesso e recuperação nas etapas pertinentes. WhatsApp não bloqueia nenhuma etapa anterior. Não é necessário ativar todos simultaneamente.

Conclusão da revisão documental: etapas agora possuem escopo e dependências explícitos. R01–R04 receberam correções de documentação; demais itens seguem rastreados até resolução. Verificação desta rodada: leitura cruzada dos pareceres e diff sem erros de whitespace. A revisão não certifica código nem resultados matemáticos executados.
