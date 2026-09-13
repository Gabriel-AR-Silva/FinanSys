# Proposta — Daily Financial Engine v2

> **Status:** proposta de evolução de produto e regra de negócio. **Não implementada e não aprovada como contrato definitivo.**
>
> **Objetivo:** registrar a evolução do modelo atual de "verba/média diária" para um motor financeiro diário capaz de separar custo, comportamento, capacidade, planejamento, economia e impacto em metas sem dupla contagem.
>
> Esta proposta deve ser revisada pelos agentes responsáveis antes de qualquer implementação. O contrato vigente continua sendo `FINANCIAL_PLANNING_CONTRACT.md` até que as decisões abaixo sejam validadas e incorporadas formalmente.

---

## 1. Contexto e mudança de direção

O FinanSys já possui planejamento mensal, visões atual/projetada, compromissos fixos, despesas variáveis, proteção, essenciais, previsões de recebimento, cartões, histórico diário e uma verba diária calculada.

A evolução proposta parte de uma mudança conceitual importante:

- **antes:** a interface podia convergir para um único valor diário de referência;
- **agora:** o sistema deve distinguir vários conceitos diários, porque "quanto eu gastei", "quanto minha vida custa", "quanto posso gastar" e "quanto decidi gastar" são perguntas diferentes;
- **resultado esperado:** o FinanSys deixa de ser somente um registrador/planejador e passa a funcionar como um **motor de decisão financeira pessoal**.

O termo recomendado para o conjunto é **Daily Financial Engine / Motor Financeiro Diário**. "Média diária de gasto" passa a ser apenas uma de suas métricas.

---

## 2. Problema do modelo de uma única média

Uma única média diária mistura fenômenos diferentes e pode levar a interpretações erradas.

Exemplo:

- receita mensal considerada: R$ 5.000;
- compromissos fixos: R$ 2.000;
- proteção/meta: R$ 1.000;
- verba variável disponível: R$ 2.000;
- referência de 30 dias: R$ 66,66/dia de capacidade variável teórica;
- gasto variável real observado: R$ 48/dia.

Os dois valores são verdadeiros, mas respondem a perguntas diferentes. O sistema não deve apresentar R$ 48 e R$ 66,66 como se fossem a mesma métrica.

---

## 3. Métricas principais propostas

### 3.1. Custo diário estrutural

Pergunta respondida:

> **Quanto minha estrutura de vida custa por dia, mesmo quando eu não faço uma compra hoje?**

Deve representar a equivalência diária dos compromissos estruturais elegíveis do período.

Exemplos conceituais:

- aluguel/moradia;
- internet;
- financiamento;
- assinaturas;
- outros compromissos recorrentes classificados como estruturais.

O usuário pode passar um dia sem realizar nenhuma transação e ainda assim possuir custo econômico estrutural.

**Regra importante:** custo estrutural diário não deve criar lançamentos fictícios. É um indicador derivado dos compromissos já reconhecidos.

### 3.2. Gasto variável diário realizado

Pergunta respondida:

> **Quanto estou efetivamente consumindo no cotidiano?**

Deve considerar somente despesas variáveis realizadas e elegíveis, respeitando as regras existentes de neutralização de transferências, estornos, reembolsos e pagamento de fatura.

Pode ser apresentado por:

- dia;
- média dos últimos N dias elegíveis;
- média do mês até o momento;
- categoria;
- comparação com períodos anteriores.

### 3.3. Custo diário total equivalente

Pergunta respondida:

> **Quanto minha vida está custando por dia quando combino estrutura + comportamento variável?**

Conceito inicial:

```text
custo diário total equivalente = custo diário estrutural
                               + gasto variável diário normalizado
```

A fórmula exata e o período de normalização devem ser aprovados pelo agente financeiro antes de implementação.

### 3.4. Orçamento diário planejado

Pergunta respondida:

> **Quanto eu decidi que quero gastar por dia?**

