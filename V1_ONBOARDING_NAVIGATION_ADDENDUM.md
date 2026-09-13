# Adendo V1 — navegação guiada, cadeia de pré-requisitos e Tsuki flutuante

## Estado

Aprovado pelo Chefe em 2026-09-13 como complemento obrigatório de `V1_ONBOARDING_CONTRACT.md`. Este adendo não altera regras financeiras; ele detalha como o usuário deve ser conduzido quando uma ação depende de outra ação anterior.

## Princípio central

Quando uma funcionalidade depende de um dado, cadastro, configuração ou operação anterior, o FinanSys deve explicar essa dependência e, sempre que for seguro, oferecer um caminho direto para a ação necessária.

O usuário não deve precisar sair procurando manualmente qual tela cria o pré-requisito.

## CTA direto para resolver o pré-requisito

Se uma tela ou ação estiver indisponível por falta de pré-requisito, a interface deve preferir um estado orientado como:

> Para usar esta ação, primeiro você precisa cadastrar/configurar X.

E, quando existir uma rota segura para isso, apresentar CTA como:

- `Acessar`
- `Configurar agora`
- `Cadastrar agora`
- `Ir para compras`
- `Ir para planejamento`

O CTA deve levar diretamente ao contexto responsável pela criação/configuração do pré-requisito, sem executar mutação financeira automaticamente.

Quando tecnicamente possível e coerente com a arquitetura existente, o destino pode abrir a tela, aba, modal ou formulário já no contexto correto, reduzindo navegação desnecessária.

## Cadeia de pré-requisitos

As dependências podem formar uma cadeia. O sistema deve continuar orientando o usuário até chegar a uma ação primária realmente executável.

Exemplo conceitual:

```text
Aplicar crédito de cartão
        ↓
Precisa existir crédito disponível
        ↓
Crédito nasce de estorno elegível
        ↓
Estorno precisa de compra elegível
        ↓
Compra depende de cartão existente
        ↓
Cadastrar cartão  ← ação primária
```

Cada etapa pode ter sua própria explicação e CTA. Ao chegar à ação primária, o usuário consegue executar o cadastro e depois avançar novamente pelas etapas dependentes.

O objetivo é impedir becos sem saída de UX.

## Retorno após concluir a dependência

Quando possível, preservar contexto de origem para facilitar o retorno.

Exemplo:

1. usuário está em `Aplicar crédito`;
2. sistema informa que não há crédito;
3. usuário acessa `Estornar compra`;
4. descobre que não há compra elegível;
5. acessa cadastro de compra/cartão;
6. conclui o pré-requisito;
7. o sistema deve facilitar o retorno ao fluxo anterior ou, no mínimo, atualizar automaticamente os estados de disponibilidade quando ele retornar.

Não é obrigatório implementar uma pilha complexa de navegação no V1, mas o Codex deve reutilizar `redirect`, parâmetros/estado de retorno ou mecanismo equivalente existente quando isso puder ser feito sem fragilizar a aplicação.

## Regras para links e ações

- Link contextual deve apontar para a tela/ação realmente responsável pelo pré-requisito.
- Não apontar genericamente para o dashboard quando existir destino mais específico.
- Não criar registros fictícios nem completar ações automaticamente para liberar outra funcionalidade.
- Não esconder erro de domínio: o CTA orienta, mas validações permanecem obrigatórias.
- Se não existir ação possível porque o recurso realmente não se aplica ao usuário, explicar isso em vez de oferecer um CTA inútil.
- Em mobile e teclado, os links/CTAs devem continuar acessíveis.

## Caso obrigatório — Correções de cartão

### Aplicar crédito

Se não houver crédito de cartão disponível, mostrar claramente que:

- crédito de cartão não é cadastrado manualmente;
- ele é gerado por estorno elegível de valor já liquidado/pago;
- o usuário pode acessar o fluxo de estorno diretamente.

CTA recomendado: `Ver compras para estornar` ou `Ir para estornos`.

### Estornar compra

Se não houver compra elegível para estorno:

- explicar qual condição falta;
- oferecer acesso à origem correta da compra/cartão quando aplicável.

Se também não houver cartão cadastrado, a orientação deve continuar até o cadastro de cartão, que passa a ser a ação primária daquela cadeia.

O Codex deve confirmar no modelo atual quais tipos de compra/cartão são elegíveis e não simplificar regras de domínio apenas para melhorar a navegação.

## Mapa obrigatório de dependências

Antes da implementação, Codex + Lia devem produzir um mapa curto para os principais fluxos do V1:

```text
Ação/conceito
→ pré-requisito imediato
→ origem do pré-requisito
→ CTA/destino
→ pré-requisito anterior, se houver
→ ação primária final
```

Priorizar fluxos em que o usuário pode encontrar select vazio, botão sem opções, dado derivado ou conceito cuja origem não seja óbvia.

## Tsuki flutuante no V1

Por enquanto, a identidade visual do ponto de ajuda será simples: usar uma lua `🌙` dentro de um botão/círculo flutuante.

Não gerar imagem, mascote, avatar animal ou asset de IA nesta etapa.

O botão deve ter sensação sutil de presença/vida sem chamar atenção excessiva. É aceitável usar CSS leve, por exemplo:

- pulso suave;
- ondas/rings discretos ao redor do círculo;
- pequena variação de escala/opacidade;
- movimento muito sutil e periódico.

Restrições:

- respeitar `prefers-reduced-motion` e desativar/reduzir animação quando solicitado pelo sistema operacional;
- não usar animação contínua agressiva;
- não competir visualmente com alertas financeiros;
- não adicionar biblioteca pesada apenas para este efeito;
- botão deve possuir `aria-label`/nome acessível, foco visível e área de toque adequada.

A lua é identidade provisória. Trocar futuramente o visual da Tsuki não deve exigir refatorar a lógica do onboarding/checklist.

## Critérios de aceite adicionais

1. Uma ação bloqueada por pré-requisito relevante explica o que falta.
2. Quando existir destino seguro, há CTA direto para resolver o pré-requisito.
3. Cadeias de dependência não terminam em tela sem orientação; é possível chegar à ação primária.
4. O fluxo de Correções de cartão demonstra esse comportamento de ponta a ponta.
5. O retorno/atualização após concluir um pré-requisito não exige o usuário adivinhar se a ação passou a estar disponível.
6. A Tsuki usa `🌙` no V1, sem asset de imagem gerado.
7. A animação é leve, acessível e respeita `prefers-reduced-motion`.
8. Nenhuma melhoria de navegação altera regras financeiras, cria crédito manual ou fabrica entidades para liberar funcionalidades.

## Orientação ao Codex

Implementar este adendo em conjunto com `V1_ONBOARDING_CONTRACT.md`. Quando houver conflito, preservar primeiro os contratos financeiros existentes; este documento define experiência/navegação, não domínio financeiro.
