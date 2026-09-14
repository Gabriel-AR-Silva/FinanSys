# Handoff do desenvolvimento — FinanSys

Atualizado em: 2026-09-13  
Branch atual: `codex/v1-card-reversal`  
Base de integração: `codex/copilot-mod-v1-hardening`  
Origem histórica: `copilot_mod_v1`

## Ponto de continuidade

Leia, nesta ordem: `AGENTS.md`, `.ai/rules/index.md`, `FINANCIAL_PLANNING_CONTRACT.md`, `CARD_REVERSAL_CONTRACT.md`, `FINANCIAL_PLANNING_STAGES.md` e `DAILY_FINANCIAL_ENGINE_PROPOSAL.md`.

A lógica contratada do V1 está implementada nesta branch. Não reiniciar E3 nem misturar o Daily Financial Engine V2 ao V1. O trabalho restante antes de publicação é validação em ambiente real, aceite visual/manual e deploy.

## O que já existia antes desta branch

- configuração financeira mensal, proteção e essenciais;
- previsões recorrentes/parciais, residual, excesso e vínculos explícitos;
- cartões, parcelas, pagamentos e dívida carregada;
- encargos confirmados e antecipação com desconto;
- visões atual/projetada;
- fechamento diário, reconstrução, revisões e proveniência;
- alertas financeiros internos deduplicados;
- hardening de locks/idempotência/auditoria e CI completo.

## Entregue nesta branch

### 1. Estorno de compra e crédito de cartão

Implementado conforme `CARD_REVERSAL_CONTRACT.md`:

- nova persistência `card_purchase_reversals`, `card_credits` e `card_credit_allocations`;
- novo estado `CardInstallmentStatus::Reversed`;
- `ReverseCardPurchase` com lock por usuário, locks dos recursos, idempotência, auditoria e rollback;
- compra não paga cancela obrigação ativa e cria crédito zero;
- compra parcial cancela residual e credita somente o valor liquidado elegível;
- compra paga gera crédito sem apagar pagamentos históricos;
- antecipação com desconto gera crédito pelo `net_amount` efetivamente pago, nunca pelo bruto nominal;
- compra estornada é soft-deleted para sair dos conjuntos ativos sem perder histórico/auditoria;
- crédito não é renda e não cria entrada em conta/caixinha.

### 2. Aplicação explícita de crédito

- `ApplyCardCredit` permite alocação total/parcial;
- destino: parcela ou encargo pendente;
- mesmo usuário e mesmo cartão são obrigatórios;
- saldo do crédito e saldo da obrigação são validados e travados;
- replay exato é idempotente e replay divergente é rejeitado;
- aplicação não cria ledger bancário;
- UI própria em `Correções de cartão`, acessível pela navegação desktop/mobile.

### 3. Correções retroativas e proveniência

`ReviseFinancialHistory` reutiliza `FinancialEvaluation`:

- fechamento já apresentado não é sobrescrito;
- se o resultado histórico recalculado mudou, cria nova `revision`;
- `supersedes_id` aponta para a versão anterior;
- origem `correction` diferencia a revisão posterior;
- estorno e aplicação de crédito atualizam histórico/alertas relevantes.

### 4. Erro da página de avisos / migration pendente

Foi endurecido o cenário relatado pelo Chefe:

- `InternalAlertController` verifica se `internal_alerts` existe;
- se a migration ainda não foi aplicada, a página abre com lista vazia e aviso explícito em vez de 500;
- `UpdateInternalAlert` também vira no-op seguro sem a tabela, então uma migration pendente do módulo de avisos não derruba a escrita financeira principal;
- há testes automatizados para abertura da página e atualização de alerta com schema ausente;
- isto não elimina a obrigação de executar migrations antes de considerar alertas operacionais.

### 5. Regressões descobertas e corrigidas durante CI

- novos modelos de estorno/crédito foram adicionados ao morph map obrigatório da auditoria;
- teste antigo de rollback da antecipação foi corrigido para comparar auditoria com o baseline existente, sem enfraquecer a atomicidade;
- nomes de rotas de `ledger-entries` foram preservados após revisão do diff;
- UI de estorno deixou de mostrar um “valor pago” enganoso em compras com antecipação por desconto.

