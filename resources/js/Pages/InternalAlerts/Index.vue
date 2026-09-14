<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, BellRing, Filter } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({
    alerts: { type: Object, required: true },
    filters: { type: Object, required: true },
    schemaWarning: { type: String, default: null },
});

const selectedView = ref(props.filters.view);
const from = ref(props.filters.from);
const to = ref(props.filters.to);
const status = ref(props.filters.status);
const deficitOnly = ref(props.filters.deficit_only);
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
const formatDateTime = (value) => value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : null;
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => currency.format(Number(value));
const situationDetails = {
    no_basis: { label: 'Sem base para comparar', tone: 'text-slate-700' },
    insufficient: { label: 'Verba insuficiente', tone: 'text-rose-700' },
    under_control: { label: 'Sob controle', tone: 'text-emerald-700' },
    balanced: { label: 'Em equilíbrio', tone: 'text-amber-700' },
    outside_plan: { label: 'Fora do planejado', tone: 'text-rose-700' },
};
const situationLabel = (value) => situationDetails[value]?.label ?? 'Sem diagnóstico';
const situationTone = (value) => situationDetails[value]?.tone ?? 'text-slate-700';

const applyFilters = () => {
    router.get(route('internal-alerts.index'), {
        view: selectedView.value,
        from: from.value,
        to: to.value,
        status: status.value === 'all' ? undefined : status.value,
        deficit_only: deficitOnly.value ? 1 : undefined,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Avisos financeiros" />
    <AuthenticatedLayout>
        <div class="mx-auto flex w-full min-w-0 max-w-4xl flex-col gap-5">
            <header class="flex flex-col gap-3"><Link :href="route('dashboard')" class="inline-flex items-center gap-2 self-start text-sm font-medium text-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-500"><ArrowRight :size="16" class="rotate-180" />Voltar ao painel</Link><div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-700"><BellRing :size="19" /></span><div><h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Avisos financeiros</h1><p class="mt-1 text-sm leading-6 text-slate-500">Mudanças relevantes preservadas por visão e dia, sem confundir orçamento com saldo bancário.</p></div></div></header>
            <div v-if="schemaWarning" class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-900" role="alert"><AlertTriangle :size="18" class="mt-0.5 shrink-0" /><div><p class="font-semibold">Módulo ainda não sincronizado</p><p>{{ schemaWarning }}</p></div></div>
            <section class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end" aria-label="Filtros dos avisos"><div class="flex min-w-0 flex-1 flex-col gap-2"><label for="alert-view" class="text-xs font-semibold text-slate-600">Visão</label><select id="alert-view" v-model="selectedView" class="w-full rounded-xl border-slate-300 text-sm"><option value="current">Atual</option><option value="projected">Projetada</option></select></div><div class="flex min-w-0 flex-1 flex-col gap-2"><label for="alert-status" class="text-xs font-semibold text-slate-600">Estado</label><select id="alert-status" v-model="status" class="w-full rounded-xl border-slate-300 text-sm"><option value="all">Todos</option><option value="active">Ativos</option><option value="recovered">Recuperados</option></select></div><div class="flex min-w-0 flex-1 flex-col gap-2"><label for="alert-from" class="text-xs font-semibold text-slate-600">De</label><input id="alert-from" v-model="from" type="date" class="w-full rounded-xl border-slate-300 text-sm" /></div><div class="flex min-w-0 flex-1 flex-col gap-2"><label for="alert-to" class="text-xs font-semibold text-slate-600">Até</label><input id="alert-to" v-model="to" type="date" class="w-full rounded-xl border-slate-300 text-sm" /></div><label class="flex min-h-11 items-center gap-2 text-sm text-slate-600"><input v-model="deficitOnly" type="checkbox" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500" />Com déficit</label><button type="button" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800" @click="applyFilters"><Filter :size="16" />Aplicar</button></section>
            <p v-if="!alerts.data.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm leading-6 text-slate-500">Nenhum aviso encontrado neste período e visão.</p>
            <ul v-else class="flex min-w-0 flex-col gap-3" aria-label="Avisos financeiros"><li v-for="alert in alerts.data" :key="alert.id" class="flex min-w-0 flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"><div class="flex items-start justify-between gap-4"><div><h2 class="font-semibold text-slate-950">{{ formatDate(alert.alert_date) }}</h2><p class="mt-1 text-xs text-slate-500">{{ alert.recovered_at ? `Recuperado em ${formatDateTime(alert.recovered_at)}` : 'Aviso ativo' }}</p></div><AlertTriangle :size="19" class="shrink-0 text-rose-600" /></div><dl class="grid grid-cols-2 gap-3 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Situação atual</dt><dd class="mt-1 font-semibold" :class="situationTone(alert.current_situation)">{{ situationLabel(alert.current_situation) }}</dd></div><div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Pior situação</dt><dd class="mt-1 font-semibold" :class="situationTone(alert.worst_situation)">{{ situationLabel(alert.worst_situation) }}</dd></div><div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Déficit observado</dt><dd class="mt-1 font-semibold text-rose-700">{{ alert.deficit_seen ? 'Sim' : 'Não' }}{{ alert.current_deficit ? ` · ${formatMoney(alert.current_deficit)}` : '' }}</dd></div></dl><p v-if="alert.reasons.length" class="text-xs leading-5 text-amber-700">{{ alert.reasons.join(' ') }}</p></li></ul>
            <nav v-if="alerts.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Paginação dos avisos"><Link v-if="alerts.prev_page_url" :href="alerts.prev_page_url" class="rounded-xl border border-slate-300 px-4 py-3">Anterior</Link><span class="text-slate-500">Página {{ alerts.current_page }} de {{ alerts.last_page }}</span><Link v-if="alerts.next_page_url" :href="alerts.next_page_url" class="rounded-xl border border-slate-300 px-4 py-3">Próxima</Link></nav>
        </div>
    </AuthenticatedLayout>
</template>
