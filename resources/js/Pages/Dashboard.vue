<script setup>
import CashFlowChart from '@/Components/CashFlowChart.vue';
import CategoryBreakdownChart from '@/Components/CategoryBreakdownChart.vue';
import GeneralBalanceChart from '@/Components/GeneralBalanceChart.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowRight, ArrowUpRight, CalendarDays, CircleGauge, Filter, Landmark, ReceiptText, Settings2, Tags, WalletCards } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    overview: { type: Object, required: true },
    planning: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const selectedPeriod = ref(String(props.filters.period));
const selectedCategory = ref(props.filters.category_id ? String(props.filters.category_id) : 'all');
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(value));
const formatPercent = (value) => `${Number(value).toFixed(1)}%`;
const situationDetails = {
    no_basis: { label: 'Sem base para comparar', message: 'Ainda falta chão pra fazer essa conta, Chefe 🤝', tone: 'text-slate-700', bar: 'bg-slate-400' },
    insufficient: { label: 'Verba insuficiente', message: 'A conta apertou. Bora ajustar sem drama 😅', tone: 'text-rose-700', bar: 'bg-rose-500' },
    under_control: { label: 'Sob controle', message: 'Tá redondo por enquanto, meu parceiro 😎', tone: 'text-emerald-700', bar: 'bg-emerald-500' },
    balanced: { label: 'Em equilíbrio', message: 'Tá no limite saudável. Só não mete o louco 👀', tone: 'text-amber-700', bar: 'bg-amber-500' },
    outside_plan: { label: 'Fora do planejado', message: 'Passou da faixa. Respira e bora recalcular essa bagaça 🧭', tone: 'text-rose-700', bar: 'bg-rose-500' },
};
const planningSituation = (view) => {
    if (view.diagnostic_available || view.situation === 'insufficient') {
        return situationDetails[view.situation];
    }

    return situationDetails.no_basis;
};
const planningCurrent = computed(() => props.planning.configured ? planningSituation(props.planning.current) : null);
const planningProjected = computed(() => props.planning.configured ? planningSituation(props.planning.projected) : null);
const progressWidth = (percentage) => `${Math.min(Math.max(Number(percentage ?? 0), 0), 100)}%`;

const selectedCategoryName = computed(() => {
    if (selectedCategory.value === 'all') {
        return 'Todas as categorias';
    }

    return props.categories.find((category) => String(category.id) === selectedCategory.value)?.name ?? 'Categoria';
});

