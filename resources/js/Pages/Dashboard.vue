<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GeneralBalanceChart from '@/Components/GeneralBalanceChart.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowRight, ArrowUpRight, Landmark, PiggyBank, ReceiptText, Sparkles, Tags, WalletCards } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({ overview: { type: Object, required: true } });
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(new Date(value));
const summaries = computed(() => [
    { label: 'Em contas', value: props.overview.accounts_balance, icon: WalletCards, color: 'bg-blue-50 text-blue-700' },
    { label: 'Em caixinhas', value: props.overview.pockets_balance, icon: PiggyBank, color: 'bg-violet-50 text-violet-700' },
    { label: 'Receitas no mês', value: props.overview.monthly_income, icon: ArrowDownLeft, color: 'bg-emerald-50 text-emerald-700' },
    { label: 'Despesas no mês', value: props.overview.monthly_expense, icon: ArrowUpRight, color: 'bg-rose-50 text-rose-700' },
]);
const categoryTotal = computed(() => props.overview.category_breakdown.reduce((total, category) => total + Math.abs(Number(category.total)), 0));
const categoryBreakdown = computed(() => props.overview.category_breakdown.map((category) => ({
    ...category,
    percentage: categoryTotal.value === 0 ? 0 : (Math.abs(Number(category.total)) / categoryTotal.value) * 100,
})));
</script>

<template>
    <Head title="Visão geral" />
    <AuthenticatedLayout>
        <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div><p class="text-sm font-medium text-emerald-700">Olá, {{ $page.props.auth.user.name }}</p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Visão geral</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Acompanhe sua vida financeira sem perder a origem de cada valor.</p></div>
            <p class="rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-medium text-slate-500">Dados atualizados agora</p>
        </section>
        <section class="mt-8 overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl shadow-slate-200 sm:p-8">
            <div class="flex flex-col justify-between gap-8 sm:flex-row sm:items-end"><div><div class="flex items-center gap-2 text-sm text-slate-400"><Landmark :size="17" />Saldo geral</div><p class="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">{{ formatMoney(overview.general_balance) }}</p><p class="mt-3 text-sm text-slate-400">Soma do saldo disponível nas contas e nas caixinhas.</p></div><div class="flex items-center gap-2 rounded-2xl bg-white/10 px-4 py-3 text-sm text-slate-300"><Sparkles :size="18" class="text-emerald-300" />Atualizado pelos lançamentos</div></div>
        </section>
        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="summary in summaries" :key="summary.label" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl" :class="summary.color"><component :is="summary.icon" :size="19" /></span><span class="text-xs font-medium text-slate-400">Atual</span></div><p class="mt-5 text-sm text-slate-500">{{ summary.label }}</p><p class="mt-1 text-xl font-semibold tracking-tight text-slate-950">{{ formatMoney(summary.value) }}</p></article>
        </section>
        <GeneralBalanceChart :chart="overview.chart" />
        <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-700"><Tags :size="19" /></span><div><h2 class="font-semibold text-slate-950">Movimentação por categoria</h2><p class="text-sm text-slate-500">Receitas e despesas no período selecionado do gráfico.</p></div></div>
            <div v-if="categoryBreakdown.length" class="mt-6 grid gap-4 sm:grid-cols-2">
                <article v-for="category in categoryBreakdown" :key="`${category.id ?? 'none'}-${category.type}`" class="rounded-2xl border border-slate-100 p-4">
                    <div class="flex items-center justify-between gap-4"><div><p class="text-sm font-semibold text-slate-800">{{ category.name }}</p><p class="text-xs text-slate-500">{{ category.type === 'income' ? 'Receita' : 'Despesa' }} · {{ category.percentage.toFixed(1) }}%</p></div><p class="text-sm font-semibold" :class="Number(category.total) >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ Number(category.total) >= 0 ? '+' : '−' }} {{ formatMoney(Math.abs(Number(category.total))) }}</p></div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" :class="Number(category.total) >= 0 ? 'bg-emerald-500' : 'bg-rose-500'" :style="{ width: `${Math.max(category.percentage, 2)}%` }" /></div>
                </article>
            </div>
            <p v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Ainda não há receitas ou despesas neste período.</p>
        </section>
        <section class="mt-6 grid gap-6 xl:grid-cols-[1.45fr_1fr]">
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between gap-4"><div><h2 class="font-semibold text-slate-950">Atividade recente</h2><p class="mt-1 text-sm text-slate-500">Seus últimos lançamentos.</p></div><Link :href="route('ledger-entries.index')" class="flex items-center gap-1.5 text-sm font-semibold text-emerald-700 hover:text-emerald-800">Ver todos<ArrowRight :size="16" /></Link></div><div v-if="overview.recent_entries.length" class="mt-6 grid gap-2"><div v-for="entry in overview.recent_entries" :key="entry.id" class="flex items-center gap-3 rounded-2xl px-3 py-3 hover:bg-slate-50"><span class="flex h-10 w-10 items-center justify-center rounded-xl" :class="entry.is_positive ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><ArrowDownLeft v-if="entry.is_positive" :size="18" /><ArrowUpRight v-else :size="18" /></span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-800">{{ entry.description || entry.type_label }}</p><p class="truncate text-xs text-slate-500">{{ entry.reference_name }} · {{ formatDate(entry.occurred_at) }}</p></div><p class="text-sm font-semibold" :class="entry.is_positive ? 'text-emerald-700' : 'text-rose-700'">{{ entry.is_positive ? '+' : '-' }} {{ formatMoney(entry.amount) }}</p></div></div><div v-else class="mt-8 flex min-h-44 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 text-center"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm"><ReceiptText :size="22" /></span><p class="mt-4 text-sm font-semibold text-slate-800">Nenhum lançamento ainda</p><p class="mt-1 max-w-sm text-sm text-slate-500">Quando você registrar receitas ou despesas, o histórico será exibido aqui.</p></div></article>
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-semibold text-slate-950">Primeiros passos</h2><p class="mt-1 text-sm text-slate-500">Prepare sua estrutura financeira.</p><div class="mt-5 grid gap-3"><Link :href="route('accounts.index')" class="group flex items-center gap-3 rounded-2xl border border-slate-200 p-4 hover:bg-slate-50"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><WalletCards :size="19" /></span><span class="flex-1 text-sm font-medium text-slate-800">Cadastre sua primeira conta</span><ArrowRight :size="17" class="text-slate-300 group-hover:text-slate-600" /></Link><Link :href="route('pockets.index')" class="group flex items-center gap-3 rounded-2xl border border-slate-200 p-4 hover:bg-slate-50"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><PiggyBank :size="19" /></span><span class="flex-1 text-sm font-medium text-slate-800">Organize uma caixinha</span><ArrowRight :size="17" class="text-slate-300 group-hover:text-slate-600" /></Link></div></article>
        </section>
    </AuthenticatedLayout>
</template>
