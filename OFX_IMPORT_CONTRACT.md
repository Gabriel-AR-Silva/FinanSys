# Contrato de importação OFX do FinanSys

## Estado do documento

- **Status:** rascunho técnico e funcional, aguardando aprovação do Chefe.
- **Autorização atual:** documentação e planejamento somente.
- **Implementação:** não iniciada. Nenhuma etapa deste documento autoriza alteração de código, banco, dependências ou produção.
- **Fonte de referência:** especificação compartilhada sobre importação OFX para Laravel e Vue.
- **Prioridade inicial:** arquivos OFX exportados pelo Nubank, preservando uma fronteira extensível para outros bancos.

## Objetivo

Permitir que o usuário autenticado selecione uma conta própria, envie um extrato OFX, revise as movimentações reconhecidas e confirme quais serão convertidas em lançamentos do FinanSys, sem duplicidade e sem modificar saldos diretamente.

## Resultado esperado

O processo possui duas operações distintas:

1. **Pré-visualização:** valida e interpreta o arquivo, normaliza os dados e informa duplicidades, sem criar lançamentos.
2. **Confirmação:** recebe uma referência segura da pré-visualização e a seleção do usuário, revalida tudo e cria os lançamentos permitidos de forma transacional e idempotente.

## Invariantes já aprovadas do FinanSys

Estas regras não ficam pendentes de decisão:

- Toda consulta e alteração é isolada pelo usuário autenticado.
- Contas e caixinhas são entidades diferentes; uma caixinha pertence a uma conta.
- Lançamentos utilizam referência polimórfica `account` ou `pocket`, com `morphMap`, enum e resolução centralizada dentro do usuário autenticado.
- Saldos são derivados dos lançamentos. O importador nunca grava ou corrige saldo diretamente.
- Valores persistidos usam `DECIMAL`; cálculos financeiros não usam `float`.
- O tipo do lançamento determina receita ou despesa. O domínio não exige valor negativo para despesas.
- Alterações financeiras são transacionais, idempotentes e auditadas.
- Exclusões financeiras relevantes respeitam `deleted_at` e a política de restauração vigente.
- Tipos e estados são representados por enums.
- Regras críticas permanecem no backend; o Vue conduz apenas a experiência.

## Escopo da primeira versão

Incluído:

- seleção de uma conta pertencente ao usuário;
- upload temporário de um arquivo OFX dentro do limite configurado;
- suporte validado contra um arquivo real e anonimizado do Nubank;
- detecção de versão, estrutura e encoding do OFX;
- normalização de data, descrição, identificador externo, tipo bancário e valor;
- prévia paginada ou limitada, com status individual;
- seleção das movimentações importáveis;
- associação a categorias existentes compatíveis;
- confirmação transacional;
- proteção contra repetição da mesma movimentação e da mesma requisição;
- resumo de importadas, duplicadas, ignoradas e rejeitadas;
- auditoria sem conteúdo integral do extrato;
- testes automatizados e fixture anonimizada.

Fora do primeiro ciclo:

- sincronização direta por API bancária;
- categorização inteligente ou aprendizagem automática;
- conciliação automática de transferências;
- suporte genérico declarado sem fixture real do banco correspondente;
- alteração do saldo a partir do saldo informado no extrato;
- armazenamento permanente do arquivo original;
- importação diretamente para caixinhas, até decisão explícita;
- desfazer uma importação completa, até definição de produto e auditoria.

## Jornada funcional

1. O usuário abre **Importar extrato**.
2. Seleciona uma conta ativa que lhe pertence.
3. Seleciona um arquivo `.ofx`.
4. O backend valida tamanho, conteúdo, estrutura e autorização da conta.
5. O parser converte o conteúdo externo para itens normalizados, sem persistir lançamentos.
6. O backend classifica cada item como novo, duplicado, inválido ou dependente de decisão.
7. A interface apresenta a prévia e seleciona por padrão somente itens novos e válidos.
8. O usuário revisa categorias e desmarca o que não deseja importar.
9. Ao confirmar, o backend revalida usuário, conta, prévia, seleção e duplicidade.
10. Uma transação de banco cria todos os lançamentos aceitos e sua auditoria.
11. A interface mostra um resumo verificável e oferece acesso aos lançamentos criados.

