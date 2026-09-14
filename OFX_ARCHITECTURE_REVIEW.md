# Revisão curta dos agentes — arquitetura OFX e Safe Data Reset

Data: 2026-09-13  
Escopo: `OFX_IMPORT_CONTRACT.md` + `SAFE_DATA_RESET_CONTRACT.md`  
Natureza: revisão consultiva antes da implementação.

## Inv — fundamentos financeiros

**Parecer:** aprovado com ressalvas conceituais já incorporadas ao contrato.

1. `CREDIT` bancário não pode ser assumido como renda e `DEBIT` não pode ser assumido como despesa econômica sem contexto.
2. Pix no Crédito precisa ser tratado como operação financiada/cartão, e não como entrada de renda seguida de gasto comum. Caso contrário, renda, gasto, margem e ritmo mensal ficam artificialmente inflados.
3. Transferências entre patrimônios próprios são neutras para renda/despesa e devem permanecer fora desses indicadores.
4. `LEDGERBAL` é evidência de conferência, não fonte autorizativa para criar ajuste ou alterar saldo do FinanSys.
5. Categoria e classificação de planejamento são indispensáveis para que despesas importadas participem corretamente de orçamento, essenciais, margem e projeções.
6. Importação automática deve preferir `needs_review` quando houver dúvida econômica. É melhor não classificar do que classificar incorretamente e contaminar indicadores.

**Risco principal apontado pelo Inv:** uma importação tecnicamente correta pode ser financeiramente errada se refletir apenas o sinal bancário. A camada de classificação econômica é obrigatória.

## Lia — produto

**Parecer:** jornada coerente e verificável.

- Preview permanece obrigatório antes de qualquer efeito financeiro.
- Usuário entende o que é novo, duplicado, pendente ou relacionado.
- Categoria ausente bloqueia somente o item correspondente, não impede análise do restante do arquivo.
- Operações compostas devem aparecer como uma unidade para evitar que o usuário precise compreender detalhes internos do OFX.
- Conciliação deve explicar diferença, nunca oferecer “corrigir saldo automaticamente”.
- Mobile deve usar cards e ações claras, pois o teste real será feito também pelo celular.

## Atlas — arquitetura

**Parecer:** separação de responsabilidades adequada.

- Parser e normalizador permanecem puros.
- Detector de relações precede o classificador.
- Persistência da importação é separada dos fatos financeiros.
- Domínio existente (`LedgerEntry`, cartão, transferência) continua fonte da regra financeira.
- `FITID` é identificador preferencial, mas fingerprint de fallback é necessário.
- Deduplicação precisa de constraint no banco, não apenas `exists()` em aplicação.
- O arquivo OFX original não precisa ser persistido permanentemente.

**Ponto para implementação:** modelar referência do item importado para o objeto de domínio sem criar acoplamento frágil ou exigir uma segunda fonte de verdade financeira.

## Bento — verificabilidade

**Parecer:** arquitetura testável; gates mínimos definidos.

Cobertura mínima antes de considerar pronta:

- fixture anonimizada equivalente ao OFX real do Nubank;
- parser válido/inválido, timezone, acentos e ausência de `FITID`;
- Pix no Crédito correlacionado sem gerar renda fictícia;
- importação repetida sem duplicar;
- período repetido com operação nova;
- categoria incompatível/cross-user rejeitada;
- adulteração de valor/data pelo frontend ignorada/rejeitada;
- rollback de lote em falha inesperada;
- isolamento entre usuários;
- reset remove uso real e preserva estruturas;
- reset remove derivados/snapshots aplicáveis;
- build e responsividade desktop/mobile.

## Conclusão coordenada

Não foi identificado bloqueio conceitual para iniciar a implementação.

A principal decisão preservada após a revisão é: **o importador não converte linhas OFX diretamente em renda/despesa; ele converte eventos bancários em fatos financeiros somente depois de correlação, classificação e validação do domínio.**

O Safe Data Reset também permanece separado do importador e só será implementado após matriz explícita de dependências `modelo/tabela -> preservar/remover`.

Nenhum destes pareceres substitui a autorização do Chefe para deploy; eles servem como gate técnico/financeiro antes do código.
