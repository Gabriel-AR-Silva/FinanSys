# Contrato de importação OFX do FinanSys

## Estado do documento

- **Status:** arquitetura funcional aprovada para implementação pelo Chefe em 2026-09-13.
- **Branch autorizada para continuidade:** `codex/v1-onboarding` enquanto a criação de uma branch dedicada estiver bloqueada pela integração.
- **Prioridade:** arquivos OFX reais exportados pelo Nubank, com fronteira extensível para outros bancos somente após fixture e validação próprias.
- **Amostra real validada:** OFX 1.02/SGML do Nubank, UTF-8, BRL, conta corrente, datas com offset BRT, `FITID`, `TRNTYPE`, `DTPOSTED`, `TRNAMT`, `MEMO` e `LEDGERBAL`.
- **Deploy:** somente depois de implementação, testes, CI, revisão e autorização explícita do Chefe para promoção.

## Objetivo

Permitir que o usuário autenticado envie um extrato OFX real, revise a interpretação do FinanSys e confirme somente operações corretamente classificadas, categorizadas e vinculadas ao domínio financeiro, sem duplicidade, sem inventar renda/despesa e sem sobrescrever saldo oficial.

## Princípio central

`CREDIT` e `DEBIT` são sinais bancários, não regras de negócio suficientes.

Antes de persistir qualquer efeito financeiro, o FinanSys deve executar:

`OFX -> validação -> parser -> normalização -> relações -> classificação -> deduplicação -> preview -> categoria/regra de planejamento -> confirmação -> domínio -> auditoria/conciliação`

A interface nunca possui autoridade para alterar valor, data, direção ou identidade bancária retornados pelo parser.

## Evidência da amostra real do Nubank

A amostra recebida demonstrou um caso de **Pix no Crédito** composto por:

- entrada `CREDIT` com memo de valor adicionado por cartão de crédito;
- saída `DEBIT` correspondente ao Pix enviado;
- `FITID` relacionado, sendo o segundo derivado do primeiro com sufixo `:reversal`;
- mesmo valor e mesma data.

Esses dois itens **não podem virar automaticamente uma receita e uma despesa comuns**. Devem ser correlacionados e apresentados como operação composta sujeita à classificação apropriada do domínio, preferencialmente cartão quando os dados necessários puderem ser confirmados pelo usuário.

## Invariantes do FinanSys

- Toda leitura e escrita é isolada por `user_id`.
- Contas e caixinhas continuam entidades distintas.
- O OFX V1 importa para conta, não diretamente para caixinha.
- Saldos permanecem derivados das operações do FinanSys.
- `LEDGERBAL` é somente evidência de conciliação; nunca sobrescreve saldo.
- Valores financeiros usam decimal exato; nunca `float`.
- Categoria deve pertencer ao usuário, estar ativa e ser compatível com o tipo.
- Despesa deve respeitar a classificação de planejamento exigida pelo domínio.
- Transferência interna não vira renda ou despesa.
- Crédito/cartão não vira renda ou caixa fictício.
- Escritas financeiras permanecem transacionais, idempotentes e auditadas.
- O importador reutiliza as Actions/serviços financeiros existentes ou extrai núcleo compartilhado; não duplica regra de negócio.

## Componentes arquiteturais

### `OfxParser`

Responsabilidade pura: receber bytes e devolver estrutura neutra, sem acessar banco.

Deve suportar inicialmente o formato observado do Nubank:

- `OFXHEADER:100`;
- `DATA:OFXSGML`;
- `VERSION:102`;
- UTF-8;
- `BANKMSGSRSV1` / `STMTRS`;
- `BANKTRANLIST` / `STMTTRN`;
- datas com timezone/offset;
- `LEDGERBAL` apenas como metadado de conciliação.

### `OfxNormalizer`

Produz itens imutáveis conceituais com:

- `external_id` derivado de `FITID` quando disponível;
- `bank_type`;
- `occurred_at` normalizado para calendário coerente com Brasília;
- `amount` como string decimal de magnitude;
- `direction` bancária normalizada;
- `description`/`memo` limitados;
- `source_index` estável;
- `fingerprint` determinístico de fallback;
- identificadores bancários somente na forma mínima necessária e, quando persistidos, preferencialmente hash.

### `OfxRelationshipDetector`

Analisa relações entre itens antes de classificá-los individualmente.

Casos iniciais:

- `FITID` derivado, como `id` e `id:reversal`;
- mesmo valor/data com descrição que indique operação composta;
- pares candidatos a transferência;
- itens que não podem ser classificados com segurança de forma isolada.

A detecção de relação não cria efeitos financeiros.

### `OfxTransactionClassifier`

Classificações iniciais conceituais:

- `income`;
- `expense`;
- `transfer_candidate`;
- `card_credit_pix_candidate`;
- `duplicate`;
- `unsupported`;
- `needs_review`.

O classificador pode sugerir, mas não inventa categoria, cartão, origem/destino ou regra de planejamento.