Não é necessariamente igual à capacidade financeira máxima.

Pode derivar de:

- orçamento configurado pelo usuário;
- verba variável restante;
- dias restantes do período;
- metas/proteções;
- limites percentuais definidos na configuração.

O orçamento diário é uma referência de planejamento, não autorização para consumo e não limite transacional rígido.

### 3.5. Capacidade diária financeira

Pergunta respondida:

> **Quanto eu poderia gastar por dia segundo os recursos e compromissos atualmente considerados?**

Deve ser separada do orçamento escolhido pelo usuário para evitar inflação automática de estilo de vida.

Exemplo:

- capacidade calculada: R$ 160/dia;
- orçamento voluntário: R$ 100/dia;
- gasto real médio: R$ 72/dia.

O sistema não deve concluir que o usuário "deve" gastar R$ 160 apenas porque passou a receber mais.

### 3.6. Margem diária / folga diária

Pergunta respondida:

> **Quanto ficou livre em relação ao orçamento diário definido?**

Conceito básico por dia fechado:

```text
folga do dia = orçamento diário aplicável ao dia - gasto elegível realizado no dia
```

Exemplo:

```text
orçamento do dia = 90,00
gasto do dia     = 80,00
folga do dia     = +10,00
```

Se o gasto for superior ao orçamento:

```text
orçamento do dia = 90,00
gasto do dia     = 110,00
folga do dia     = -20,00
```

**A folga não cria dinheiro.** Ela apenas mede a diferença entre o planejado e o realizado.

---

## 4. O impacto de gastar menos que o orçamento

Esse é um dos pontos centrais desta proposta.

Se ontem o orçamento diário era R$ 90 e o gasto elegível foi R$ 80, houve uma diferença favorável de R$ 10.

Essa diferença pode afetar diferentes indicadores, mas **não deve ser aplicada automaticamente em todos ao mesmo tempo**.

Possíveis efeitos a serem avaliados:

1. aumentar a folga acumulada do mês;
2. melhorar a projeção de fechamento;
3. elevar a margem livre remanescente;
4. antecipar uma meta, caso exista regra explícita de alocação;
5. aumentar o orçamento diário futuro apenas se a política escolhida permitir redistribuição;
6. melhorar uma pontuação de disciplina/eficiência, sem transformar a pontuação em dinheiro.

### 4.1. Regra de não dupla contagem

Os mesmos R$ 10 não podem simultaneamente:

- aumentar saldo;
- aumentar verba disponível;
- ser considerados aporte em meta;
- e ainda aparecer como nova receita.

O sistema deve registrar que **R$ 10 deixaram de ser consumidos em relação ao orçamento**, e cada consequência financeira precisa derivar do mesmo fato-base sem duplicação.

### 4.2. Políticas possíveis para a folga

A política precisa ser explícita e configurável/contratada. Opções candidatas:

#### Política A — Folga acumulada sem redistribuição automática

- R$ 10 ficam registrados como economia contra orçamento;
- orçamento de amanhã não muda automaticamente;
- melhora projeção/margem do período;
- metas só recebem valor se houver transferência/aporte real ou regra contratada.

#### Política B — Redistribuição no restante do mês

- a verba remanescente é recalculada;
- parte da folga pode elevar a referência diária dos dias seguintes;
- o sistema deve mostrar que isso é redistribuição, não nova renda.

#### Política C — Direcionamento para proteção/meta

- uma porcentagem ou todo o excedente pode ser reservado para uma meta;
- exige regra de alocação clara;
- reservar não significa necessariamente movimentar dinheiro entre contas, salvo se existir uma operação financeira real correspondente.

**Recomendação inicial:** Política A como padrão mais conservador. Políticas B/C podem ser opções futuras após validação do modelo.

---

## 5. Gráfico de ritmo e desempenho diário

O dashboard deve permitir visualizar a relação entre orçamento e gasto real ao longo dos dias.

### 5.1. Gráfico principal recomendado

