<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpenCheck, CircleHelp, Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';

const search = ref('');

const sections = [
    {
        order: 1,
        title: '1. Comece pela base',
        description: 'Estruture onde o dinheiro existe antes de tentar interpretar indicadores.',
        articles: [
            { title: 'Como criar minha primeira conta?', summary: 'Cadastre o banco, carteira ou lugar onde o dinheiro realmente está.', keywords: 'conta saldo inicial banco carteira começar', steps: ['Abra Contas.', 'Clique em Criar conta.', 'Informe o nome e o saldo inicial real daquele momento.', 'Salve e use essa conta como base das próximas movimentações.'], cta: { label: 'Ir para Contas', href: route('accounts.index', { create: 1, from: 'help' }) } },
            { title: 'Como criar uma caixinha?', summary: 'Separe uma reserva sem inventar um novo saldo.', keywords: 'caixinha reserva objetivo transferir', steps: ['Tenha pelo menos uma conta ativa.', 'Abra Caixinhas e crie a reserva vinculada à conta.', 'A caixinha começa zerada.', 'Transfira dinheiro da conta para a caixinha quando quiser reservar de verdade.'], cta: { label: 'Ir para Caixinhas', href: route('pockets.index', { create: 1, from: 'help' }) } },
            { title: 'Como organizar categorias?', summary: 'Crie categorias compatíveis com receitas e despesas antes de lançar.', keywords: 'categoria receita despesa organizar', steps: ['Abra Categorias.', 'Escolha se a categoria é de receita ou despesa.', 'Dê um nome claro.', 'Mantenha ativa enquanto quiser usá-la em novos lançamentos.'], cta: { label: 'Ir para Categorias', href: route('categories.index', { from: 'help' }) } },
        ],
    },
    {
        order: 2,
        title: '2. Registre o que realmente aconteceu',
        description: 'Depois da base, registre entradas, saídas e transferências sem misturar os conceitos.',
        articles: [
            { title: 'Como adicionar uma receita?', summary: 'Registre dinheiro que realmente entrou.', keywords: 'receita renda entrada salário', steps: ['Tenha uma conta e uma categoria de receita.', 'Abra Lançamentos já no modo Receita.', 'Informe valor, data, conta e categoria.', 'Confirme. A receita recebida passa a afetar o saldo.'], cta: { label: 'Adicionar receita', href: route('ledger-entries.index', { create: 'income', from: 'help' }) } },
            { title: 'Como adicionar uma despesa?', summary: 'Registre o gasto e classifique seu papel no planejamento.', keywords: 'despesa gasto compra saída', steps: ['Tenha uma conta e uma categoria de despesa.', 'Abra Lançamentos no modo Despesa.', 'Informe valor, data, conta e categoria.', 'Escolha a classificação de planejamento adequada e confirme.'], cta: { label: 'Adicionar despesa', href: route('ledger-entries.index', { create: 'expense', from: 'help' }) } },
            { title: 'Como transferir entre conta e caixinha?', summary: 'Mova dinheiro internamente sem criar receita ou despesa.', keywords: 'transferência transferir conta caixinha', steps: ['Abra o fluxo de transferência.', 'Escolha origem e destino.', 'Informe o valor.', 'Confirme. O patrimônio total não aumenta nem diminui por causa da transferência.'], cta: { label: 'Fazer transferência', href: route('ledger-entries.index', { transfer: 1, from: 'help' }) } },
            { title: 'Como registrar um reembolso?', summary: 'Devolva parte ou todo um gasto sem transformar o valor em renda.', keywords: 'reembolso devolver gasto estorno despesa', steps: ['Abra Lançamentos e localize a despesa original.', 'Use a ação de reembolso quando houver valor elegível.', 'Escolha a conta que recebeu o dinheiro e informe o valor.', 'O reembolso reduz o efeito daquela despesa sem aparecer como nova renda.'], cta: { label: 'Ver Lançamentos', href: route('ledger-entries.index', { from: 'help' }) } },
        ],
    },
    {
        order: 3,
        title: '3. Informe o futuro e configure o planejamento',
        description: 'Só depois dos fatos básicos vale enriquecer o sistema com previsões, compromissos e orçamento.',
        articles: [
            { title: 'Como cadastrar um recebimento previsto?', summary: 'Planeje uma entrada sem fingir que o dinheiro já chegou.', keywords: 'recebimento previsto salário futuro previsão renda', steps: ['Abra Recebimentos previstos.', 'Cadastre valor e data esperada.', 'Use recorrência quando fizer sentido.', 'Quando receber, registre pelo fluxo próprio para que a previsão vire fato corretamente.'], cta: { label: 'Ir para Recebimentos previstos', href: route('receipt-forecasts.index', { from: 'help' }) } },
            { title: 'Como cadastrar um compromisso futuro?', summary: 'Registre uma obrigação conhecida sem diminuir o saldo antes do pagamento.', keywords: 'compromisso futuro conta pagar fixo vencimento parcela', steps: ['Abra Compromissos futuros.', 'Informe conta, categoria, descrição, valor e vencimento.', 'Escolha o comportamento no planejamento.', 'Cadastre sem alterar o saldo; o efeito em caixa acontece quando houver pagamento.'], cta: { label: 'Ir para Compromissos futuros', href: route('expense-commitments.index', { from: 'help' }) } },
            { title: 'Como registrar pagamento parcial de um compromisso?', summary: 'Baixe apenas a parte efetivamente paga e preserve o saldo restante.', keywords: 'compromisso pagamento parcial parcela restante', steps: ['Abra Compromissos futuros.', 'Localize a obrigação.', 'Use a ação de pagamento e informe somente o valor realmente pago.', 'O compromisso mantém o residual até ser quitado, corrigido ou cancelado.'], cta: { label: 'Ver Compromissos futuros', href: route('expense-commitments.index', { from: 'help' }) } },
            { title: 'Como configurar meus gastos essenciais?', summary: 'Reserve parte da verba mensal para categorias essenciais.', keywords: 'essenciais alimentação orçamento planejamento', steps: ['Crie as categorias de despesa necessárias.', 'Abra Configuração financeira.', 'Escolha o mês.', 'Defina proteção e reservas essenciais sem confundir reserva com gasto realizado.'], cta: { label: 'Configurar mês', href: route('financial-settings.edit', { from: 'help' }) } },
            { title: 'Como definir meu orçamento diário?', summary: 'Escolha uma referência diária voluntária para comparar comportamento.', keywords: 'orçamento diário gasto dia margem folga', steps: ['Abra Orçamento diário.', 'Informe quanto pretende gastar por dia.', 'O valor não bloqueia compras nem reduz seu saldo.', 'Use os check-ins para comparar gasto elegível, folga e excesso.'], cta: { label: 'Definir orçamento diário', href: route('daily-budgets.edit', { from: 'help' }) } },
        ],
    },
    {
        order: 4,
        title: '4. Configure cartões quando fizer sentido',
        description: 'Cartão é opcional. Cadastre somente se você realmente usa esse fluxo.',
        articles: [
            { title: 'Como cadastrar e usar um cartão?', summary: 'Cadastre datas, limite e depois registre compras.', keywords: 'cartão crédito limite compra parcela fatura', steps: ['Abra Cartões e cadastre o cartão.', 'Informe o limite total se quiser controlar capacidade disponível.', 'Registre compras e parcelas pelo cartão.', 'Pagamentos da fatura liquidam obrigações; não viram uma segunda despesa.'], cta: { label: 'Ir para Cartões', href: route('credit-cards.index', { from: 'help' }) } },
            { title: 'Como atualizar o limite do cartão?', summary: 'Mantenha o limite total alinhado ao valor real informado pela operadora.', keywords: 'cartão limite atualizar disponível usado', steps: ['Abra Cartões.', 'Localize o cartão.', 'Abra a edição de limite.', 'Informe o limite total atual ou remova o limite configurado quando não quiser controlá-lo.'], cta: { label: 'Ir para Cartões', href: route('credit-cards.index', { from: 'help' }) } },
            { title: 'Como pagar ou antecipar o cartão?', summary: 'Liquide obrigações do cartão sem gerar uma segunda despesa.', keywords: 'cartão pagamento antecipar parcela fatura antecipação', steps: ['Abra Cartões.', 'Escolha pagamento para liquidar valores já devidos ou antecipação para parcelas elegíveis.', 'Informe o valor conforme o fluxo escolhido.', 'O sistema atualiza obrigações e limite sem contar o pagamento como novo consumo.'], cta: { label: 'Ir para Cartões', href: route('credit-cards.index', { from: 'help' }) } },
            { title: 'Como funciona estorno e crédito do cartão?', summary: 'Crédito nasce de estorno elegível; não é renda nem dinheiro em conta.', keywords: 'estorno crédito cartão correção', steps: ['Abra Correções de cartão.', 'Escolha uma compra elegível.', 'Registre o estorno.', 'Se já havia valor pago elegível, o sistema pode gerar crédito para aplicação futura.'], cta: { label: 'Abrir Correções de cartão', href: route('card-corrections.index', { from: 'help' }) } },
        ],
    },
    {
        order: 5,
        title: '5. Construa metas e patrimônio',
        description: 'Com fluxo e planejamento organizados, acompanhe objetivos e riqueza patrimonial.',
        articles: [
            { title: 'Como criar uma meta financeira?', summary: 'Defina objetivo, prazo e vincule uma caixinha se quiser medir reserva real.', keywords: 'meta objetivo reserva prazo caixinha', steps: ['Abra a aba Metas no dashboard.', 'Informe nome, valor-alvo e data.', 'Opcionalmente vincule uma caixinha.', 'Sem caixinha, o FinanSys não presume dinheiro reservado.'], cta: { label: 'Criar uma meta', href: route('dashboard', { view: 'goals', from: 'help' }) } },
            { title: 'Como calcular meu patrimônio estimado?', summary: 'Some patrimônio financeiro e valor líquido dos bens sem tratar bens como dinheiro disponível.', keywords: 'patrimônio bem moto carro imóvel dívida financiamento líquido estimado', steps: ['Abra Patrimônio.', 'Adicione o bem pelo valor aproximado de mercado hoje.', 'Informe o saldo devedor atual caso exista financiamento ou dívida vinculada.', 'O sistema calcula o patrimônio líquido do bem: valor estimado menos dívida.', 'O dashboard soma esse valor ao patrimônio financeiro, mas mantém liquidez separada.'], cta: { label: 'Adicionar bem ao patrimônio', href: route('patrimony.index', { create: 1, from: 'help' }) } },
            { title: 'Como atualizar um bem financiado?', summary: 'Atualize valor estimado e saldo devedor usando referências atuais, não a soma das parcelas.', keywords: 'patrimônio financiamento dívida moto carro parcela saldo devedor atualizar', steps: ['Abra Patrimônio e edite o bem.', 'Atualize o valor aproximado de mercado se ele mudou.', 'Consulte o saldo devedor atual na financeira ou banco e informe esse valor.', 'Não diminua a dívida pelo valor bruto das parcelas pagas: parte delas pode ser juros e encargos.'], cta: { label: 'Abrir Patrimônio', href: route('patrimony.index', { from: 'help' }) } },
            { title: 'Qual a diferença entre patrimônio e liquidez?', summary: 'Patrimônio mede valor econômico; liquidez mostra o dinheiro financeiro disponível sem vender bens.', keywords: 'patrimônio liquidez dinheiro disponível bens diferença', steps: ['Liquidez financeira considera contas e caixinhas.', 'Bens entram pelo valor estimado menos a dívida vinculada.', 'Uma moto pode aumentar seu patrimônio sem aumentar o dinheiro disponível hoje.', 'Use os dois indicadores juntos para evitar confundir riqueza estimada com poder de compra imediato.'], cta: { label: 'Ver Patrimônio', href: route('patrimony.index', { from: 'help' }) } },
        ],
    },
    {
        order: 6,
        title: '6. Revise, corrija e entenda o histórico',
        description: 'Quando algo estiver errado, corrija o fato em vez de compensar com lançamentos fictícios.',
        articles: [
            { title: 'Como corrigir uma movimentação?', summary: 'Use correção ou estorno para preservar rastreabilidade.', keywords: 'corrigir erro lançamento estorno histórico', steps: ['Abra Lançamentos e localize o registro.', 'Use o fluxo de correção quando o dado original estiver incorreto.', 'Use estorno quando precisar desfazer o efeito.', 'Evite criar uma movimentação artificial apenas para “bater” o saldo.'], cta: { label: 'Ver Lançamentos', href: route('ledger-entries.index', { from: 'help' }) } },
            { title: 'Onde vejo meu histórico financeiro?', summary: 'Consulte avaliações e revisões preservadas pelo motor financeiro.', keywords: 'histórico revisão check-in avaliação', steps: ['Abra o Histórico financeiro.', 'Escolha o período relevante.', 'Compare o estado registrado com revisões posteriores.', 'Use a proveniência para entender mudanças sem reescrever silenciosamente o passado.'], cta: { label: 'Abrir Histórico', href: route('financial-evaluations.index', { from: 'help' }) } },
            { title: 'Como corrigir um check-in diário?', summary: 'Revise um dia sem apagar a proveniência do resultado anterior.', keywords: 'check-in checkin corrigir diário revisão orçamento', steps: ['Abra o orçamento/check-in diário.', 'Localize o dia que precisa de correção.', 'Use a ação de correção em vez de criar um ajuste fictício.', 'A revisão preserva a leitura histórica e recalcula os indicadores atuais conforme as regras da V2.'], cta: { label: 'Abrir Orçamento diário', href: route('daily-budgets.edit', { from: 'help' }) } },
        ],
    },
    {
        order: 7,
        title: '7. Diagnóstico e recursos avançados',
        description: 'Use estes recursos quando precisar investigar o sistema ou importar informações.',
        articles: [
            { title: 'Como conferir se um indicador está correto?', summary: 'Exporte fatos e indicadores para reproduzir os cálculos fora da interface.', keywords: 'json diagnóstico indicador divergência exportar auditoria', steps: ['Abra Meu perfil.', 'Na área avançada, baixe o JSON de diagnóstico.', 'O arquivo contém fatos financeiros e indicadores derivados, sem credenciais.', 'Use o arquivo para recalcular e comparar divergências.'], cta: { label: 'Abrir Meu perfil', href: route('profile.edit', { from: 'help' }) } },
            { title: 'Como importar um OFX?', summary: 'O módulo existe, mas pode estar desativado nesta instalação.', keywords: 'ofx extrato importar banco', steps: ['Quando o recurso estiver habilitado, abra Importar extrato OFX.', 'Escolha a conta.', 'Envie o arquivo e revise a interpretação.', 'Confirme apenas os itens corretos; preview não altera seu dinheiro.'], cta: null },
            { title: 'Como recomeçar meus dados de teste?', summary: 'Use a limpeza operacional somente quando realmente quiser zerar fatos financeiros.', keywords: 'reset limpar zerar dados teste', steps: ['Abra Meu perfil e a área avançada.', 'Leia o escopo preservado e removido.', 'Passe pela confirmação forte.', 'A identidade e estruturas preservadas não são apagadas como um migrate:fresh.'], cta: { label: 'Abrir Configurações avançadas', href: route('profile.edit', { from: 'help' }) } },
        ],
    },
];