### Deduplicação

Deduplicação bancária e idempotência de requisição são proteções diferentes.

Chave preferencial:

`user + account + institution + external_id`

Fallback quando `FITID` estiver ausente ou não puder ser usado:

`hash(institution + account_source + occurred_at + amount + direction + normalized_description)`

A garantia final deve existir em índice/constraint de banco, não somente em consulta prévia.

## Persistência proposta

### `bank_statement_imports`

Representa a operação de importação e seus metadados:

- usuário;
- conta FinanSys;
- instituição detectada;
- período;
- status;
- totais por resultado;
- hash do arquivo ou identificador técnico seguro quando útil;
- início/conclusão;
- saldo de origem somente para conciliação.

O arquivo OFX original não deve ser armazenado permanentemente.

### `bank_statement_import_items`

Representa cada item normalizado/importado:

- importação;
- identidade externa/fingerprint;
- índice de origem;
- data;
- valor;
- direção bancária;
- classificação;
- status;
- categoria escolhida quando aplicável;
- regra de planejamento quando aplicável;
- referência ao objeto do domínio criado;
- vínculo com item relacionado quando houver.

Não persistir CPF, número completo de conta ou memo sensível além do necessário para a experiência e auditoria. Logs jamais recebem o conteúdo integral do extrato.

### `bank_account_sources` (opcional no primeiro incremento, recomendado)

Vínculo seguro entre conta FinanSys e origem bancária conhecida para sugerir automaticamente a conta nas importações futuras.

Persistir apenas dados mínimos, preferencialmente hash dos identificadores externos.

## Preview obrigatório

Nenhum efeito financeiro ocorre durante o preview.

A tela deve exibir, para cada item ou grupo relacionado:

- data;
- descrição sanitizada;
- valor;
- classificação sugerida;
- status de duplicidade;
- categoria quando obrigatória;
- planejamento para despesa quando obrigatório;
- motivo quando exigir revisão.

No desktop pode existir tabela. No mobile, usar cards responsivos.

Itens inválidos, duplicados ou sem decisão obrigatória não podem ser confirmados.

## Categorias e planejamento

A primeira versão não usa IA para categorização.

- Receita: categoria ativa de receita do próprio usuário.
- Despesa: categoria ativa de despesa + `planning_type` permitido pelo domínio.
- Sem categoria válida, o item permanece pendente e não é importado.
- Sugestões automáticas futuras podem usar regras determinísticas por descrição, mas nunca ignoram a validação final do backend.

## Operações compostas e cartão

Quando o detector reconhecer uma operação como candidata a Pix no Crédito, o preview deve tratá-la como uma unidade lógica.

Se os dados necessários estiverem disponíveis/confirmados, a persistência deve reutilizar o domínio de cartão, incluindo cartão, categoria, classificação de planejamento, valor e datas exigidas.

Se não houver informação suficiente para persistir corretamente no domínio de cartão, o grupo permanece `needs_review`; o FinanSys não cria receita/despesa fictícia como fallback.

## Transferências

Transferências detectadas pelo OFX entram inicialmente como `transfer_candidate`.

- Não são convertidas automaticamente em renda/despesa.
- Se o outro lado puder ser identificado com segurança dentro do FinanSys, a evolução poderá reutilizar o domínio de transferência.
- Caso contrário, ficam para revisão explícita do usuário.

## Conciliação

`LEDGERBAL` é usado para informar ao usuário uma conferência, nunca para corrigir o banco.

Após a seleção/importação, a interface pode apresentar:

- saldo informado pelo banco na data do extrato;
- saldo calculado pelo FinanSys para a mesma referência;
- diferença;
- indicação de itens ignorados ou pendentes quando houver divergência.

Uma diferença de conciliação não autoriza escrita automática de ajuste.

## Jornada V1

1. Abrir **Importar extrato**.
2. Selecionar conta ativa própria; uma origem conhecida pode sugerir a conta.
3. Enviar `.ofx`.
4. Validar tamanho, conteúdo, estrutura, encoding e autorização.
5. Parsear e normalizar.
6. Detectar relações entre itens.
7. Classificar e deduplicar.
8. Exibir preview responsivo.
9. Resolver categoria/planejamento e decisões pendentes.
10. Confirmar somente itens/grupos válidos.
11. Revalidar tudo no backend.
12. Persistir em transação, usando o domínio correto.
13. Registrar auditoria e resultado da importação.
14. Exibir resumo e conciliação.
15. Remover temporários.

## Regras de confirmação

A confirmação deve receber somente:

- token opaco da prévia;
- itens/grupos escolhidos;
- IDs de decisões permitidas (categoria, cartão, planejamento etc.);
- chave de idempotência.

O backend revalida:

- usuário;
- conta;
- validade e integridade da prévia;
- item normalizado original;
- categoria/cartão pertencente ao usuário;
- compatibilidade de tipo;
- duplicidade;
- regras financeiras vigentes.