Série temporal com pelo menos:

- linha/área de orçamento diário aplicável;
- linha/barras de gasto diário realizado;
- opcionalmente capacidade diária;
- diferença favorável/desfavorável por dia;
- tendência/média móvel apenas como apoio, nunca como substituta das métricas oficiais.

Exemplo conceitual:

```text
Dia       Orçamento   Gasto    Diferença
10/09       90,00     80,00     +10,00
11/09       90,00     60,00     +30,00
12/09       92,14    105,00     -12,86
```

A razão de eventual mudança do orçamento deve ser explicável: recebimento confirmado, novo compromisso, alteração de meta, redistribuição etc.

### 5.2. Curva acumulada de economia contra orçamento

Um segundo gráfico pode mostrar o acumulado da diferença diária:

```text
economia acumulada(D) = soma das folgas dos dias encerrados até D
```

Exemplo:

- dia 1: +10;
- dia 2: +30;
- dia 3: -12;
- acumulado: +28.

Esse gráfico responde:

> **Ao longo do mês, estou consumindo mais ou menos do que planejei?**

### 5.3. Não confundir com saldo bancário

Economia contra orçamento é um indicador de desempenho. Não é necessariamente saldo disponível em uma conta específica.

---

## 6. Pontuação / índice de eficiência financeira

Pode existir uma pontuação de 0–100 ou classificação equivalente, mas ela deve ser **derivada e explicável**.

Ela não deve recompensar simplesmente "gastar o mínimo possível".

Uma pessoa que deixa de comprar algo essencial não deve receber uma pontuação artificialmente melhor.

Dimensões candidatas:

- aderência ao orçamento planejado;
- cumprimento de compromissos;
- preservação da verba protegida;
- progresso de metas;
- estabilidade do gasto variável;
- percentual de fixos/compromissos sobre receita considerada;
- ocorrência e duração de déficit;
- concentração anormal em categorias;
- tendência de melhora/piora.

Exemplo de saída futura:

```text
Saúde financeira: 78/100

+ gastos variáveis sob controle
+ meta mensal no ritmo
~ custos fixos elevados em relação à renda
- alimentação acima do padrão recente
```

### 6.1. Gate obrigatório

A fórmula da pontuação não deve ser implementada antes de:

- revisão do Inv/agente financeiro;
- teste com cenários extremos;
- definição de pesos;
- definição do comportamento com dados insuficientes;
- garantia de que a pontuação é explicável pelo sistema.

---

## 7. Metas integradas ao motor diário

As metas pessoais devem afetar o planejamento sem serem confundidas com despesas realizadas.

Exemplo:

```text
meta: impressora 3D
valor alvo: 3.000,00
já reservado: 600,00
restante: 2.400,00
prazo restante: 120 dias
necessidade diária teórica: 20,00/dia
```

O motor pode apresentar:

```text
capacidade diária:             110,00
reserva diária sugerida meta:  -20,00
orçamento diário recomendado:   90,00
```

Se o usuário gastar R$ 58 com orçamento de R$ 90, a folga de R$ 32 pode melhorar a projeção da meta.

**Mas:** a meta só deve ser marcada como financeiramente reservada/aportada conforme a política de alocação aprovada. Não transformar automaticamente toda folga em aporte real.

O sistema poderá estimar:

- data prevista de conclusão;
- adiantamento/atraso em dias;
- necessidade diária atualizada;
- impacto de um gasto extraordinário na meta.

---

## 8. Relação entre receita e padrão de gasto

O aumento de receita pode elevar a **capacidade**, mas não deve elevar automaticamente o **orçamento desejado**.

Objetivo: evitar inflação automática de estilo de vida.

O sistema deve conseguir mostrar separadamente:

```text
capacidade diária: 160,00
orçamento diário:  100,00
gasto real médio:   72,00
folga potencial:     88,00
```

A configuração poderá futuramente permitir percentuais, mas a semântica precisa ser clara:

