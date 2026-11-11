<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Building2, ChevronRight, Factory, Plus, X } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import CompanyDetails from './CompanyDetails.vue';
import Editor from './Editor.vue';
import Notices from './Notices.vue';
import { date, money } from './format';
import './nfc.css';

const props = defineProps({
    schema_pending: { type: Boolean, default: false },
    today: { type: String, default: '' },
    summary: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
    production: { type: Array, default: () => [] },
    notices: { type: Array, default: () => [] },
    warranty_notices: { type: Array, default: () => [] },
});

const filters = [
    { key: 'all', label: 'Todas' },
    { key: 'interested', label: 'Interessadas' },
    { key: 'sold', label: 'Com vendas' },
];
const filter = ref('all');
const search = ref('');
const dialog = ref(null);
const dialogHeading = ref(null);
const busy = ref(false);

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

const company = computed(() => props.companies.find(item => item.id === dialog.value?.companyId));
const sale = computed(() => company.value?.sales.find(item => item.id === dialog.value?.saleId));
const unit = computed(() => company.value?.units.find(item => item.id === dialog.value?.unitId));
const plate = computed(() => sale.value?.plates.find(item => item.id === dialog.value?.plateId));
const batch = computed(() => props.production.find(item => item.id === dialog.value?.batchId));