Valor, data, sinal e identidade bancária não são reconstruídos do navegador.

## Falha parcial

Um item inválido não impede o preview dos demais.

Na confirmação, somente itens integralmente válidos e selecionados participam da transação. Dentro de uma confirmação, falha inesperada antes do commit deve produzir rollback do lote selecionado, evitando estado parcial silencioso.

Itens rejeitados permanecem no resumo com motivo amigável.

## Período repetido

Importar um arquivo que cobre período já processado gera aviso e deduplicação item a item. O período por si só não bloqueia a importação, pois um extrato posterior pode conter operações novas do mesmo intervalo.

## Segurança e privacidade

- validar conteúdo real, não somente extensão/MIME informado;
- limites explícitos de bytes, quantidade de itens, profundidade e tempo;
- parser sem resolução de entidades externas, includes ou rede;
- armazenamento temporário privado e expiração curta;
- proteção CSRF/rate limit nas rotas;
- nenhum stack trace/caminho interno na UI;
- nenhum OFX integral em logs/auditoria;
- nenhum CPF/número de conta completo em logs;
- fixtures de teste anonimizadas preservando estrutura técnica;
- isolamento por usuário em todas as queries/mutações.

## Safe Data Reset relacionado

A limpeza de dados operacionais é contrato separado em `SAFE_DATA_RESET_CONTRACT.md`.

O reset deve remover importações OFX e seus itens juntamente com demais fatos financeiros do usuário, sem deixar snapshots sensíveis antigos acessíveis, preservando identidade e estruturas explicitamente protegidas pelo contrato de reset.

## Decisões aprovadas nesta revisão

1. **Transferências:** classificar como candidato e exigir revisão; nunca renda/despesa automática.
2. **Categoria ausente:** categoria é obrigatória para operação que vira receita/despesa.
3. **Destino:** OFX V1 importa para conta; caixinha fica fora.
4. **Falha parcial:** preview continua; confirmação grava apenas seleção válida e faz rollback do lote em falha inesperada.
5. **Período duplicado:** alertar e deduplicar por item; não bloquear apenas pelo intervalo.
6. **Desfazer importação:** não será um recurso próprio do primeiro incremento; limpeza controlada é tratada pelo Safe Data Reset.
7. **Pix no Crédito/operações compostas:** correlacionar antes de classificar; sem fallback que invente renda/despesa.
8. **Saldo do OFX:** somente conciliação.

## Plano de execução

### Fase 1 — Fixture e parser

- gerar fixture anonimizada estruturalmente equivalente à amostra real;
- parser puro;
- normalização de datas/decimais/texto;
- testes de formato válido/inválido, timezone, encoding, ausência de `FITID`, limites e acentos.

### Fase 2 — Relações, classificação e deduplicação

- detector de pares/grupos;
- caso real de Pix no Crédito;
- fingerprint fallback;
- constraints de deduplicação;
- testes de repetição e relações.

### Fase 3 — Preview seguro

- requests/autorização;
- armazenamento temporário privado;
- token opaco/expiração;
- preview sem efeitos financeiros;
- categorias/cartões próprios do usuário.

### Fase 4 — UI responsiva

- upload e conta;
- tabela desktop/cards mobile;
- filtros/status;
- categoria/planejamento;
- estados de revisão/duplicidade/erro;
- resumo e conciliação.

### Fase 5 — Persistência

- integração com Actions existentes;
- operação composta para domínio correto;
- transação/idempotência/auditoria;
- rollback e concorrência.

### Fase 6 — Safe Data Reset

Implementar conforme `SAFE_DATA_RESET_CONTRACT.md`, com confirmação forte, mapeamento explícito de dependências e teste de preservação.

### Fase 7 — Validação e produção

- Pint;
- testes afetados;
- suíte completa;
- build/manifest;
- testes de isolamento e repetição;
- migração em banco compatível com produção;
- teste manual desktop/mobile;
- smoke após deploy;
- promoção somente após autorização explícita do Chefe.

## Critérios de conclusão

- fixture Nubank anonimizada coberta por testes;
- parser puro e determinístico;
- Pix no Crédito não vira renda fictícia;
- preview não produz efeitos;
- categorias e planejamento respeitam o domínio;
- importação repetida não duplica dados;
- período repetido pode adicionar apenas operações novas;
- saldos continuam derivados do FinanSys;
- `LEDGERBAL` somente concilia;
- segurança/privacidade verificadas;
- arquivo temporário eliminado;
- mobile/desktop validados;
- CI verde no último HEAD;
- migrations/deploy validados antes da produção.

## Registro de aprovação

- **2026-09-13:** Chefe enviou amostra real do Nubank, autorizou o avanço da arquitetura e determinou continuidade na branch já existente enquanto a criação de branch dedicada permanece bloqueada.
- **2026-09-13:** decisões acima consolidadas com base na amostra real e nas invariantes existentes do FinanSys.