- percentual máximo de consumo da renda;
- percentual protegido;
- percentual direcionado a metas/investimentos;
- percentual de tolerância sobre orçamento.

Nenhum percentual deve ser chamado genericamente de "porcentagem de gasto" se houver mais de uma base possível.

---

## 9. Categorias e análise comportamental

As métricas diárias devem poder ser quebradas por categoria sem alterar o ledger.

Exemplos:

- alimentação;
- bebidas;
- combustível;
- transporte;
- viagem;
- lazer;
- assinaturas;
- outras categorias livres.

Indicadores candidatos:

- média diária por categoria;
- participação da categoria no gasto variável;
- diferença contra orçamento da categoria;
- tendência 7/30/90 dias;
- gasto extraordinário versus recorrente;
- concentração fora do padrão.

Evitar diagnosticar negativamente uma categoria apenas porque um único pagamento grande ocorreu no início do mês.

---

## 10. Estados e visões temporais

O motor deve respeitar a separação já existente entre **atual** e **projetado**.

### Atual

Somente fatos confirmados elegíveis.

### Projetado

Fatos atuais + compromissos/previsões elegíveis ainda não realizados conforme o contrato.

### Histórico fechado

O valor que foi calculado ao encerrar o dia deve ser preservado, com revisão identificável caso fatos retroativos sejam corrigidos.

Para cada dia, armazenar ou reconstruir com proveniência suficiente:

- orçamento diário apresentado;
- capacidade diária apresentada;
- gasto realizado elegível;
- folga do dia;
- situação/classificação;
- premissas/versão do cálculo;
- visão utilizada;
- revisão posterior, se houver.

---

## 11. Diferenciar fato financeiro de indicador

### Fatos financeiros

- receita;
- despesa;
- pagamento;
- reembolso;
- estorno;
- compromisso;
- previsão;
- aporte/movimentação real para meta, caso exista.

### Indicadores derivados

- custo diário estrutural;
- média diária realizada;
- capacidade diária;
- orçamento diário;
- folga diária;
- economia acumulada contra orçamento;
- projeção de fechamento;
- pontuação/saúde financeira.

Indicadores derivados não devem criar movimentações artificiais.

---

## 12. Alterações em relação ao modelo vigente

| Tema | Modelo vigente | Evolução proposta |
| --- | --- | --- |
| Valor diário | Verba/média diária como indicador central | Conjunto de métricas diárias com semânticas separadas |
| Gasto diário | Ritmo observado | Realizado + histórico por categoria e período |
| Fixos | Compromissos mensais na verba | Também geram custo estrutural diário equivalente |
| Receita maior | Aumenta verba/capacidade conforme regras | Capacidade pode subir sem obrigar orçamento pessoal a subir |
| Dia abaixo do orçamento | Melhora verba/margem implicitamente | Gera folga diária explícita e rastreável |
| Folga | Não tratada como conceito de primeira classe | Indicador próprio, com política de redistribuição/alocação |
| Metas | Fora do núcleo atual desta proposta | Integradas à recomendação diária e projeção de prazo |
| Gráfico | Indicadores mensais/planejamento | Série orçamento x realizado + acumulado de folga |
| Score | Faixas de situação mensal | Possível índice explicável de eficiência/saúde financeira |
| Histórico | Avaliação diária | Evolui para preservar também métricas diárias apresentadas |

---

## 13. Invariantes obrigatórios

1. Não usar `float` em regras financeiras.
2. Não transformar indicador em movimentação contábil.
3. Não contar a mesma despesa ou receita em múltiplos conjuntos.
4. Pagamento de fatura não duplica compra.
5. Transferência própria permanece neutra.
6. Estorno/reembolso corrige o fato original segundo contrato.
7. Folga diária não é receita.
8. Economia contra orçamento não é automaticamente saldo bancário.
9. Aumento de capacidade não altera orçamento voluntário sem regra explícita.
10. Meta não recebe aporte fictício apenas porque houve folga.
11. Dados insuficientes não geram diagnóstico forte.
12. Toda mudança do orçamento diário deve ser explicável por fatos/configuração.
13. Histórico de um dia fechado deve registrar a versão da regra usada.
14. Cálculos derivados devem ser determinísticos para o mesmo conjunto de entradas e versão de regra.

