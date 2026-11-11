<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NfcSharedLayout from '@/Layouts/NfcSharedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Building2, ChevronRight, Factory, History, Plus, X } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import CompanyDetails from './CompanyDetails.vue';
import Editor from './Editor.vue';
import Notices from './Notices.vue';
import { date, money } from './format';
import './nfc.css';

const props = defineProps({
    schema_pending: { type: Boolean, default: false },
    shared: { type: Boolean, default: false },
    share_token: { type: String, default: null },
    share_links: { type: Array, default: () => [] },
    share_links_enabled: { type: Boolean, default: false },
    created_share_url: { type: String, default: null },
    today: { type: String, default: '' },
    summary: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    production: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    warranty_notices: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const filters = [
    { key: 'all', label: 'Todas' },
    { key: 'interested', label: 'Interessadas' },
    { key: 'sold', label: 'Com vendas' },
];
const filter = ref('all');
const search = ref('');
const productionSearch = ref('');
const activeRegistryTab = ref('companies');
const authenticatedUser = computed(() => usePage().props.auth?.user ?? null);
const dialog = ref(null);
const dialogHeading = ref(null);
const busy = ref(false);
const shareBusy = ref(false);
const copied = ref(false);
const nfcRoute = (name, params = {}) => props.shared
    ? route(name.replace(/^nfc\./, 'nfc.share.'), { token: props.share_token, ...params })
    : route(name, params);

function createShare() {
    if (shareBusy.value || props.shared || !props.share_links_enabled || !authenticatedUser.value) return;
    router.post(route('nfc.share-links.store'), {}, {
        preserveScroll: true,
        onStart: () => { shareBusy.value = true; },
        onFinish: () => { shareBusy.value = false; },
    });
}

function revokeShare(link) {
    if (shareBusy.value || props.shared || !authenticatedUser.value || !window.confirm('Revogar este link? Quem o recebeu perderá o acesso imediatamente.')) return;
    router.delete(route('nfc.share-links.destroy', { link: link.id }), {
        preserveScroll: true,
        onStart: () => { shareBusy.value = true; },
        onFinish: () => { shareBusy.value = false; },
    });
}

async function copyShare() {
    if (!props.created_share_url) return;
    try {
        await navigator.clipboard.writeText(props.created_share_url);
        copied.value = true;
    } catch (_) {
        copied.value = false;
    }
}

const companiesShown = computed(() => {
    const text = search.value.trim().toLocaleLowerCase('pt-BR');
    return props.companies.filter(company => {
        if (filter.value === 'interested' && !company.interested) return false;
        if (filter.value === 'sold' && !company.sold) return false;
        if (!text) return true;
        return [
            company.name, company.cnpj, company.phone,
            ...company.units.map(unit => unit.name),
            ...company.sales.flatMap(sale => [
                sale.id,
                ...sale.plates.map(plate => plate.label ?? ''),
            ]),
        ].some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(text));
    });
});
const unitRows = computed(() => companiesShown.value.flatMap(company => company.units.map(unit => {
    const sales = company.sales.filter(sale => sale.unit_id === unit.id);
    return {
        id: unit.id,
        companyId: company.id,
        companyName: company.name,
        identifier: company.cnpj,
        unitName: unit.name,
        productionId: unit.production_id ?? null,
        productionStatus: unit.production_status ?? null,
        sold: sales.reduce((sum, sale) => sum + sale.plates.length, 0),
        total: sales.reduce((sum, sale) => sum + sale.total_cents, 0),
        received: sales.reduce((sum, sale) => sum + sale.paid_cents, 0),
        outstanding: sales.reduce((sum, sale) => sum + sale.balance_cents, 0),
    };
})));
const productionShown = computed(() => {
    const text = productionSearch.value.trim().toLocaleLowerCase('pt-BR');
    return props.production.map(batch => ({
        ...batch,
        linkedUnits: props.companies.flatMap(company =>
            company.units.filter(unit => unit.production_id === batch.id)
                .map(unit => `${company.name} · ${unit.name}`)),
    })).filter(batch => !text || [
        batch.id,
        batch.initial ? 'Lote inicial' : 'Lote',
        batch.produced_on,
        batch.quantity,
        batch.customized_quantity,
        batch.cost_cents,
        batch.status === 'in_progress' ? 'Em fabricação' : 'Fabricado',
        ...batch.linkedUnits,
    ].some(value => String(value ?? '').toLocaleLowerCase('pt-BR').includes(text)));
});
const noticeCount = computed(() => props.notices.filter(notice => !notice.resolved_at).length + props.warranty_notices.length);
const hasInitialBatch = computed(() => props.production.some(batch => batch.initial));

const metrics = computed(() => {
    const totals = props.summary;
    const costsPending = totals.unknown_cost_batches;

    return [
        { label: 'Interessadas', value: totals.interested },
        { label: 'Fabricadas', value: totals.manufactured },
        { label: 'Em fabricação', value: totals.in_production ?? 0, tone: 'nfc-warning' },
        { label: 'Em customização', value: totals.in_customization },
        { label: 'Vendidas', value: totals.sold, detail: `${totals.sold_standard} normais · ${totals.sold_custom} personalizadas` },
        { label: 'Plaquinhas pagas', value: totals.paid_plates },
        { label: 'Com defeito', value: totals.defective ?? 0, tone: 'nfc-danger' },
        { label: 'Possível prejuízo', value: money(totals.estimated_loss_cents ?? 0), tone: 'nfc-danger',
            detail: totals.unestimated_defects ? (totals.unestimated_defects + ' defeito(s) sem valor estimado') : 'Estimativa, não baixa financeira' },
        { label: 'Custo de fabricação', value: costsPending && costsPending === props.production.length ? '—' : money(totals.production_cost_cents), detail: costsPending ? `${costsPending} ${costsPending === 1 ? 'lote sem custo informado' : 'lotes sem custo informado'}` : null },
        { label: 'Arrecadado', value: money(totals.received_cents), tone: 'nfc-success' },
        { label: 'A receber', value: money(totals.outstanding_cents), tone: 'nfc-warning' },
    ];
});

const metricPages = computed(() => {
    const pages = [];
    for (let index = 0; index < metrics.value.length; index += 3) {
        pages.push(metrics.value.slice(index, index + 3));
    }
    return pages;
});

const company = computed(() => props.companies.find(item => item.id === dialog.value?.companyId));
const sale = computed(() => company.value?.sales.find(item => item.id === dialog.value?.saleId));
const unit = computed(() => company.value?.units.find(item => item.id === dialog.value?.unitId));
const plate = computed(() => sale.value?.plates.find(item => item.id === dialog.value?.plateId));
const batch = computed(() => props.production.find(item => item.id === dialog.value?.batchId));

const titles = {
    notices: 'Avisos',
    history: 'Histórico de alterações',
    share: 'Links de acesso NFC',
    notice: 'Novo aviso',
    payment: 'Registrar pagamento',
    plate: 'Editar plaquinha',
};
const title = computed(() => {
    switch (dialog.value?.kind) {
        case 'company': return company.value?.name;
        case 'company-form': return company.value ? 'Editar empresa' : 'Nova empresa';
        case 'unit': return unit.value ? 'Editar unidade' : 'Nova unidade';
        case 'sale': return sale.value ? 'Editar venda' : 'Nova venda';
        case 'batch': return batch.value ? 'Editar lote' : dialog.value.initial ? 'Registrar lote inicial' : 'Novo lote';
        default: return titles[dialog.value?.kind];
    }
});

const historyVerb = { created: 'Cadastrado', updated: 'Atualizado', deleted: 'Excluído', revoked: 'Revogado' };
const historyKind = { company: 'Empresa', notice: 'Aviso', production: 'Fabricação', initial_production: 'Lote inicial', share_link: 'Link compartilhado' };
const datetime = text => new Date(text).toLocaleString('pt-BR');
function destroy(kind, context = {}) {
    if (busy.value) return;
    const routeNames = {
        company: ['nfc.companies.destroy', { company: context.companyId }],
        unit: ['nfc.units.destroy', { company: context.companyId, unit: context.unitId }],
        sale: ['nfc.sales.destroy', { company: context.companyId, sale: context.saleId }],
        plate: ['nfc.plates.destroy', { company: context.companyId, sale: context.saleId, plate: context.plateId }],
        payment: ['nfc.payments.destroy', { company: context.companyId, sale: context.saleId, payment: context.paymentId }],
        batch: ['nfc.production.destroy', { production: context.batchId }],
    };
    const target = routeNames[kind];
    if (!target || !window.confirm('Excluir este registro? A alteração ficará no histórico e poderá afetar os indicadores.')) return;
    router.delete(nfcRoute(target[0], target[1]), {
        preserveScroll: true,
        onStart: () => { busy.value = true; },
        onFinish: () => { busy.value = false; },
        onSuccess: () => { if (kind === 'company') dialog.value = null; },
    });
}

function showCompany(companyId, saleId = null) {
    dialog.value = { kind: 'company', companyId, saleId, previous: dialog.value?.kind === 'notices' ? dialog.value : null };
}

function edit(kind, context = {}) {
    dialog.value = { kind, ...context, previous: dialog.value };
}

function goBack() {
    if (busy.value) return;
    dialog.value = dialog.value?.previous ?? null;
}

function saved() {
    if (dialog.value?.kind === 'company-form' && !company.value) filter.value = 'all';
    busy.value = false;
    goBack();
}

function closeDialog() {
    if (!busy.value) dialog.value = null;
}

watch(() => props.created_share_url, value => {
    if (value && !props.shared && props.share_links_enabled && authenticatedUser.value) dialog.value = { kind: 'share' };
}, { immediate: true });

watch(dialog, async (current, previous) => {
    if (!current || !previous) return;
    await nextTick();
    dialogHeading.value?.focus();
});
</script>

<template>
    <component :is="shared ? NfcSharedLayout : AuthenticatedLayout">
        <Head title="Plaquinhas NFC" />

        <div class="flex min-w-0 flex-col gap-6 sm:gap-8">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Plaquinhas NFC</h1>
                <div v-if="!schema_pending" class="flex flex-wrap items-center gap-2">
                    <button v-if="!shared && share_links_enabled && authenticatedUser" type="button" class="nfc-button" @click="dialog = { kind: 'share' }">Compartilhar</button>
                    <button type="button" class="nfc-button" @click="dialog = { kind: 'history' }"><History :size="17" aria-hidden="true" /> Histórico</button>
                    <button type="button" class="nfc-button nfc-icon-button" :aria-label="`Avisos (${noticeCount})`" @click="dialog = { kind: 'notices' }">
                        <Bell :size="18" aria-hidden="true" />
                        <span v-if="noticeCount" class="nfc-badge nfc-warning tabular-nums" aria-hidden="true">{{ noticeCount }}</span>
                    </button>
                    <button type="button" class="nfc-button nfc-primary" @click="edit('company-form')">
                        <Plus :size="18" aria-hidden="true" /> Nova empresa
                    </button>
                </div>
            </header>

            <p v-if="schema_pending" class="nfc-panel nfc-warning p-4 text-sm" role="status">Aplique a migration de Plaquinhas NFC para liberar os cadastros.</p>

            <div v-else class="flex min-w-0 flex-col gap-1.5">
                <dl class="nfc-scrollbar flex snap-x snap-mandatory gap-2 overflow-x-auto overscroll-x-contain pb-2 sm:hidden"
                    aria-label="Indicadores das plaquinhas NFC, em grupos de até três" tabindex="0">
                    <div v-for="(pageMetrics, index) in metricPages" :key="index"
                        class="grid w-full min-w-full shrink-0 snap-start grid-cols-3 items-stretch gap-1.5"
                        role="group" :aria-label="`Indicadores ${index * 3 + 1} a ${index * 3 + pageMetrics.length}`">
                        <div v-for="metric in pageMetrics" :key="metric.label" class="nfc-panel flex min-w-0 flex-col gap-1 px-2 py-2.5">
                            <dt class="nfc-muted text-[10px] font-medium leading-tight">{{ metric.label }}</dt>
                            <dd class="min-w-0 self-start text-sm font-semibold leading-snug tabular-nums [overflow-wrap:anywhere]"
                                :class="metric.tone && `${metric.tone} rounded-md px-1`">{{ metric.value }}</dd>
                            <dd v-if="metric.detail" class="nfc-muted text-[10px] leading-tight [overflow-wrap:anywhere]">{{ metric.detail }}</dd>
                        </div>
                    </div>
                </dl>
                <p v-if="metricPages.length > 1" class="nfc-muted text-[11px] sm:hidden">Deslize os indicadores para os lados.</p>
                <dl aria-label="Indicadores das plaquinhas NFC"
                    class="hidden min-w-0 grid-cols-2 gap-2 sm:grid md:grid-cols-4 md:gap-3">
                    <div v-for="metric in metrics" :key="metric.label" class="nfc-panel flex min-w-0 flex-col gap-1 p-3">
                        <dt class="nfc-muted text-xs font-medium leading-tight">{{ metric.label }}</dt>
                        <dd class="min-w-0 self-start text-lg font-semibold leading-snug tabular-nums [overflow-wrap:anywhere]"
                            :class="metric.tone && `${metric.tone} rounded-md px-1.5`">{{ metric.value }}</dd>
                        <dd v-if="metric.detail" class="nfc-muted text-[11px] leading-snug">{{ metric.detail }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="!schema_pending" role="tablist" aria-label="Empresas e fabricação NFC"
                class="nfc-panel flex w-full min-w-0 gap-1 p-1 sm:w-fit">
                <button id="nfc-tab-companies" type="button" role="tab" aria-controls="nfc-panel-companies"
                    :aria-selected="activeRegistryTab === 'companies'" :aria-pressed="activeRegistryTab === 'companies'"
                    class="nfc-button min-w-0 flex-1 sm:min-w-44" @click="activeRegistryTab = 'companies'">
                    <Building2 :size="16" aria-hidden="true" /> Empresas
                    <span class="nfc-badge nfc-neutral tabular-nums">{{ companies.length }}</span>
                </button>
                <button id="nfc-tab-production" type="button" role="tab" aria-controls="nfc-panel-production"
                    :aria-selected="activeRegistryTab === 'production'" :aria-pressed="activeRegistryTab === 'production'"
                    class="nfc-button min-w-0 flex-1 sm:min-w-44" @click="activeRegistryTab = 'production'">
                    <Factory :size="16" aria-hidden="true" /> Fabricação
                    <span class="nfc-badge nfc-neutral tabular-nums">{{ production.length }}</span>
                </button>
            </div>

            <section v-if="!schema_pending" v-show="activeRegistryTab === 'companies'" id="nfc-panel-companies" role="tabpanel" aria-labelledby="nfc-tab-companies" class="flex min-w-0 flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="nfc-companies" class="flex items-center gap-2 text-lg font-semibold"><Building2 :size="19" aria-hidden="true" /> Empresas</h2>
                    <div class="flex flex-wrap gap-1.5" aria-label="Filtrar empresas" role="group">
                        <button v-for="option in filters" :key="option.key" type="button" class="nfc-button" :aria-pressed="filter === option.key" @click="filter = option.key">{{ option.label }}</button>
                    </div>
                </div>
                <label class="nfc-field max-w-xl">Buscar empresa, CPF/CNPJ, unidade, venda ou plaquinha
                    <input v-model.trim="search" type="search" class="nfc-control" placeholder="Digite para filtrar..." aria-label="Buscar no módulo NFC" />
                </label>
                <div v-if="unitRows.length" class="flex flex-col gap-3">
                    <ul class="nfc-scrollbar flex max-h-[min(58dvh,34rem)] flex-col gap-2 overflow-y-auto overscroll-contain pr-1 md:hidden" aria-label="Empresas e unidades NFC">
                        <li v-for="row in unitRows" :key="row.companyId + '-' + row.id" class="nfc-panel min-w-0">
                            <button type="button" class="nfc-row flex w-full min-w-0 flex-col items-start gap-2 rounded-xl p-4 text-left" @click="showCompany(row.companyId)">
                                <span class="flex w-full items-center justify-between gap-2 font-semibold">{{ row.companyName }} <ChevronRight :size="16" aria-hidden="true" /></span>
                                <span class="nfc-muted text-sm">{{ row.unitName }} · {{ row.identifier || 'Sem CPF/CNPJ' }}</span>
                                <span class="nfc-badge" :class="row.productionStatus === 'in_progress' ? 'nfc-warning' : row.productionStatus ? 'nfc-success' : 'nfc-neutral'">{{ row.productionStatus === 'in_progress' ? 'Em fabricação' : row.productionStatus ? 'Fabricado' : 'Sem lote' }}</span>
                                <span class="flex w-full flex-wrap gap-3 text-sm"><span>{{ row.sold }} vendidas</span><span>Valor: {{ money(row.total) }}</span><span>Recebido: {{ money(row.received) }}</span><span>A receber: {{ money(row.outstanding) }}</span></span>
                            </button>
                        </li>
                    </ul>
                    <div class="nfc-panel nfc-scrollbar hidden max-h-[min(58dvh,34rem)] min-w-0 overflow-auto overscroll-contain md:block">
                        <table class="w-full min-w-[780px] text-left text-sm">
                            <thead class="nfc-muted nfc-rule sticky top-0 z-10 border-b bg-[var(--fs-surface)] text-xs">
                                <tr><th class="px-4 py-3">Empresa / CPF-CNPJ</th><th class="px-3 py-3">Unidade</th><th class="px-3 py-3">Lote / Status</th><th class="px-3 py-3 text-right">Vendidas</th><th class="px-3 py-3 text-right">Valor</th><th class="px-3 py-3 text-right">Pago</th><th class="px-4 py-3 text-right">A receber</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in unitRows" :key="row.companyId + '-' + row.id" class="nfc-rule border-b last:border-b-0">
                                    <td class="px-4 py-3"><button type="button" class="font-semibold underline-offset-2 hover:underline" @click="showCompany(row.companyId)">{{ row.companyName }}</button><p class="nfc-muted text-xs">{{ row.identifier || '—' }}</p></td>
                                    <td class="px-3 py-3">{{ row.unitName }}</td>
                                    <td class="px-3 py-3"><span :class="row.productionStatus === 'in_progress' ? 'nfc-warning' : row.productionStatus ? 'nfc-success' : 'nfc-neutral'" class="nfc-badge">{{ row.productionStatus === 'in_progress' ? 'Em fabricação' : row.productionStatus ? 'Fabricado' : 'Sem lote' }}</span><span v-if="row.productionId" class="nfc-muted block text-xs">Lote #{{ row.productionId }}</span></td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ row.sold }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ money(row.total) }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ money(row.received) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ money(row.outstanding) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p v-else class="nfc-panel nfc-muted p-4 text-sm">{{ companies.length ? 'Nenhuma empresa neste filtro.' : 'Nenhuma empresa cadastrada.' }}</p>
            </section>

            <section v-if="!schema_pending" v-show="activeRegistryTab === 'production'" id="nfc-panel-production" role="tabpanel" aria-labelledby="nfc-tab-production" class="flex min-w-0 flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="nfc-production" class="flex items-center gap-2 text-lg font-semibold"><Factory :size="19" aria-hidden="true" /> Fabricação</h2>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="!hasInitialBatch" type="button" class="nfc-button" @click="edit('batch', { initial: true })">Registrar lote inicial</button>
                        <button type="button" class="nfc-button" @click="edit('batch')"><Plus :size="17" aria-hidden="true" /> Novo lote</button>
                    </div>
                </div>
                <label class="nfc-field max-w-xl">Buscar lote, data, status, empresa ou unidade
                    <input v-model.trim="productionSearch" type="search" class="nfc-control" placeholder="Digite para filtrar a fabricação..." aria-label="Buscar lotes de fabricação" />
                </label>
                <ul v-if="productionShown.length" class="nfc-panel nfc-scrollbar max-h-[min(58dvh,34rem)] min-w-0 overflow-y-auto overscroll-contain">
                    <li v-for="item in productionShown" :key="item.id" class="nfc-rule flex min-w-0 flex-wrap items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ item.initial ? 'Lote inicial' : item.produced_on ? date(item.produced_on) : 'Lote sem data' }} · {{ item.quantity }} plaquinhas</p>
                            <span class="nfc-badge" :class="item.status === 'in_progress' ? 'nfc-warning' : 'nfc-success'">{{ item.status === 'in_progress' ? 'Em fabricação' : 'Fabricado' }}</span>
                            <p class="nfc-muted text-sm">{{ item.customized_quantity === null ? 'Tipos a informar' : `${item.quantity - item.customized_quantity} normais · ${item.customized_quantity} personalizadas` }}</p>
                            <p v-if="item.linkedUnits.length" class="nfc-muted mt-1 break-words text-xs">Vinculado a: {{ item.linkedUnits.join('; ') }}</p>
                        </div>
                        <span class="nfc-muted text-sm tabular-nums">{{ item.cost_cents === null ? 'Custo a informar' : money(item.cost_cents) }}</span>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="nfc-button" :aria-label="`Editar lote #${item.id}`" @click="edit('batch', { batchId: item.id })">Editar</button>
                            <button type="button" class="nfc-button nfc-danger" @click="destroy('batch', { batchId: item.id })">Excluir</button>
                        </div>
                    </li>
                </ul>
                <p v-else class="nfc-panel nfc-muted p-4 text-sm">{{ production.length ? 'Nenhum lote encontrado nesta busca.' : 'Nenhuma fabricação registrada.' }}</p>
            </section>
        </div>

        <Modal class="nfc-dialog" :show="dialog !== null" labelled-by="nfc-dialog-heading" :closeable="!busy" @close="closeDialog">
            <section v-if="dialog" class="nfc-dialog-content flex min-w-0 flex-col gap-4 p-4 sm:gap-5 sm:p-6">
                <header class="flex min-w-0 items-start gap-2">
                    <button v-if="dialog.previous" type="button" class="nfc-button nfc-icon-button shrink-0" aria-label="Voltar" :disabled="busy" @click="goBack"><ArrowLeft :size="18" aria-hidden="true" /></button>
                    <h2 id="nfc-dialog-heading" ref="dialogHeading" tabindex="-1" class="min-w-0 flex-1 self-center text-xl font-semibold tracking-tight [overflow-wrap:anywhere]">{{ title }}</h2>
                    <button type="button" class="nfc-button nfc-icon-button shrink-0" aria-label="Fechar" :disabled="busy" @click="closeDialog"><X :size="18" aria-hidden="true" /></button>
                </header>

                <CompanyDetails v-if="dialog.kind === 'company' && company" :company="company" :highlight-sale-id="dialog.saleId" @edit="edit" @destroy="destroy" />
                <div v-else-if="dialog.kind === 'share' && !shared && share_links_enabled && authenticatedUser" class="flex min-w-0 flex-col gap-4">
                    <p class="nfc-muted text-sm">Links individuais válidos por sete dias. Quem possuir um link poderá consultar e alterar somente os dados do módulo NFC, incluindo exclusões. Revogue assim que não precisar mais.</p>
                    <button type="button" class="nfc-button nfc-primary self-start" :disabled="shareBusy" @click="createShare">Gerar novo link</button>
                    <div v-if="created_share_url" class="nfc-panel flex min-w-0 flex-col gap-2 p-3">
                        <label class="nfc-field">Link gerado (copie agora)
                            <input class="nfc-control" type="text" readonly :value="created_share_url" @focus="$event.target.select()" />
                        </label>
                        <button type="button" class="nfc-button self-start" @click="copyShare">{{ copied ? 'Copiado' : 'Copiar link' }}</button>
                        <p class="nfc-muted text-xs">Por segurança, o endereço completo não será mostrado novamente.</p>
                    </div>
                    <h3 class="font-semibold">Links emitidos</h3>
                    <ul class="flex flex-col gap-2">
                        <li v-for="link in share_links" :key="link.id" class="nfc-panel flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
                            <div><p class="font-semibold">Link #{{ link.id }}</p>
                                <p class="nfc-muted text-xs">Expira: {{ datetime(link.expires_at) }}</p>
                                <span v-if="link.revoked_at" class="nfc-badge nfc-neutral">Revogado</span>
                                <span v-else-if="new Date(link.expires_at) <= new Date()" class="nfc-badge nfc-warning">Expirado</span>
                                <span v-else class="nfc-badge nfc-success">Ativo</span>
                            </div>
                            <button v-if="!link.revoked_at" type="button" class="nfc-button nfc-danger" :disabled="shareBusy" @click="revokeShare(link)">Revogar</button>
                        </li>
                    </ul>
                    <p v-if="!share_links.length" class="nfc-muted text-sm">Nenhum link emitido.</p>
                </div>
                <div v-else-if="dialog.kind === 'history'" class="flex max-h-[65dvh] min-w-0 flex-col gap-3 overflow-y-auto">
                    <p class="nfc-muted text-sm">Últimos {{ history.length }} eventos. Criações, edições, resoluções e exclusões são registradas no módulo NFC.</p>
                    <ul class="flex flex-col gap-2">
                        <li v-for="entry in history" :key="entry.id" class="nfc-panel p-3 text-sm">
                            <p class="font-semibold">{{ historyVerb[entry.event] || entry.event }} · {{ historyKind[entry.target_kind] || entry.target_kind }} #{{ entry.target_id }}</p>
                            <p v-if="entry.label" class="min-w-0 break-words">{{ entry.label }}</p>
                            <p class="nfc-muted text-xs">{{ entry.actor }} · {{ datetime(entry.occurred_at) }}</p>
                            <details class="mt-2"><summary class="cursor-pointer text-xs underline">Ver dados registrados</summary>
                                <pre class="nfc-muted mt-2 max-h-52 overflow-auto whitespace-pre-wrap break-all text-xs">{{ JSON.stringify({ antes: entry.before, depois: entry.after }, null, 2) }}</pre>
                            </details>
                        </li>
                    </ul>
                    <p v-if="!history.length" class="nfc-muted text-sm">Nenhuma alteração registrada.</p>
                </div>
                <Notices v-else-if="dialog.kind === 'notices'" :notices="notices" :warranty-notices="warranty_notices" :nfc-route="nfcRoute" @create="edit('notice')" @open-company="showCompany" @busy="busy = $event" />
                <Editor
                    v-else
                    :key="dialog.kind"
                    :kind="dialog.kind"
                    :context="dialog"
                    :today="today"
                    :company="company"
                    :companies="companies"
                    :unit="unit"
                    :sale="sale"
                    :plate="plate"
                    :batch="batch"
                    :production="production"
                    :nfc-route="nfcRoute"
                    @cancel="goBack"
                    @saved="saved"
                    @busy="busy = $event"
                />
            </section>
        </Modal>
    </component>
</template>
