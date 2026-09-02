<script setup>
import CashFlowChart from '@/Components/CashFlowChart.vue';
import CategoryBreakdownChart from '@/Components/CategoryBreakdownChart.vue';
import GeneralBalanceChart from '@/Components/GeneralBalanceChart.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowRight, ArrowUpRight, CalendarDays, Filter, Landmark, ReceiptText, Tags, WalletCards } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    overview: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const selectedPeriod = ref(String(props.filters.period));
const selectedCategory = ref(props.filters.category_id ? String(props.filters.category_id) : 'all');
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(value));
const formatPercent = (value) => `${Number(value).toFixed(1)}%`;

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

        <section class="mt-4 grid gap-3 xl:grid-cols-[1.15fr_2fr]">
            <article class="flex min-h-32 flex-col justify-between rounded-2xl bg-slate-950 p-5 text-white shadow-lg shadow-slate-200"><div class="flex items-center justify-between gap-3 text-sm text-slate-400"><span class="flex items-center gap-2"><Landmark :size="17" /> Saldo geral</span><span class="text-xs">Contas + caixinhas</span></div><p class="mt-5 text-3xl font-semibold tracking-tight">{{ formatMoney(overview.general_balance) }}</p></article>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><article v-for="card in periodCards" :key="card.label" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><span class="flex h-8 w-8 items-center justify-center rounded-lg" :class="card.tone"><component :is="card.icon" :size="16" /></span><p class="mt-3 text-xs leading-4 text-slate-500">{{ card.label }}</p><p class="mt-1 truncate text-lg font-semibold tracking-tight text-slate-950" :title="formatMoney(card.value)">{{ formatMoney(card.value) }}</p></article></div>
        </section>

        <section class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-xs text-slate-500">Taxa de economia</p><p class="mt-1 font-semibold" :class="Number(overview.period_summary.savings_rate) >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatPercent(overview.period_summary.savings_rate) }}</p></article>
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-xs text-slate-500">Movimentações</p><p class="mt-1 font-semibold text-slate-900">{{ overview.period_summary.transaction_count }}</p></article>
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-xs text-slate-500">Maior despesa</p><p class="mt-1 truncate font-semibold text-rose-700" :title="formatMoney(overview.period_summary.largest_expense)">{{ formatMoney(overview.period_summary.largest_expense) }}</p></article>
            <article class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-xs text-slate-500">Categoria ativa</p><p class="mt-1 truncate font-semibold text-slate-900" :title="selectedCategoryName">{{ selectedCategoryName }}</p></article>
        </section>

        <section class="mt-6"><div class="mb-3"><h2 class="text-base font-semibold text-slate-950">Evolução financeira</h2><p class="text-sm text-slate-500">Saldo acumulado e entradas versus saídas no período.</p></div><div class="grid gap-4 xl:grid-cols-2"><GeneralBalanceChart :chart="overview.chart" /><CashFlowChart :cash-flow="overview.cash_flow" /></div></section>

        <section class="mt-6"><div class="mb-3"><h2 class="text-base font-semibold text-slate-950">Visualização por categoria</h2><p class="text-sm text-slate-500">Compare onde o dinheiro entrou e saiu sem perder o contexto.</p></div><div class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <CategoryBreakdownChart :categories="overview.category_breakdown" />
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="flex items-center justify-between gap-4"><div><h3 class="font-semibold text-slate-950">Atividade recente</h3><p class="text-xs text-slate-500">{{ selectedCategoryName }}</p></div><ReceiptText :size="18" class="text-slate-400" /></div><div v-if="overview.recent_entries.length" class="mt-3 divide-y divide-slate-100"><div v-for="entry in overview.recent_entries" :key="entry.id" class="flex items-center gap-3 py-2.5"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="entry.is_positive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><ArrowDownLeft v-if="entry.is_positive" :size="15" /><ArrowUpRight v-else :size="15" /></span><div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-slate-800">{{ entry.description || entry.type_label }}</p><p class="truncate text-xs text-slate-500">{{ entry.reference_name }} · {{ formatDate(entry.occurred_at) }}</p></div><p class="shrink-0 text-xs font-semibold" :class="entry.is_positive ? 'text-emerald-700' : 'text-rose-700'">{{ entry.is_positive ? '+' : '−' }} {{ formatMoney(entry.amount) }}</p></div></div><p v-else class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Nenhuma movimentação para estes filtros.</p></article>
        </div></section>

        <section class="mt-4 grid gap-3 sm:grid-cols-2"><Link :href="route('accounts.index')" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:bg-slate-50"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-700"><WalletCards :size="17" /></span><span class="flex-1 text-sm font-medium text-slate-800">Gerenciar contas</span><ArrowRight :size="16" class="text-slate-300 group-hover:text-slate-600" /></Link><Link :href="route('categories.index')" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:bg-slate-50"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-700"><Tags :size="17" /></span><span class="flex-1 text-sm font-medium text-slate-800">Gerenciar categorias</span><ArrowRight :size="16" class="text-slate-300 group-hover:text-slate-600" /></Link></section>
    </AuthenticatedLayout>
</template>