---

## 14. Agentes e responsabilidades de revisão

A implementação não deve começar como tarefa monolítica.

### Inv — análise financeira/matemática

Responsável por:

- validar definições financeiras;
- impedir dupla contagem;
- definir fórmulas e denominadores;
- revisar efeitos da folga diária;
- revisar política de metas;
- propor/testar score financeiro;
- criar cenários de fronteira e contraexemplos.

### Atlas — arquitetura e contratos

Responsável por:

- separar fatos de indicadores;
- definir serviços/calculadoras puras;
- definir contratos de entrada/saída;
- definir versionamento das regras;
- evitar acoplamento entre dashboard, ledger e metas;
- revisar persistência de snapshots/proveniência.

### Lia — produto e comportamento

Responsável por:

- validar nomenclatura apresentada ao usuário;
- evitar confusão entre orçamento, capacidade, saldo e média;
- definir explicações e estados incompletos;
- validar comportamento das configurações.

### Bento — QA e regressão

Responsável por:

- construir matriz de cenários;
- validar limites, centavos, início/fim de mês;
- testar mudanças retroativas;
- testar gráficos e histórico;
- provar que o novo motor não altera ledger indevidamente.

### Íris — isolamento e privacidade

Responsável por:

- revisar isolamento por `user_id`;
- validar snapshots e histórico;
- verificar exposição de dados em integrações futuras.

### Nexo — confiabilidade/concorrência

Responsável por:

- revisar jobs de fechamento diário;
- reconstrução de dias perdidos;
- idempotência;
- concorrência com mutações financeiras.

### Nilo — implementação

Implementa somente após fechamento das decisões e contratos relevantes.

---

## 15. Ordem recomendada de execução

### Fase D0 — contrato conceitual

- aprovar nomes das métricas;
- definir entradas e saídas;
- escolher política inicial da folga;
- definir relação com metas;
- decidir se score entra no primeiro incremento.

### Fase D1 — calculadora pura v2

Implementar sem UI e sem efeitos colaterais:

- custo estrutural diário;
- gasto diário realizado;
- capacidade diária;
- orçamento diário;
- folga diária;
- acumulado de folga;
- projeções associadas.

### Fase D2 — seleção de dados

- mapear conjuntos exclusivos;
- garantir neutralizações;
- integrar compromissos, previsões, cartões, estornos e reembolsos;
- cobrir categorias.

### Fase D3 — histórico diário

- snapshot/versionamento;
- reconstrução;
- correção retroativa identificada;
- trilha de explicação.

### Fase D4 — dashboard e gráficos

- cards das métricas;
- orçamento x realizado;
- folga acumulada;
- categorias;
- atual x projetado;
- explicação do motivo das mudanças.

### Fase D5 — metas

- metas pessoais;
- prazo e necessidade diária;
- impacto da folga;
- política de alocação;
- previsão de conclusão.

### Fase D6 — score opcional

Somente após dados e métricas estabilizados.

### Fase D7 — consolidação

- agentes revisam em suas especialidades;
- suíte completa;
- cenários financeiros independentes;
- regressão do contrato atual;
- aceite visual desktop/mobile;
- documentação/handoff atualizados.

---

## 16. Cenários mínimos para o contrato futuro

### Cenário A — abaixo do orçamento

```text
orçamento: 90,00
gasto:     80,00
folga:    +10,00
```

Esperado:

- registrar +10 de folga;
- não registrar +10 como receita;
- não criar transação fictícia;
- projeção pode melhorar;
- orçamento futuro só muda conforme política aprovada.

### Cenário B — acima do orçamento