const applyFilters = () => {
    router.get(route('dashboard'), {
        period: selectedPeriod.value,
        category_id: selectedCategory.value === 'all' ? undefined : selectedCategory.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const periodCards = computed(() => [
    { label: 'Receitas', value: props.overview.period_summary.income, icon: ArrowDownLeft, tone: 'text-emerald-700 bg-emerald-50' },
    { label: 'Despesas', value: props.overview.period_summary.expense, icon: ArrowUpRight, tone: 'text-rose-700 bg-rose-50' },
    { label: 'Resultado', value: props.overview.period_summary.net, icon: Landmark, tone: Number(props.overview.period_summary.net) >= 0 ? 'text-emerald-700 bg-emerald-50' : 'text-rose-700 bg-rose-50' },
    { label: 'Média diária de despesas', value: props.overview.period_summary.average_daily_expense, icon: CalendarDays, tone: 'text-amber-700 bg-amber-50' },
]);
</script>

<template>
    <Head title="Visão geral" />
    <AuthenticatedLayout>
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-medium text-emerald-700">Olá, {{ $page.props.auth.user.name }}</p><h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Painel financeiro</h1><p class="mt-1 text-sm text-slate-500">Uma leitura rápida do seu saldo, fluxo e categorias.</p></div>
            <Link :href="route('ledger-entries.index')" class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-800">Ver lançamentos <ArrowRight :size="16" /></Link>
        </section>

        <section class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm lg:flex-row lg:items-center">
            <div class="flex items-center gap-2 px-2 text-sm font-semibold text-slate-700"><Filter :size="16" class="text-emerald-600" />Analisar período</div>
            <div class="grid flex-1 gap-2 sm:grid-cols-[10rem_minmax(13rem,1fr)]">
                <select v-model="selectedPeriod" class="rounded-xl border-slate-200 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500" @change="applyFilters"><option value="7">Últimos 7 dias</option><option value="15">Últimos 15 dias</option><option value="30">Últimos 30 dias</option><option value="60">Últimos 60 dias</option><option value="365">Último ano</option></select>
                <select v-model="selectedCategory" class="rounded-xl border-slate-200 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500" @change="applyFilters"><option value="all">Todas as categorias</option><optgroup label="Receitas"><option v-for="category in categories.filter((item) => item.type === 'income')" :key="category.id" :value="String(category.id)">{{ category.name }}</option></optgroup><optgroup label="Despesas"><option v-for="category in categories.filter((item) => item.type === 'expense')" :key="category.id" :value="String(category.id)">{{ category.name }}</option></optgroup></select>
            </div>
            <p class="hidden px-2 text-xs text-slate-500 2xl:block">O saldo geral permanece atual; os demais dados respeitam os filtros.</p>
        </section>

        <section class="mt-4 grid min-w-0 gap-3 xl:grid-cols-[1.15fr_2fr]">
            <article class="flex min-h-32 flex-col justify-between rounded-2xl bg-slate-950 p-5 text-white shadow-lg shadow-slate-200"><div class="flex items-center justify-between gap-3 text-sm text-slate-400"><span class="flex items-center gap-2"><Landmark :size="17" /> Saldo geral</span><span class="text-xs">Contas + caixinhas</span></div><p class="mt-5 text-3xl font-semibold tracking-tight">{{ formatMoney(overview.general_balance) }}</p></article>
            <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4"><article v-for="card in periodCards" :key="card.label" class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><span class="flex h-8 w-8 items-center justify-center rounded-lg" :class="card.tone"><component :is="card.icon" :size="16" /></span><p class="mt-3 text-xs leading-4 text-slate-500">{{ card.label }}</p><p class="mt-1 truncate text-lg font-semibold tracking-tight text-slate-950" :title="formatMoney(card.value)">{{ formatMoney(card.value) }}</p></article></div>
        </section>

        <section class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-xs text-slate-500">Taxa de economia</p><p class="mt-1 font-semibold" :class="Number(overview.period_summary.savings_rate) >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatPercent(overview.period_summary.savings_rate) }}</p></article>
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-xs text-slate-500">Movimentações</p><p class="mt-1 font-semibold text-slate-900">{{ overview.period_summary.transaction_count }}</p></article>
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-xs text-slate-500">Maior despesa</p><p class="mt-1 truncate font-semibold text-rose-700" :title="formatMoney(overview.period_summary.largest_expense)">{{ formatMoney(overview.period_summary.largest_expense) }}</p></article>
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-xs text-slate-500">Categoria ativa</p><p class="mt-1 truncate font-semibold text-slate-900" :title="selectedCategoryName">{{ selectedCategoryName }}</p></article>
        </section>

        <section class="mt-4 min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="planning-heading">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><CircleGauge :size="18" /></span><div><h2 id="planning-heading" class="font-semibold text-slate-950">Ritmo financeiro do mês</h2><p class="mt-0.5 text-xs leading-5 text-slate-500">Verba disponível, não saldo bancário. Atualizado em Brasília.</p></div></div>
                <div class="flex flex-wrap items-center gap-3"><Link :href="route('financial-evaluations.index')" class="inline-flex shrink-0 items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-800"><CalendarDays :size="15" />Histórico</Link><Link :href="route('financial-settings.edit', { month: planning.month })" class="inline-flex shrink-0 items-center gap-2 text-xs font-semibold text-emerald-700 hover:text-emerald-800"><Settings2 :size="15" />Configurar mês</Link></div>
            </div>

            <div v-if="!planning.configured" class="mt-4 rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">Sem chute: configure proteção e essenciais para o FinanSys calcular direito. 🧮</div>
            <div v-else class="mt-4 grid min-w-0 gap-4 lg:grid-cols-[1fr_1fr_auto] lg:items-center">
                <article class="min-w-0 rounded-xl bg-slate-50 p-3.5"><div class="flex items-center justify-between gap-3"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Uso da verba até hoje</p><p class="text-sm font-semibold" :class="planningCurrent.tone">{{ planningCurrent.label }}</p></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full transition-[width]" :class="planningCurrent.bar" :style="{ width: progressWidth(planning.current.percentage) }" /></div><div class="mt-2 flex items-end justify-between gap-3"><p class="text-xs text-slate-500">{{ planningCurrent.message }}</p><p class="shrink-0 text-lg font-semibold text-slate-950">{{ planning.current.percentage === null ? '—' : formatPercent(planning.current.percentage) }}</p></div></article>
                <article class="min-w-0 rounded-xl bg-slate-50 p-3.5"><div class="flex items-center justify-between gap-3"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimativa de uso até o fim do mês</p><p class="text-sm font-semibold" :class="planningProjected.tone">{{ planningProjected.label }}</p></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full transition-[width]" :class="planningProjected.bar" :style="{ width: progressWidth(planning.projected.percentage) }" /></div><div class="mt-2 flex items-end justify-between gap-3"><p class="text-xs text-slate-500">{{ planningProjected.message }}</p><p class="shrink-0 text-lg font-semibold text-slate-950">{{ planning.projected.percentage === null ? '—' : formatPercent(planning.projected.percentage) }}</p></div></article>
                <dl class="grid grid-cols-2 gap-x-5 gap-y-2 text-xs lg:min-w-48 lg:grid-cols-1"><div><dt class="text-slate-500">Margem livre agora</dt><dd class="mt-0.5 truncate font-semibold text-slate-900" :title="formatMoney(planning.free_margin)">{{ formatMoney(planning.free_margin) }}</dd></div><div><dt class="text-slate-500">Média livre por dia</dt><dd class="mt-0.5 truncate font-semibold text-slate-900" :title="formatMoney(planning.daily.amount)">{{ formatMoney(planning.daily.amount) }}</dd><p class="mt-0.5 text-[11px] leading-4 text-slate-400">{{ planning.daily.remaining_days }} dias, após essenciais</p></div><div><dt class="text-slate-500">Variáveis projetadas</dt><dd class="mt-0.5 truncate font-semibold text-slate-900" :title="formatMoney(planning.variable.projected)">{{ formatMoney(planning.variable.projected) }}</dd></div></dl>
                <p v-if="planning.reasons.length" class="text-xs leading-5 text-amber-700 lg:col-span-3">⚠️ {{ planning.reasons.join(' ') }}</p>
            </div>
        </section>

        <section class="mt-6 min-w-0"><div class="mb-3"><h2 class="text-base font-semibold text-slate-950">Evolução financeira</h2><p class="text-sm text-slate-500">Saldo acumulado e entradas versus saídas no período.</p></div><div class="grid min-w-0 gap-4 xl:grid-cols-2"><GeneralBalanceChart :chart="overview.chart" /><CashFlowChart :cash-flow="overview.cash_flow" /></div></section>

        <section class="mt-6"><div class="mb-3"><h2 class="text-base font-semibold text-slate-950">Visualização por categoria</h2><p class="text-sm text-slate-500">Compare onde o dinheiro entrou e saiu sem perder o contexto.</p></div><div class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <CategoryBreakdownChart :categories="overview.category_breakdown" />
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between gap-4"><div><h3 class="font-semibold text-slate-950">Atividade recente</h3><p class="text-xs text-slate-500">{{ selectedCategoryName }}</p></div><ReceiptText :size="18" class="text-slate-400" /></div><div v-if="overview.recent_entries.length" class="mt-3 divide-y divide-slate-100"><div v-for="entry in overview.recent_entries" :key="entry.id" class="flex items-center gap-3 py-2.5"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="entry.is_positive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><ArrowDownLeft v-if="entry.is_positive" :size="15" /><ArrowUpRight v-else :size="15" /></span><div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-slate-800">{{ entry.description || entry.type_label }}</p><p class="truncate text-xs text-slate-500">{{ entry.reference_name }} · {{ formatDate(entry.occurred_at) }}</p></div><p class="shrink-0 text-xs font-semibold" :class="entry.is_positive ? 'text-emerald-700' : 'text-rose-700'">{{ entry.is_positive ? '+' : '−' }} {{ formatMoney(entry.amount) }}</p></div></div><p v-else class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Nenhuma movimentação para estes filtros.</p></article>
        </div></section>

        <section class="mt-4 grid gap-3 sm:grid-cols-2"><Link :href="route('accounts.index')" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:bg-slate-50"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700"><WalletCards :size="17" /></span><span class="flex-1 text-sm font-medium text-slate-800">Gerenciar contas</span><ArrowRight :size="16" class="text-slate-300 group-hover:text-slate-600" /></Link><Link :href="route('categories.index')" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:bg-slate-50"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-700"><Tags :size="17" /></span><span class="flex-1 text-sm font-medium text-slate-800">Gerenciar categorias</span><ArrowRight :size="16" class="text-slate-300 group-hover:text-slate-600" /></Link></section>
    </AuthenticatedLayout>
</template>