## Contrato de normalização

O parser recebe bytes do arquivo e retorna dados internos neutros. Ele não consulta nem grava o banco.

Cada item normalizado deve conter conceitualmente:

| Campo | Tipo lógico | Regra |
| --- | --- | --- |
| `external_id` | string nula | Origina-se preferencialmente de `FITID`; nunca é confiado sem validação. |
| `bank_type` | enum/string | Valor original normalizado de `TRNTYPE`, preservado para diagnóstico. |
| `occurred_at` | data/hora imutável | Derivada de `DTPOSTED`, com timezone tratado explicitamente. |
| `amount` | decimal em string | Magnitude monetária exata; nunca `float`. |
| `direction` | `income` ou `expense` | Derivada do sinal e dos dados bancários; será convertida para o enum do domínio. |
| `description` | string | Derivada de `NAME`/`MEMO`, normalizada e limitada. |
| `memo` | string nula | Informação auxiliar limitada; não é regra financeira. |
| `document` | string nula | Origina-se de `CHECKNUM`/`REFNUM`, quando existente. |
| `source_balance` | decimal em string nula | Apenas conferência da importação; nunca vira saldo oficial. |
| `source_index` | inteiro | Posição estável no arquivo para diagnóstico e seleção. |
| `fingerprint` | string | Hash determinístico de fallback para deduplicação quando não houver identificador confiável. |

O contrato PHP definitivo deverá usar objetos imutáveis, enums existentes e representação decimal compatível com o projeto. Os nomes acima são conceituais e podem ser adaptados às convenções encontradas durante a implementação.

## Contrato da pré-visualização

### Entrada

- usuário autenticado pela sessão;
- `account_id` pertencente ao usuário e com estado permitido;
- arquivo obrigatório, temporário e dentro do limite configurado;
- chave de idempotência da requisição, quando adotada pelo contrato HTTP definitivo.

### Saída

- identificador opaco e temporário da prévia;
- validade da prévia;
- metadados mínimos: instituição detectada, conta selecionada e período encontrado;
- totais: encontrados, novos, duplicados, inválidos e dependentes de decisão;
- itens normalizados, cada um com identificador opaco, dados de exibição, status e motivo;
- categorias compatíveis pertencentes ao usuário.

### Garantias

- Nenhum `LedgerEntry` é criado.
- O arquivo não fica acessível publicamente.
- A resposta não contém caminhos internos, stack trace ou conteúdo integral do OFX.
- Itens duplicados e inválidos não são selecionáveis.
- A prévia pertence exclusivamente ao usuário e expira.
- O cliente não recebe autoridade para redefinir valor, tipo, data ou destino após o parsing.

## Contrato da confirmação

### Entrada

- identificador opaco da prévia;
- identificadores opacos dos itens selecionados;
- categoria do próprio usuário, compatível com receita ou despesa, para cada item que exigir categoria;
- chave de idempotência da confirmação.

### Revalidação obrigatória

- sessão e usuário;
- propriedade e estado da conta;
- propriedade, integridade e validade da prévia;
- correspondência exata entre itens selecionados e itens normalizados no servidor;
- categoria pertencente ao usuário e compatível com o tipo;
- duplicidade no momento da gravação;
- regras vigentes de criação de `LedgerEntry`.

### Persistência

- Reutilizar a ação/serviço de criação de lançamento ou extrair um núcleo compartilhado; não duplicar regras.
- O destino inicial é uma referência polimórfica `account` resolvida dentro do usuário autenticado.
- A magnitude de `amount` permanece não negativa; `LedgerEntryType` representa a direção.
- Cada lançamento recebe `operation_id` e dados suficientes para auditoria e rastreabilidade da importação.
- A operação usa `DB::transaction` e índice único no banco para sustentar a idempotência sob concorrência.

### Saída

- estado geral: concluída, concluída com ressalvas ou rejeitada;
- quantidades importadas, duplicadas, ignoradas e rejeitadas;
- referências dos lançamentos criados, quando seguro e útil;
- erros por item em linguagem amigável, sem detalhes internos.

## Deduplicação e idempotência