```text
orçamento: 90,00
gasto:    110,00
folga:    -20,00
```

Esperado:

- registrar -20;
- recalcular projeção;
- preservar distinção entre excesso diário e déficit mensal.

### Cenário C — aumento de receita

Antes:

```text
capacidade: 100/dia
orçamento:   80/dia
```

Depois de nova renda confirmada:

```text
capacidade: 150/dia
orçamento:   80/dia (até regra/configuração alterar)
```

Esperado: não ocorrer inflação automática do orçamento pessoal.

### Cenário D — meta

```text
restante meta: 2.400
prazo: 120 dias
necessidade: 20/dia
capacidade: 110/dia
orçamento recomendado: 90/dia
```

Esperado: exibir impacto da meta sem registrar aporte inexistente.

### Cenário E — dia sem compra

O usuário não realiza transação variável hoje, mas possui compromissos estruturais.

Esperado:

- gasto variável do dia pode ser 0;
- custo estrutural equivalente continua existindo;
- não criar despesas diárias fictícias para representar o estrutural.

---

## 17. Questões que os agentes devem fechar antes da implementação

1. Custo estrutural diário usa dias do mês-calendário, dias restantes ou outra base de apresentação?
2. Orçamento diário é recalculado a cada mutação ou congelado no início do dia para fins históricos?
3. Ao receber renda no meio do dia, qual orçamento deve aparecer no snapshot daquele dia?
4. Folga acumulada negativa deve carregar influência para o dia seguinte? Em que indicador?
5. Política A, B ou C será padrão?
6. Metas reduzem orçamento diário como proteção/reserva ou ficam como camada recomendativa separada?
7. O score entra no MVP do motor ou fica para depois?
8. Quais gastos são excluídos da média comportamental por serem extraordinários?
9. Média móvel será 7/30/90 dias ou configurável?
10. Como tratar mês com poucos dias de dados?
11. Como apresentar dias sem lançamento versus dias explicitamente sem gasto?
12. Como versionar snapshots quando a fórmula evoluir?
13. Quais métricas serão persistidas e quais serão reconstruídas?
14. Como metas interagem com patrimônio já existente sem declarar esse patrimônio como renda mensal?

---

## 18. Fora do escopo desta proposta

- módulo financeiro Business/profissional;
- contabilidade empresarial;
- estoque e compra/venda empresarial;
- WhatsApp;
- importação OFX;
- importação/exportação geral JSON;
- IA gerando cálculos financeiros livremente.

WhatsApp, OFX e JSON continuam como integrações futuras do módulo pessoal, mas não devem bloquear a definição correta do motor financeiro diário.

---

## 19. Princípio para futura IA/assistente

O cálculo financeiro deve permanecer determinístico.

A IA poderá:

- consultar métricas;
- explicar resultados;
- resumir tendências;
- interpretar linguagem natural;
- sugerir ações;
- acionar operações mediante contratos explícitos.

A IA **não deve inventar saldo, verba, média, projeção ou score**. Esses valores devem vir do motor financeiro versionado e testável.

Exemplo futuro:

```text
Usuário: quanto posso gastar hoje?

Resposta baseada no motor:
- orçamento diário: 74,00
- realizado hoje: 32,00
- restante de referência: 42,00
- projeção do mês: 286,00 abaixo do orçamento atual
```

---

## 20. Critério para considerar esta proposta pronta para virar contrato

A proposta só deve migrar para `FINANCIAL_PLANNING_CONTRACT.md` ou contrato sucessor quando:

- Inv aprovar matemática e invariantes;
- Atlas aprovar fronteiras e arquitetura;
- Lia aprovar semântica e UX;
- Bento possuir cenários de aceitação independentes;
- conflitos com o contrato atual estiverem enumerados;
- decisões abertas da seção 17 estiverem resolvidas;
- estiver explícito o que entra no primeiro incremento e o que fica adiado.

Até lá, este documento é **fonte de intenção e planejamento**, não evidência de implementação.