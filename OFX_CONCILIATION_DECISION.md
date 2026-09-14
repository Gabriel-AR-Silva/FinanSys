# Decisão de arquitetura — revisão humana e separação do módulo OFX

Data: 2026-09-13  
Status: aprovado pelo Chefe para orientar a implementação na branch `codex/v1-onboarding`.

## Decisão central

A importação OFX é um **módulo de entrada de dados**, não uma nova fonte de verdade financeira e não um substituto do domínio do FinanSys.

Conceitualmente, ela é apenas outra forma de cadastrar fatos financeiros:

- cadastro manual: usuário informa os dados diretamente;
- importação OFX: arquivo fornece dados de origem, o FinanSys interpreta e o usuário valida antes da gravação.

Ambos os caminhos devem convergir para as mesmas Actions/serviços e invariantes do domínio.

## Separação arquitetural obrigatória

O módulo OFX deve permanecer separável do restante do sistema. Parser, normalização, detecção de relações, classificação, preview e estado temporário de importação pertencem ao módulo de importação. Depois da confirmação humana, a persistência delega ao domínio existente (`LedgerEntry`, cartão, transferência ou outra operação suportada).

Fluxo conceitual:

`Arquivo OFX -> Importação OFX -> interpretação automática -> revisão/conciliação pelo usuário -> confirmação -> domínio FinanSys`

O módulo não deve espalhar regras específicas de OFX pelos modelos financeiros nem fazer o domínio depender do parser. Isso permitirá, no futuro, substituir, remover ou evoluir a importação OFX sem reestruturar o núcleo financeiro.

## Revisão humana é obrigatória

Mesmo quando o sistema classificar uma operação como **conciliada automaticamente** ou tiver alta confiança, isso significa apenas que existe uma sugestão pronta para confirmação.

Não existe, nesta versão, importação financeira silenciosa.

Antes de qualquer criação de fato financeiro, o usuário deve visualizar e confirmar a proposta. Uma sugestão automática pode reduzir o trabalho manual, pré-selecionar categoria/classificação e agrupar operações relacionadas, mas nunca equivale a autorização de gravação.

Portanto os estados de UI devem ser entendidos assim:

- `sugerido_automaticamente`: sistema encontrou uma interpretação provável; usuário ainda precisa validar;
- `precisa_revisao`: faltam decisões ou existe ambiguidade;
- `duplicado`: não importável;
- `invalido`: não importável;
- `confirmado_pelo_usuario`: único estado que autoriza a etapa de persistência, sujeito à revalidação do backend.

## Papel da conciliação

A tela de revisão pode ser apresentada como uma experiência de **conciliação bancária**, porque ela compara o evento bancário com a interpretação financeira do FinanSys. Porém, no escopo atual, essa conciliação é parte da jornada do módulo OFX e não deve acoplar o núcleo financeiro ao formato OFX.

A tela responde para cada item ou grupo:

1. O que o banco informou?
2. O que o FinanSys sugere que isso representa?
3. Qual categoria, planejamento, cartão ou tratamento será usado?
4. O usuário confirma, altera ou ignora?

Mesmo uma sugestão considerada segura deve aparecer nessa revisão antes do commit.

## Parecer consultivo do Inv incorporado

A revisão financeira preserva os seguintes fundamentos:

- sinal bancário não define sozinho renda ou despesa econômica;
- Pix no Crédito não pode inflar receita/despesa como dois fatos independentes;
- transferências próprias são patrimonialmente neutras para indicadores de renda/gasto;
- classificação incorreta contamina orçamento, margem, médias e projeções;
- na dúvida, o sistema deve pedir revisão em vez de inventar interpretação;
- automação pode sugerir, mas a validação humana é adequada para o primeiro ciclo porque o custo de um falso positivo financeiro é maior do que o custo de uma confirmação adicional.

O Inv permanece consultivo; esta regra de produto foi aprovada pelo Chefe.

## Fronteira para evolução futura

A arquitetura deve permitir que a etapa de revisão/conciliação seja futuramente extraída para um módulo mais genérico de ingestão/conciliação capaz de receber outras fontes, por exemplo API bancária ou integrações externas.

Entretanto, não será criada abstração genérica prematura agora. O primeiro incremento terá fronteira clara, nomes e dependências que evitem acoplamento ao domínio, mas implementação concreta focada em OFX/Nubank.

Uma evolução possível será:

`Fonte externa -> evento financeiro de entrada -> revisão/conciliação -> confirmação -> domínio FinanSys`

OFX seria então apenas um adapter de entrada.

## Critérios adicionais de aceite

- nenhuma sugestão automática cria lançamento sem ação explícita do usuário;
- a UI diferencia claramente sugestão automática de confirmação realizada;
- adulterar a decisão no frontend não permite alterar valor/data/origem retornados pelo parser;
- o backend revalida todas as escolhas na confirmação;
- o módulo OFX pode ser removido conceitualmente sem quebrar cadastro manual ou regras financeiras;
- nenhuma Action financeira central passa a depender de classes específicas de OFX;
- testes comprovam que preview/revisão não geram efeitos financeiros;
- somente a confirmação explícita chama a camada de persistência do domínio.

## Relação com os demais documentos

Este documento complementa `OFX_IMPORT_CONTRACT.md` e `OFX_ARCHITECTURE_REVIEW.md`. Em caso de ambiguidade sobre automação, prevalece esta decisão: **automação sugere; usuário valida; backend revalida; somente então o domínio persiste**.