const normalizeText = value => value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('pt-BR')
    .trim();

const normalizedSearch = computed(() => normalizeText(search.value));
const visibleSections = computed(() => {
    if (!normalizedSearch.value) return sections;
    return sections
        .map(section => ({
            ...section,
            articles: section.articles.filter(article => normalizeText([article.title, article.summary, article.keywords, ...article.steps].join(' ')).includes(normalizedSearch.value)),
        }))
        .filter(section => section.articles.length);
});
</script>

<template>
    <Head title="Central de ajuda" />
    <AuthenticatedLayout>
        <section class="mx-auto max-w-5xl">
            <div class="rounded-3xl bg-slate-950 px-5 py-7 text-white sm:px-8 sm:py-9">
                <div class="flex items-start gap-4"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-400/15 text-emerald-300"><BookOpenCheck :size="24" /></span><div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300">Central de ajuda</p><h1 class="mt-1 text-2xl font-semibold sm:text-3xl">O que você quer fazer no FinanSys?</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Você pode pesquisar uma dúvida ou seguir a ordem abaixo. A sequência acompanha a forma natural de configurar e aprender o sistema.</p></div></div>
                <div class="relative mt-6"><Search :size="18" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" /><input v-model="search" type="search" class="w-full rounded-2xl border-0 bg-white py-3 pl-11 pr-11 text-sm text-slate-950 ring-1 ring-white/10 focus:ring-2 focus:ring-emerald-400" placeholder="Ex.: adicionar despesa, patrimônio, cartão, meta..." /><button v-if="search" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1 text-slate-400 hover:bg-slate-100" aria-label="Limpar pesquisa" @click="search = ''"><X :size="17" /></button></div>
            </div>

            <div v-if="visibleSections.length" class="mt-7 space-y-8">
                <section v-for="section in visibleSections" :key="section.order" class="scroll-mt-24">
                    <div class="mb-3"><h2 class="text-lg font-semibold text-slate-950">{{ section.title }}</h2><p class="mt-1 text-sm text-slate-500">{{ section.description }}</p></div>
                    <div class="space-y-3">
                        <details v-for="article in section.articles" :key="article.title" class="group rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-4 sm:px-5"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><CircleHelp :size="18" /></span><span class="min-w-0 flex-1"><span class="block font-semibold text-slate-900">{{ article.title }}</span><span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ article.summary }}</span></span><ArrowRight :size="17" class="shrink-0 text-slate-400 transition group-open:rotate-90" /></summary>
                            <div class="border-t border-slate-100 px-4 pb-5 pt-4 sm:px-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Passo a passo</p>
                                <ol class="mt-3 space-y-3">
                                    <li v-for="(step, index) in article.steps" :key="step" class="flex gap-3 text-sm leading-6 text-slate-700"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-950 text-[11px] font-bold text-white">{{ index + 1 }}</span><span>{{ step }}</span></li>
                                </ol>
                                <Link v-if="article.cta" :href="article.cta.href" class="mt-5 inline-flex min-h-10 items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">{{ article.cta.label }} <ArrowRight :size="15" /></Link>
                                <p v-else class="mt-5 rounded-xl bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">Este recurso depende da configuração desta instalação e pode não aparecer no menu.</p>
                            </div>
                        </details>
                    </div>
                </section>
            </div>

            <div v-else class="mt-7 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"><CircleHelp :size="28" class="mx-auto text-slate-400" /><h2 class="mt-4 font-semibold text-slate-950">Nenhum passo encontrado</h2><p class="mt-2 text-sm text-slate-500">Tente pesquisar pelo que você quer fazer, como “despesa”, “cartão”, “meta” ou “patrimônio”.</p></div>
        </section>
    </AuthenticatedLayout>
</template>
