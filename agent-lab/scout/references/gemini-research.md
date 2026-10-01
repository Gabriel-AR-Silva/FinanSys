# Gemini Research Architecture — lições para o Scout

Esta referência registra práticas documentadas do Gemini que podem melhorar o Scout. O objetivo não é imitar produto ou interface, e sim extrair princípios de arquitetura de pesquisa.

## O que o Google documenta

### 1. Grounding com Google Search
Quando a ferramenta de Google Search está habilitada, o modelo:
1. analisa o prompt;
2. decide se busca externa melhora a resposta;
3. gera uma ou várias consultas de busca;
4. executa as buscas;
5. processa os resultados;
6. sintetiza uma resposta;
7. vincula trechos da resposta a citações.

A resposta da API também pode expor as consultas executadas e resultados usados para grounding.

### 2. Deep Research
O Deep Research:
- usa Google Search como fonte padrão;
- permite adicionar outras fontes;
- cria um plano de pesquisa antes de iniciar;
- permite editar esse plano;
- analisa muitas fontes antes de gerar o relatório.

## O que isso ensina ao Scout

### Multi-query planning
Nunca depender de uma única consulta quando a missão for regular, profunda ou exploratória.

Gerar consultas em famílias:
- consulta principal;
- sinônimos e termos técnicos;
- consulta por fonte primária;
- consulta adversarial;
- consulta temporal;
- consulta geográfica/idioma;
- consulta de validação independente.

### Query fan-out adaptativo
O número de consultas não deve ser fixo.

Expandir consultas quando:
- surgirem conceitos novos;
- houver conflito entre fontes;
- faltar fonte primária;
- houver baixa cobertura;
- aparecer hipótese relevante ainda não testada.

Parar quando novas consultas não alterarem evidência, confiança ou conclusão.

### Search loop
O Scout deve operar em ciclos:

1. Planejar
2. Buscar
3. Extrair claims/evidências
4. Detectar lacunas
5. Gerar novas consultas
6. Buscar novamente
7. Reavaliar
8. Encerrar por saturação

Isso é melhor do que "pesquisar X sites".

### Grounding por claim
Uma conclusão importante não deve ter apenas uma lista geral de links.

Sempre que possível:
- mapear claim → evidência → fonte;
- distinguir qual fonte sustenta qual parte;
- evitar uma citação genérica para um parágrafo com vários claims diferentes.

### Search observability
Registrar de forma enxuta:
- consultas principais executadas;
- categorias de fontes;
- queries descartadas;
- novas queries geradas a partir de lacunas;
- motivo de parada.

Isso permite avaliar o processo e não apenas a resposta final.

### Separar discovery de evidence
Resultados de busca servem primeiro para descoberta.
A evidência final deve priorizar:
- fonte original;
- documentação oficial;
- dados primários;
- estudos;
- fontes independentes fortes.

Snippets e agregadores podem orientar a busca, mas não devem receber peso indevido.

## Regra nova recomendada para o Scout

Em pesquisa regular, profunda, exploratória ou de oportunidade:

> Nunca trate a primeira rodada de busca como suficiente por padrão. Execute ao menos um ciclo de revisão de lacunas. Gere novas consultas somente quando elas puderem adicionar evidência, contradição, fonte primária, contexto temporal/geográfico ou nova hipótese plausível.

## Referências oficiais

- Google AI for Developers — Grounding with Google Search
  https://ai.google.dev/gemini-api/docs/google-search/
- Gemini Apps Help — Deep Research
  https://support.google.com/gemini/answer/15719111