## Evidência automatizada

Run verde de referência: GitHub Actions `34784669863`, commit `e574462afebc9b80cb147eb615a275c49ef8eda7`:

- Pint: 277 arquivos aprovados;
- PHPUnit: **388 testes / 2.124 asserções**;
- npm: instalação aprovada, zero vulnerabilidades reportadas naquele run;
- Vite build: aprovado;
- manifest: aprovado;
- `public/build`: artefato gerado.

Depois desse run foram adicionados hardenings/tests/UI/documentação. **Antes de merge/publicação, use como evidência o último HEAD verde**, não o run acima se houver commit posterior.

## Revisão coordenada dos agentes

### Inv + Lia

- crédito permanece compensação de cartão, nunca renda/caixa;
- desconto de antecipação não reaparece como crédito fictício;
- competência corrigida e data real do estorno permanecem separadas;
- nenhuma regra do V2 foi introduzida no V1.

### Atlas

- modelo separa evento de estorno, saldo de crédito e aplicações;
- soft delete remove compra dos conjuntos ativos preservando trilha histórica;
- revisão histórica reutiliza infraestrutura existente em vez de criar um histórico paralelo.

### Nexo

- mutações novas serializam pelo usuário e bloqueiam recursos financeiros relevantes;
- chaves idempotentes impedem duplicação e divergência;
- escrita, auditoria, revisão e efeitos internos ficam no fluxo transacional;
- SQLite/CI não substitui teste concorrente no banco de produção.

### Íris

- consultas e mutações novas são restritas por `user_id`;
- destino de crédito exige o mesmo cartão;
- tentativa cross-user é coberta por teste sem escrita parcial.

### Bento

A matriz automatizada cobre não pago, parcial, integral, crédito parcial, replay, divergência, isolamento cross-user, antecipação com desconto e fallback de alerts sem migration. A suíte completa permanece o gate antes de merge.

### Maia

E3/E5/E6 deixam de ter item lógico conhecido aguardando implementação. O próximo ciclo é exclusivamente evidência externa, visual e publicação, salvo regressão concreta descoberta pelos testes.

## O que ainda falta — não é nova lógica de contrato

1. Último HEAD da branch com CI completo verde.
2. Concorrência no mesmo mecanismo de banco adotado na hospedagem; SQLite não comprova locks/deadlocks.
3. Teste manual/visual do Chefe no Microsoft Edge desktop/mobile, incluindo `Avisos financeiros` e `Correções de cartão`.
4. Aplicar/ensaiar migrations no banco de destino com backup e rollback.
5. Verificar `APP_KEY`, OAuth, HTTPS, filas, scheduler, secrets e ausência de seeder de desenvolvimento.
6. Smoke test após implantação.
7. Somente depois integrar/mesclar para a branch definida; não fazer push direto na `main`.

## Para o Codex

Se este chat precisar ser continuado pelo Codex:

- não reimplemente estorno/crédito;
- confira primeiro o último workflow da `codex/v1-card-reversal`;
- se falhar, corrija a regressão mantendo os contratos acima;
- se estiver verde, faça revisão do diff e prepare evidência para teste concorrente/visual/deploy;
- mantenha `FINANCIAL_PLANNING_STAGES.md` e este handoff sincronizados;
- só comece `DAILY_FINANCIAL_ENGINE_PROPOSAL.md` depois que o V1 estiver estabilizado/aprovado.

## Invariantes permanentes

- decimais financeiros exatos, nunca `float` nas regras;
- isolamento por `user_id`;
- idempotência e rejeição de chave repetida com parâmetros diferentes;
- auditoria e escrita financeira atômicas;
- transferência interna não vira renda/despesa;
- crédito de cartão não é renda nem caixa;
- estorno não apaga cronologia financeira passada;
- fechamento apresentado não é reescrito silenciosamente;
- falha do módulo de alertas não bloqueia a escrita financeira principal;
- WhatsApp/OFX não bloqueiam V1;
- V2 não entra silenciosamente no V1.