São proteções diferentes:

- **Idempotência da requisição:** repetir a mesma confirmação não produz uma segunda operação.
- **Deduplicação bancária:** a mesma movimentação de origem não produz outro lançamento em uma importação diferente.

A chave bancária candidata é composta por usuário, conta de destino, instituição/origem e `external_id`. Quando `FITID` estiver ausente ou não for confiável, deverá existir uma estratégia de fingerprint documentada e testada. A decisão final do índice depende da análise do OFX real e do esquema vigente.

A verificação em código melhora a mensagem ao usuário, mas a garantia contra concorrência deve existir no banco de dados.

## Segurança e privacidade

- Autorizar a conta sempre a partir do usuário autenticado; nunca buscar apenas pelo ID recebido.
- Validar conteúdo real, não somente extensão ou MIME informado.
- Definir limites de bytes, itens, profundidade, tempo e memória.
- Desabilitar resolução de entidades externas e acessos de rede durante o parsing.
- Processar o arquivo em armazenamento privado/temporário e removê-lo ao finalizar ou expirar.
- Não registrar conteúdo integral, valores individuais, CPF, número de conta ou tokens de prévia.
- Não aceitar na confirmação valores financeiros reconstruídos pelo navegador.
- Aplicar proteção CSRF, rate limiting e mensagens de erro não reveladoras nas rotas autenticadas.
- Anonimizar fixtures sem destruir a estrutura necessária ao teste.

## Falhas e consistência

- Falha na prévia não altera o domínio financeiro.
- Prévia expirada exige novo processamento do arquivo.
- Falha antes do commit reverte todos os lançamentos daquela confirmação.
- Conflito de duplicidade detectado no commit é convertido em resultado seguro, nunca em duplicação.
- O resumo distingue erro de arquivo, item inválido, duplicidade e falha interna.
- Processamento assíncrono somente será introduzido se tamanho ou tempo medidos justificarem; o contrato deve permitir evolução sem exigir fila agora.

## Auditoria mínima

Registrar:

- usuário e conta envolvidos;
- identificador da operação/importação;
- instituição/formato detectado;
- horário de início e conclusão;
- quantidades encontradas, importadas, duplicadas, ignoradas e rejeitadas;
- resultado e categoria geral do erro, quando houver.

Não registrar nome completo do arquivo se ele puder conter dados pessoais, conteúdo do extrato ou detalhes financeiros linha a linha.

## Decisões pendentes do Chefe

Estas escolhas bloqueiam partes específicas da implementação, mas não impedem a análise inicial do OFX:

1. **Transferências:** ignorar, importar como lançamento comum ou encaminhar para conciliação manual?
2. **Categoria ausente:** exigir escolha durante a prévia ou permitir lançamento histórico sem categoria?
3. **Destino:** primeira versão importa somente para conta ou também permite escolher uma caixinha?
4. **Falha parcial:** uma linha inválida cancela tudo ou as válidas são importadas com ressalvas?
5. **Período duplicado:** apenas alertar quando o arquivo cobre um período já importado ou impedir até revisão?
6. **Desfazer importação:** ficará fora do MVP ou deverá existir restauração em lote desde a primeira versão?

Até essas respostas serem aprovadas, nenhuma hipótese será transformada silenciosamente em regra de negócio.

## Plano de execução em partes

### Parte 0 — Aprovação do contrato

**Responsáveis:** Lia e Atlas; decisão final do Chefe.

- responder às decisões pendentes;
- ajustar jornada e invariantes;
- marcar este documento como aprovado.

**Gate:** nenhuma implementação começa enquanto o contrato estiver em rascunho.

### Parte 1 — Descoberta técnica e amostra real

**Responsáveis:** Atlas e Nilo; revisão de Íris.

- mapear ações, modelos, enums, rotas e componentes existentes;
- obter um OFX real do Nubank devidamente anonimizado;
- identificar versão, encoding, timezone, tags e irregularidades;
- avaliar uma biblioteca somente depois de confirmar versões e formato;
- propor o contrato PHP e as alterações mínimas de banco.

**Entrega:** relatório de compatibilidade e fixture anonimizada.

**Gate:** nenhum parser é declarado pronto sem a amostra real.

