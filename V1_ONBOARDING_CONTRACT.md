# Contrato V1 — onboarding e primeiros passos

## Estado e objetivo

Aprovado pelo Chefe em 2026-09-13 para integrar o fechamento do V1 antes da publicação. Este contrato complementa `FINANCIAL_PLANNING_CONTRACT.md` sem alterar as regras matemáticas, contábeis ou de cartão já aprovadas.

Objetivo: impedir que o primeiro acesso termine em um dashboard vazio sem orientação. O FinanSys deve conduzir o usuário pela configuração financeira mínima, explicar o próximo passo e reconhecer automaticamente o que já foi concluído.

## Princípios aprovados

- O onboarding é uma camada de experiência sobre dados reais do FinanSys; não cria uma segunda fonte de verdade financeira.
- O progresso de cada etapa deve ser derivado, sempre que possível, da existência/estado dos dados reais do usuário. Não usar checkboxes manuais como prova de conclusão de uma configuração financeira.
- O usuário não deve preencher um formulário gigante. O fluxo deve ser progressivo, com etapas curtas e ações contextuais.
- Separar configuração **essencial** de configuração **recomendada**. Recursos que o usuário pode legitimamente não usar, como cartão ou meta, não podem bloquear o sistema.
- Ausência de configuração continua diferente de zero confirmado, conforme o contrato financeiro existente.
- O onboarding não pode inventar saldo, renda, orçamento, projeção ou conclusão financeira.
- O V1 não depende de IA para orientar o usuário. A orientação é determinística e baseada no estado real do sistema.
- O componente de ajuda pode usar provisoriamente a identidade visual da Tsuki (por exemplo, ícone/emoji 🌙), sem acoplar o V1 ao projeto de IA Tsuki.
- O usuário pode fechar/adiar orientações não essenciais e reabrir posteriormente a área de primeiros passos.

## Fluxo de primeira entrada

No primeiro acesso elegível, apresentar um `Setup Financeiro Inicial` antes de abandonar o usuário no dashboard vazio. O fluxo deve permitir navegação segura entre etapas e preservar o que já foi cadastrado.

Ordem de referência:

1. **Configuração básica** — apresentar/confirmar dados necessários ao funcionamento do módulo, reutilizando defaults seguros já existentes (por exemplo, moeda BRL e calendário/fuso já contratados quando aplicável).
2. **Primeira conta e posição inicial** — orientar criação da primeira conta financeira e, quando o modelo atual exigir, informar a posição/saldo inicial sem fabricar movimentação incompatível com os contratos existentes.
3. **Renda/recebimentos** — orientar configuração de renda ou recebimentos previstos. Receita prevista permanece previsão e nunca é apresentada como dinheiro disponível antes de recebida.
4. **Compromissos conhecidos** — orientar cadastro dos compromissos fixos/recorrentes relevantes.
5. **Categorias/essenciais** — orientar seleção ou cadastro das categorias importantes e essenciais variáveis.
6. **Planejamento** — levar o usuário à configuração mensal já existente e explicar, com os dados disponíveis, o que falta para os indicadores terem interpretação confiável.

A implementação pode ajustar a granularidade das telas para reutilizar componentes existentes, desde que preserve a semântica e os critérios deste contrato.

## Essencial versus recomendado

O Codex deve primeiro mapear o modelo atual e propor a menor definição de `ready_for_use` coerente com o sistema existente. A intenção aprovada é:

- **Essencial:** somente o mínimo sem o qual o núcleo financeiro não consegue ser usado de maneira compreensível/correta, como possuir a estrutura financeira inicial necessária para registrar e interpretar movimentações.
- **Recomendado:** renda/recebimentos previstos, compromissos, essenciais/categorias, cartão, metas e demais enriquecimentos que melhoram planejamento e análise, mas que podem não existir para todo usuário.

Não transformar cartão, meta, renda fixa ou qualquer hipótese de perfil financeiro em requisito universal.

## Checklist persistente de primeiros passos

Após o wizard inicial, disponibilizar um ponto de ajuda persistente e discreto no layout autenticado, preferencialmente flutuante no V1, com identidade provisória da Tsuki (🌙 ou equivalente acessível).

Ao abrir, mostrar:

- progresso das etapas essenciais e recomendadas;
- estado `concluído` derivado dos dados reais;
- uma explicação curta do motivo de cada etapa;
- CTA `Fazer agora` que leva ao contexto/tela correta;
- indicação clara quando a configuração essencial estiver pronta;
- possibilidade de reabrir os primeiros passos posteriormente.