const titles = {
    notices: 'Avisos',
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

watch(dialog, async (current, previous) => {
    if (!current || !previous) return;
    await nextTick();
    dialogHeading.value?.focus();
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Plaquinhas NFC" />

        <div class="flex min-w-0 flex-col gap-6 sm:gap-8">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Plaquinhas NFC</h1>
                <div v-if="!schema_pending" class="flex flex-wrap items-center gap-2">
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

            <dl v-else aria-label="Indicadores das plaquinhas NFC" class="flex snap-x snap-mandatory gap-2.5 overflow-x-auto overscroll-x-contain pb-3 sm:grid sm:grid-cols-2 sm:overflow-visible sm:pb-0 md:grid-cols-4 md:gap-3">
                <div v-for="metric in metrics" :key="metric.label" class="nfc-panel flex w-[min(78vw,19rem)] shrink-0 snap-start flex-col gap-1.5 p-3 sm:w-auto sm:shrink sm:p-4">
                    <dt class="nfc-muted text-xs font-medium sm:text-sm">{{ metric.label }}</dt>
                    <dd class="self-start text-lg font-semibold tabular-nums [overflow-wrap:anywhere] sm:text-2xl" :class="metric.tone && `${metric.tone} rounded-lg px-1.5`">{{ metric.value }}</dd>
                    <dd v-if="metric.detail" class="nfc-muted text-xs leading-relaxed">{{ metric.detail }}</dd>
                </div>
            </dl>

            <section v-if="!schema_pending" aria-labelledby="nfc-companies" class="flex min-w-0 flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="nfc-companies" class="flex items-center gap-2 text-lg font-semibold"><Building2 :size="19" aria-hidden="true" /> Empresas</h2>
                    <div class="flex flex-wrap gap-1.5" aria-label="Filtrar empresas" role="group">
                        <button v-for="option in filters" :key="option.key" type="button" class="nfc-button" :aria-pressed="filter === option.key" @click="filter = option.key">{{ option.label }}</button>
                    </div>
                </div>
                <label class="nfc-field max-w-xl">Buscar empresa, CPF/CNPJ, unidade, venda ou plaquinha
                    <input v-model.trim="search" type="search" class="nfc-control" placeholder="Digite para filtrar..." aria-label="Buscar no módulo NFC" />
                </label>
                <div v-if="companiesShown.length" class="nfc-panel min-w-0">
                    <div aria-hidden="true" class="nfc-muted hidden grid-cols-[minmax(0,1.6fr)_minmax(0,0.75fr)_minmax(0,0.75fr)_minmax(0,1fr)_1rem] gap-3 border-b px-4 py-3 text-xs font-semibold nfc-rule md:grid">
                        <span>Empresa</span><span>Unidades</span><span>Vendidas</span><span>A receber</span>
                    </div>
                    <ul>
                        <li v-for="item in companiesShown" :key="item.id" class="nfc-rule border-b last:border-b-0">
                            <button type="button" class="nfc-row grid w-full min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2 rounded-xl px-4 py-3 text-left text-sm md:grid-cols-[minmax(0,1.6fr)_minmax(0,0.75fr)_minmax(0,0.75fr)_minmax(0,1fr)_1rem]" @click="showCompany(item.id)">
                                <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 font-semibold [overflow-wrap:anywhere]">
                                    {{ item.name }}
                                    <span v-if="item.interested" class="nfc-badge nfc-info">Interessada</span>
                                </span>
                                <span class="nfc-muted col-start-1 row-start-2 md:col-auto md:row-auto"><span class="md:sr-only">Unidades </span>{{ item.units.length }}</span>
                                <span class="nfc-muted col-start-2 row-start-2 md:col-auto md:row-auto"><span class="md:sr-only">Vendidas </span>{{ item.sold }}</span>
                                <span class="col-start-1 row-start-3 font-medium tabular-nums [overflow-wrap:anywhere] md:col-auto md:row-auto"><span class="md:sr-only">A receber </span>{{ money(item.outstanding_cents) }}</span>
                                <ChevronRight :size="16" class="nfc-muted col-start-2 row-start-1 justify-self-end md:col-auto md:row-auto" aria-hidden="true" />
                            </button>
                        </li>
                    </ul>
                </div>
                <p v-else class="nfc-panel nfc-muted p-4 text-sm">{{ companies.length ? 'Nenhuma empresa neste filtro.' : 'Nenhuma empresa cadastrada.' }}</p>
            </section>

            <section v-if="!schema_pending" aria-labelledby="nfc-production" class="flex min-w-0 flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="nfc-production" class="flex items-center gap-2 text-lg font-semibold"><Factory :size="19" aria-hidden="true" /> Fabricação</h2>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="!hasInitialBatch" type="button" class="nfc-button" @click="edit('batch', { initial: true })">Registrar lote inicial</button>
                        <button type="button" class="nfc-button" @click="edit('batch')"><Plus :size="17" aria-hidden="true" /> Novo lote</button>
                    </div>
                </div>
                <ul v-if="production.length" class="nfc-panel min-w-0">
                    <li v-for="item in production" :key="item.id" class="nfc-rule flex min-w-0 flex-wrap items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">{{ item.initial ? 'Lote inicial' : item.produced_on ? date(item.produced_on) : 'Lote sem data' }} · {{ item.quantity }} plaquinhas</p>
                            <span class="nfc-badge" :class="item.status === 'in_progress' ? 'nfc-warning' : 'nfc-success'">{{ item.status === 'in_progress' ? 'Em fabricação' : 'Fabricado' }}</span>
                            <p class="nfc-muted text-sm">{{ item.customized_quantity === null ? 'Tipos a informar' : `${item.quantity - item.customized_quantity} normais · ${item.customized_quantity} personalizadas` }}</p>
                        </div>
                        <span class="nfc-muted text-sm tabular-nums">{{ item.cost_cents === null ? 'Custo a informar' : money(item.cost_cents) }}</span>
                        <button type="button" class="nfc-button" :aria-label="`Editar ${item.initial ? 'lote inicial' : `lote de ${date(item.produced_on)}`}`" @click="edit('batch', { batchId: item.id })">Editar</button>
                    </li>
                </ul>
                <p v-else class="nfc-panel nfc-muted p-4 text-sm">Nenhuma fabricação registrada.</p>
            </section>
        </div>

        <Modal class="nfc-dialog" :show="dialog !== null" labelled-by="nfc-dialog-heading" :closeable="!busy" @close="closeDialog">
            <section v-if="dialog" class="nfc-dialog-content flex min-w-0 flex-col gap-4 p-4 sm:gap-5 sm:p-6">
                <header class="flex min-w-0 items-start gap-2">
                    <button v-if="dialog.previous" type="button" class="nfc-button nfc-icon-button shrink-0" aria-label="Voltar" :disabled="busy" @click="goBack"><ArrowLeft :size="18" aria-hidden="true" /></button>
                    <h2 id="nfc-dialog-heading" ref="dialogHeading" tabindex="-1" class="min-w-0 flex-1 self-center text-xl font-semibold tracking-tight [overflow-wrap:anywhere]">{{ title }}</h2>
                    <button type="button" class="nfc-button nfc-icon-button shrink-0" aria-label="Fechar" :disabled="busy" @click="closeDialog"><X :size="18" aria-hidden="true" /></button>
                </header>

                <CompanyDetails v-if="dialog.kind === 'company' && company" :company="company" :highlight-sale-id="dialog.saleId" @edit="edit" />
                <Notices v-else-if="dialog.kind === 'notices'" :notices="notices" :warranty-notices="warranty_notices" @create="edit('notice')" @open-company="showCompany" @busy="busy = $event" />
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
                    @cancel="goBack"
                    @saved="saved"
                    @busy="busy = $event"
                />
            </section>
        </Modal>
    </AuthenticatedLayout>
</template>