### Parte 2 — Parser puro

**Responsável:** Nilo; revisão de Bento e Íris.

- implementar parser encapsulado e DTO imutável;
- normalizar datas, decimais, texto e identificadores;
- limitar recursos e rejeitar conteúdo inseguro ou inválido;
- não acessar o banco.

**Testes:** arquivo válido, inválido, variações de encoding, acentos, datas, entradas/saídas, ausência de `FITID` e limites.

**Gate:** testes do parser aprovados com fixture real anonimizada.

### Parte 3 — Pré-visualização segura

**Responsáveis:** Nilo e Íris; revisão de Bento.

- criar Form Request e autorização da conta;
- armazenar estado temporário privado com expiração;
- classificar itens e duplicidades sem persistir lançamentos;
- retornar contrato adequado ao Inertia.

**Testes:** isolamento entre usuários, conta alheia, arquivo inválido, expiração, nenhum efeito financeiro e mensagens seguras.

**Gate:** provar que a prévia não cria nem altera lançamentos.

### Parte 4 — Interface responsiva de revisão

**Responsável:** Nilo; revisão de Lia e Bento.

- seleção de conta e arquivo;
- progresso, estado vazio e erros junto aos campos;
- tabela no desktop e apresentação adequada no mobile;
- filtros de status, seleção somente de itens permitidos e escolha de categoria;
- confirmação explícita com resumo.

**Testes:** navegação por teclado, estados de carregamento/erro, larguras mobile reais e prevenção de seleção inválida.

**Gate:** jornada completa de prévia validada sem quebra de layout.

### Parte 5 — Importação transacional e idempotente

**Responsáveis:** Nilo e Nexo; revisão de Atlas, Íris e Bento.

- implementar persistência reutilizando contratos financeiros existentes;
- criar proteção de idempotência e índice de deduplicação;
- auditar a operação;
- tratar corrida e repetição da requisição;
- retornar resumo por estado.

**Testes:** mesma confirmação repetida, mesmo arquivo repetido, concorrência, rollback, categoria incompatível, conta alheia e dados adulterados no frontend.

**Gate:** nenhuma duplicidade ou estado parcial após falhas simuladas.

### Parte 6 — Integração, desempenho e operação

**Responsáveis:** Maia coordena; Fluxo mede; Bento executa regressão.

- executar testes afetados e suíte completa;
- medir arquivo pequeno e arquivo no limite;
- revisar consultas e índices com evidência;
- verificar logs, limpeza dos temporários e comportamento em produção;
- decidir, a partir das medições, se filas são necessárias.

**Gate:** build, testes, revisão de segurança e checklist funcional aprovados por papéis diferentes dos implementadores.

### Parte 7 — Publicação controlada

**Responsáveis:** Maia e Nexo; autorização do Chefe.

- versionar primeiro na branch de desenvolvimento;
- executar migration e build conforme o processo de deploy vigente;
- validar com arquivo anonimizado;
- observar erros sem registrar dados financeiros;
- promover para produção somente com autorização explícita.

**Gate:** plano de retorno definido e validação pós-deploy concluída.

## Critérios de conclusão da funcionalidade

- contrato aprovado pelo Chefe;
- OFX real anonimizado coberto por testes;
- parser isolado e sem `float` monetário;
- prévia sem efeitos financeiros;
- confirmação revalidada no backend;
- isolamento integral por usuário;
- conta e categorias resolvidas dentro do usuário;
- saldos continuam derivados dos lançamentos;
- importação repetida não duplica registros;
- concorrência protegida pelo banco;
- operação transacional e auditada;
- arquivo temporário eliminado;
- interface responsiva e acessível nos estados essenciais;
- mensagens amigáveis sem vazamento de detalhes internos;
- testes afetados, regressão e build aprovados;
- deploy realizado somente após autorização do Chefe.

## Registro de aprovação

Quando o Chefe autorizar alterações, registrar aqui:

- decisões pendentes respondidas;
- versão aprovada do contrato;
- partes autorizadas para implementação;
- data da aprovação;
- mudanças posteriores e seus impactos.

Enquanto este registro estiver vazio, o documento permanece exclusivamente como proposta.
