<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CalendarDays, Filter } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({
    evaluations: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const selectedView = ref(props.filters.view);
const from = ref(props.filters.from);
const to = ref(props.filters.to);
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => currency.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
const formatDateTime = (value) => new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
const formatPercent = (value) => value === null || value === undefined ? 'Sem comparação' : `${Number(value).toFixed(2)}%`;
const situationDetails = {
    no_basis: { label: 'Sem base para comparar', tone: 'text-slate-700' },
    insufficient: { label: 'Verba insuficiente', tone: 'text-rose-700' },
    under_control: { label: 'Sob controle', tone: 'text-emerald-700' },
    balanced: { label: 'Em equilíbrio', tone: 'text-amber-700' },
    outside_plan: { label: 'Fora do planejado', tone: 'text-rose-700' },
};
const sourceLabel = (source) => source === 'reconstructed' ? 'Reconstruída posteriormente' : 'Registrada no fechamento';
const situationLabel = (situation) => situationDetails[situation]?.label ?? 'Sem diagnóstico';
const situationTone = (situation) => situationDetails[situation]?.tone ?? 'text-slate-700';

const applyFilters = () => {
    router.get(route('financial-evaluations.index'), {
        view: selectedView.value,
        from: from.value,
        to: to.value,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Histórico financeiro" />
    <AuthenticatedLayout>
        <div class="mx-auto flex w-full min-w-0 max-w-4xl flex-col gap-5">
            <header class="flex flex-col gap-3">
                <Link :href="route('dashboard')" class="inline-flex items-center gap-2 self-start text-sm font-medium text-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-500"><ArrowRight :size="16" class="rotate-180" />Voltar ao painel</Link>
                <div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><CalendarDays :size="19" /></span><div><h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Histórico financeiro</h1><p class="mt-1 text-sm leading-6 text-slate-500">Avaliações preservadas no fechamento diário, separadas do saldo bancário.</p></div></div>
            </header>

            <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end" aria-label="Filtros do histórico">
                <div class="flex min-w-0 flex-1 flex-col gap-2"><label for="evaluation-view" class="text-xs font-semibold text-slate-600">Visão</label><select id="evaluation-view" v-model="selectedView" class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="current">Atual</option><option value="projected">Projetada</option></select></div>
                <div class="flex min-w-0 flex-1 flex-col gap-2"><label for="evaluation-from" class="text-xs font-semibold text-slate-600">De</label><input id="evaluation-from" v-model="from" type="date" class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></div>
                <div class="flex min-w-0 flex-1 flex-col gap-2"><label for="evaluation-to" class="text-xs font-semibold text-slate-600">Até</label><input id="evaluation-to" v-model="to" type="date" class="w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></div>
                <button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-emerald-500" @click="applyFilters"><Filter :size="16" />Aplicar</button>
            </section>

            <p v-if="!evaluations.data.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm leading-6 text-slate-500">Nenhuma avaliação encontrada neste período e visão.</p>
            <ul v-else class="flex min-w-0 flex-col gap-3" aria-label="Avaliações financeiras">
                <li v-for="evaluation in evaluations.data" :key="evaluation.id" class="flex min-w-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="font-semibold text-slate-950">{{ formatDate(evaluation.evaluation_date) }}</h2><p class="mt-1 text-xs text-slate-500">{{ sourceLabel(evaluation.source) }} · revisão {{ evaluation.revision }} · atualizado {{ formatDateTime(evaluation.evaluated_at) }}</p></div><span class="self-start rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Regras {{ evaluation.rules_version }}</span></div>
                    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Situação</dt><dd class="mt-1 font-semibold" :class="situationTone(evaluation.result.situation)">{{ situationLabel(evaluation.result.situation) }}</dd></div><div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Percentual</dt><dd class="mt-1 font-semibold text-slate-950">{{ formatPercent(evaluation.result.percentage) }}</dd></div><div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Base da visão</dt><dd class="mt-1 truncate font-semibold text-slate-950" :title="formatMoney(evaluation.result.base)">{{ formatMoney(evaluation.result.base) }}</dd></div></dl>
                    <p v-if="evaluation.result.reasons?.length" class="text-xs leading-5 text-amber-700">{{ evaluation.result.reasons.join(' ') }}</p>
                </li>
            </ul>
            <nav v-if="evaluations.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Paginação do histórico"><Link v-if="evaluations.prev_page_url" :href="evaluations.prev_page_url" class="rounded-xl border border-slate-300 px-4 py-3 focus-visible:ring-2 focus-visible:ring-emerald-500">Anterior</Link><span class="text-slate-500">Página {{ evaluations.current_page }} de {{ evaluations.last_page }}</span><Link v-if="evaluations.next_page_url" :href="evaluations.next_page_url" class="rounded-xl border border-slate-300 px-4 py-3 focus-visible:ring-2 focus-visible:ring-emerald-500">Próxima</Link></nav>
        </div>
    </AuthenticatedLayout>
</template>