O componente não deve competir com alertas financeiros nem bloquear navegação normal depois que o mínimo essencial estiver atendido.

## Fonte de verdade e persistência

Evitar colunas booleanas específicas para cada passo (`completed_account_step`, `completed_income_step`, etc.) quando a conclusão puder ser inferida dos dados financeiros.

Se persistência própria de onboarding for necessária, ela deve guardar apenas estado de experiência que não pode ser derivado com segurança, por exemplo:

- `user_id`;
- `started_at`;
- `completed_at` para o onboarding essencial;
- `dismissed_at`/estado equivalente para orientação dispensável;
- `current_step` ou último contexto visitado, se necessário para retomada.

A definição final de tabela/campos cabe ao Atlas/Codex após inspeção do modelo existente. Dados financeiros continuam sendo a fonte de verdade da conclusão das etapas.

## Reentrada, idempotência e mudanças posteriores

- Recarregar a página ou repetir uma ação não pode duplicar contas, saldos, previsões ou compromissos.
- Se o usuário já possui dados válidos (conta antiga/importada/seed de ambiente legítimo), o onboarding deve reconhecer as etapas correspondentes como concluídas.
- Se um dado que sustentava uma etapa for removido posteriormente, o checklist pode voltar a indicar configuração incompleta quando isso for relevante; não deve apagar silenciosamente dados de progresso histórico sem necessidade.
- Usuários existentes na data da implantação não devem ficar presos em um bloqueio incompatível com dados já cadastrados. O estado deve ser inferido e a experiência deve degradar para orientação/checklist quando o mínimo já existir.

## UX e acessibilidade

- Desktop e mobile devem ter o mesmo significado e progresso.
- Não depender apenas de cor para indicar concluído/pendente.
- Botão flutuante precisa de rótulo acessível, foco por teclado e área de toque adequada.
- O usuário deve entender por que uma etapa é necessária e o que acontecerá ao clicar em `Fazer agora`.
- Não usar linguagem financeira que confunda previsão com disponibilidade real.
- Estados vazios do dashboard devem apontar para os primeiros passos em vez de apenas mostrar zeros sem contexto.

## Fora do escopo deste contrato V1

- IA generativa da Tsuki.
- Chat da Tsuki.
- Recomendações financeiras produzidas por modelo de IA.
- Daily Financial Engine V2, metas avançadas e score V2.
- WhatsApp e OFX.
- Tour visual complexo dependente de biblioteca nova, salvo necessidade técnica aprovada.

## Responsabilidades dos agentes

- **Maia:** sequência, dependências e gate de fechamento.
- **Lia:** fluxo, linguagem, obrigatoriedade e critérios de aceite.
- **Atlas:** fonte de verdade, persistência mínima e contratos de dados.
- **Íris:** isolamento por usuário e exposição segura de estado.
- **Nilo/Codex:** implementação somente após mapear/reutilizar componentes e ações existentes.
- **Bento:** testes de primeira entrada, usuário existente, retomada, conclusão automática e regressões.
- **Nexo:** idempotência quando o wizard dispara mutações financeiras existentes.

## Critérios de aceite V1

1. Usuário novo não termina o primeiro acesso sem orientação acionável.
2. Existe fluxo progressivo para configurar o mínimo financeiro e acessar as configurações recomendadas.
3. Checklist reconhece automaticamente etapas concluídas a partir dos dados reais.
4. Nenhum checkbox manual pode falsificar a existência de configuração financeira.
5. `Fazer agora` direciona para o contexto correto e o retorno reflete automaticamente a conclusão.
6. Usuário existente com dados válidos não é indevidamente bloqueado pelo novo onboarding.
7. Cartão, meta e outras configurações opcionais não impedem `ready_for_use`.
8. Receita prevista nunca é convertida em saldo/disponibilidade pelo onboarding.
9. Componente de ajuda/primeiros passos permanece reabrível após o wizard e funciona em desktop/mobile/teclado.
10. Testes cobrem isolamento, idempotência relevante, primeira entrada, retomada, usuário preexistente e conclusão derivada.
11. Nenhuma regra do Daily Financial Engine V2 é introduzida silenciosamente.
12. Suíte existente, Pint e build permanecem verdes.

## Gate de implementação

Antes de escrever migrations/componentes, o Codex deve inspecionar o modelo atual de contas/saldos, configuração mensal, previsões, compromissos, categorias e layout autenticado para reutilizar os fluxos existentes. Se a estrutura atual já representar um passo, o onboarding deve orquestrar esse fluxo em vez de criar uma implementação financeira paralela.
