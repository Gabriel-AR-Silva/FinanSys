<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { normalizeMoneyInput } from '@/Support/money';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArchiveRestore, ArrowDownCircle, ArrowRightLeft, ArrowUpCircle, CalendarClock, ChevronLeft, ChevronRight, Filter, Plus, ReceiptText, RotateCcw, Trash2, X } from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    entries: { type: Object, required: true },
    deletedEntries: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    transferReferences: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const periods = [
    ['all', 'Todo o período'], ['7', '7 dias'], ['15', '15 dias'],
    ['30', '30 dias'], ['60', '60 dias'], ['365', '1 ano'],
];
const filters = reactive({
    type: props.filters.type ?? 'all',
    account_id: props.filters.account_id ? String(props.filters.account_id) : 'all',
    category_id: props.filters.category_id ? String(props.filters.category_id) : 'all',
    period: props.filters.period ?? 'all',
});
const createModalOpen = ref(false);
const transferModalOpen = ref(false);
const deletingEntry = ref(null);
const reversingEntry = ref(null);
const restoringEntryId = ref(null);
const currentDate = new Date();
const today = new Date(currentDate.getTime() - currentDate.getTimezoneOffset() * 60_000).toISOString().slice(0, 10);
const newOperationId = () => crypto.randomUUID();
const createForm = useForm({
    type: 'expense', account_id: props.accounts[0]?.id ?? '',
    category_id: '', description: '', amount: '', occurred_at: today, operation_id: newOperationId(),
});
const deleteForm = useForm({});
const reversalForm = useForm({ operation_id: newOperationId() });
const sourceKey = ref('');
const destinationKey = ref('');
const transferForm = useForm({ amount: '', operation_id: newOperationId() });
const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const date = new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' });
const hasAccounts = computed(() => props.accounts.length > 0);
const availableCategories = computed(() => props.categories.filter((category) => category.type === createForm.type && category.status === 'active'));
const canTransfer = computed(() => props.transferReferences.length > 1);
const selectedSource = computed(() => props.transferReferences.find((reference) => reference.key === sourceKey.value));
const availableDestinations = computed(() => props.transferReferences.filter((reference) => reference.key !== sourceKey.value));
const formatMoney = (value) => money.format(Number(value));
const formatDate = (value) => date.format(new Date(value));
const positiveTypes = ['income', 'opening_balance', 'transfer_in'];
const typeLabels = { income: 'Receita', expense: 'Despesa', opening_balance: 'Saldo inicial', transfer_in: 'Transferência recebida', transfer_out: 'Transferência enviada' };
const isIncome = (entry) => positiveTypes.includes(entry.type);
const accountName = (entry) => entry.reference?.name ?? 'Conta';
const entryTitle = (entry) => entry.description || entry.category?.name || 'Sem categoria';

function applyFilters() {
    const query = Object.fromEntries(Object.entries(filters).filter(([, value]) => value && value !== 'all'));
    router.get(route('ledger-entries.index'), query, { preserveState: true, preserveScroll: true, replace: true });
}

function openCreateModal(type = 'expense') {
    createForm.clearErrors();
    createForm.type = type;
    createForm.category_id = availableCategories.value[0]?.id ?? '';
    createModalOpen.value = true;
}

function closeCreateModal() {
    if (createForm.processing) return;
    createModalOpen.value = false;
    createForm.reset();
    createForm.type = 'expense';
    createForm.account_id = props.accounts[0]?.id ?? '';
    createForm.category_id = availableCategories.value[0]?.id ?? '';
    createForm.occurred_at = today;
    createForm.operation_id = newOperationId();
}

function submitCreate() {
    createForm
        .transform((data) => ({ ...data, amount: normalizeMoneyInput(data.amount) }))
        .post(route('ledger-entries.store'), { preserveScroll: true, onSuccess: closeCreateModal });
}

function openTransferModal() {
    transferForm.clearErrors();
    sourceKey.value = props.transferReferences[0]?.key ?? '';
    destinationKey.value = props.transferReferences.find((reference) => reference.key !== sourceKey.value)?.key ?? '';
    transferModalOpen.value = true;
}

function closeTransferModal() {
    if (transferForm.processing) return;
    transferModalOpen.value = false;
    transferForm.reset();
    transferForm.operation_id = newOperationId();
}

function submitTransfer() {
    const source = props.transferReferences.find((reference) => reference.key === sourceKey.value);
    const destination = props.transferReferences.find((reference) => reference.key === destinationKey.value);
    transferForm
        .transform((data) => ({
            source_type: source?.type, source_id: source?.id,
            destination_type: destination?.type, destination_id: destination?.id,
            amount: normalizeMoneyInput(data.amount), operation_id: data.operation_id,
        }))
        .post(route('transfers.store'), { preserveScroll: true, onSuccess: closeTransferModal });
}

function submitDelete() {
    deleteForm.delete(route('ledger-entries.destroy', deletingEntry.value.id), {
        preserveScroll: true, onSuccess: () => { deletingEntry.value = null; },
    });
}

function restore(entry) {
    restoringEntryId.value = entry.id;
    router.post(route('ledger-entries.restore', entry.id), {}, {
        preserveScroll: true, onFinish: () => { restoringEntryId.value = null; },
    });
}

function closeReversalModal() {
    if (reversalForm.processing) return;
    reversingEntry.value = null;
    reversalForm.clearErrors();
    reversalForm.operation_id = newOperationId();
}

function submitReversal() {
    reversalForm.post(route('ledger-entries.reversals.store', reversingEntry.value.id), {
        preserveScroll: true,
        onSuccess: closeReversalModal,
    });
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const requestedType = params.get('create');
    if (['income', 'expense'].includes(requestedType)) openCreateModal(requestedType);
    else if (requestedType === '1') openCreateModal();
    if (params.get('transfer') === '1' && canTransfer.value) openTransferModal();
});

watch(() => createForm.type, () => {
    if (! availableCategories.value.some((category) => category.id === createForm.category_id)) {
        createForm.category_id = availableCategories.value[0]?.id ?? '';
    }
});

watch(sourceKey, () => {
    if (destinationKey.value === sourceKey.value) {
        destinationKey.value = availableDestinations.value[0]?.key ?? '';
    }
});
</script>

<template>
    <Head title="Lançamentos" />
    <AuthenticatedLayout>
        <div class="flex flex-col gap-8">
            <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-medium text-emerald-700">Histórico financeiro</p>
                    <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Lançamentos</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Registre receitas e despesas. O tipo determina o efeito no saldo da conta.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="button" class="flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canTransfer" :title="canTransfer ? 'Transferir entre contas e caixinhas' : 'Crie pelo menos duas origens financeiras'" @click="openTransferModal">
                        <ArrowRightLeft :size="18" />Transferir
                    </button>
                    <button type="button" class="flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!hasAccounts" :title="hasAccounts ? 'Registrar lançamento' : 'Crie uma conta primeiro'" @click="openCreateModal()">
                        <Plus :size="18" />Registrar lançamento
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filtros de lançamentos">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[1fr_1.2fr_1.2fr_1fr_auto] xl:items-end">
                    <div>
                        <InputLabel for="filter-category" value="Categoria" />
                        <select id="filter-category" v-model="filters.category_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="all">Todas as categorias</option><option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}{{ category.status === 'inactive' ? ' (inativa)' : '' }}</option></select>
                    </div>
                    <div>
                        <InputLabel for="filter-type" value="Tipo" />
                        <select id="filter-type" v-model="filters.type" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="all">Todos</option><option value="income">Receitas</option><option value="expense">Despesas</option></select>
                    </div>
                    <div>
                        <InputLabel for="filter-account" value="Conta" />
                        <select id="filter-account" v-model="filters.account_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="all">Todas as contas</option><option v-for="account in accounts" :key="account.id" :value="String(account.id)">{{ account.name }}</option></select>
                    </div>
                    <div>
                        <InputLabel for="filter-period" value="Período" />
                        <select id="filter-period" v-model="filters.period" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option v-for="period in periods" :key="period[0]" :value="period[0]">{{ period[1] }}</option></select>
                    </div>
                    <button type="button" class="flex items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="applyFilters"><Filter :size="17" />Filtrar</button>
                </div>
            </section>

            <section v-if="entries.data.length" aria-label="Lista de lançamentos">
                <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-4">Lançamento</th><th class="px-5 py-4">Conta</th><th class="px-5 py-4">Data</th><th class="px-5 py-4 text-right">Valor</th><th class="w-16 px-5 py-4"><span class="sr-only">Ações</span></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-slate-50/70">
                                <td class="px-5 py-4"><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl" :class="isIncome(entry) ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><ArrowUpCircle v-if="isIncome(entry)" :size="20" /><ArrowDownCircle v-else :size="20" /></span><div><p class="font-semibold text-slate-900">{{ entryTitle(entry) }}</p><p class="mt-0.5 text-xs text-slate-500">{{ entry.category?.name ?? 'Sem categoria' }} · {{ typeLabels[entry.type] ?? 'Lançamento' }}<span v-if="entry.is_reversal" class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700">Estorno</span></p></div></div></td>
                                <td class="px-5 py-4 text-slate-600">{{ accountName(entry) }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ formatDate(entry.occurred_at) }}</td>
                                <td class="px-5 py-4 text-right font-semibold" :class="isIncome(entry) ? 'text-emerald-700' : 'text-rose-700'">{{ isIncome(entry) ? '+' : '−' }} {{ formatMoney(entry.amount) }}</td>
                                <td class="px-5 py-4 text-right"><div class="flex justify-end gap-1"><button v-if="entry.can_reverse" type="button" class="rounded-lg p-2 text-slate-400 hover:bg-amber-50 hover:text-amber-700" :aria-label="`Estornar ${entryTitle(entry)}`" @click="reversingEntry = entry"><RotateCcw :size="17" /></button><button v-if="entry.can_delete" type="button" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-700" :aria-label="`Excluir ${entryTitle(entry)}`" @click="deletingEntry = entry"><Trash2 :size="17" /></button></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 md:hidden">
                    <article v-for="entry in entries.data" :key="entry.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="isIncome(entry) ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><ArrowUpCircle v-if="isIncome(entry)" :size="20" /><ArrowDownCircle v-else :size="20" /></span><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-3"><p class="truncate font-semibold text-slate-900">{{ entryTitle(entry) }}</p><p class="shrink-0 font-semibold" :class="isIncome(entry) ? 'text-emerald-700' : 'text-rose-700'">{{ isIncome(entry) ? '+' : '−' }} {{ formatMoney(entry.amount) }}</p></div><p class="mt-1 text-xs text-slate-500">{{ entry.category?.name ?? 'Sem categoria' }} · {{ accountName(entry) }} · {{ formatDate(entry.occurred_at) }}<span v-if="entry.is_reversal" class="ml-1 font-semibold text-amber-700">· Estorno</span></p></div><div class="flex shrink-0 gap-1"><button v-if="entry.can_reverse" type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-amber-50 hover:text-amber-700" :aria-label="`Estornar ${entryTitle(entry)}`" @click="reversingEntry = entry"><RotateCcw :size="17" /></button><button v-if="entry.can_delete" type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-700" :aria-label="`Excluir ${entryTitle(entry)}`" @click="deletingEntry = entry"><Trash2 :size="17" /></button></div></div>
                    </article>
                </div>

                <nav v-if="entries.last_page > 1" class="mt-5 flex items-center justify-between gap-4" aria-label="Paginação"><p class="text-sm text-slate-500">Página {{ entries.current_page }} de {{ entries.last_page }}</p><div class="flex gap-2"><Link :href="entries.prev_page_url ?? '#'" class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700" :class="{ 'pointer-events-none opacity-40': !entries.prev_page_url }" preserve-scroll><ChevronLeft :size="16" />Anterior</Link><Link :href="entries.next_page_url ?? '#'" class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700" :class="{ 'pointer-events-none opacity-40': !entries.next_page_url }" preserve-scroll>Próxima<ChevronRight :size="16" /></Link></div></nav>
            </section>

            <section v-else class="flex min-h-80 flex-col items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm"><span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"><ReceiptText :size="29" /></span><h2 class="mt-5 text-xl font-semibold text-slate-950">Nenhum lançamento encontrado</h2><p class="mt-2 max-w-md text-sm leading-6 text-slate-500">Ajuste os filtros ou registre sua primeira receita ou despesa.</p><button v-if="hasAccounts" type="button" class="mt-6 flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800" @click="openCreateModal()"><Plus :size="18" />Registrar lançamento</button><Link v-else :href="route('accounts.index', { create: 1 })" class="mt-6 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Criar primeira conta</Link></section>

            <section v-if="deletedEntries.length"><div class="flex items-center gap-2"><ArchiveRestore :size="19" class="text-slate-500" /><h2 class="font-semibold text-slate-900">Excluídos recentemente</h2></div><div class="mt-4 grid gap-3"><article v-for="entry in deletedEntries" :key="entry.id" class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center"><div><p class="text-sm font-semibold text-slate-800">{{ entry.description }}</p><p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500"><span>{{ accountName(entry) }} · {{ formatMoney(entry.amount) }}</span><span>·</span><CalendarClock :size="14" />Disponível até {{ formatDate(entry.restorable_until) }}</p></div><button type="button" class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50" :disabled="restoringEntryId === entry.id" @click="restore(entry)"><ArchiveRestore :size="16" />{{ restoringEntryId === entry.id ? 'Restaurando...' : 'Restaurar' }}</button></article></div></section>
        </div>

        <Modal :show="createModalOpen" max-width="lg" @close="closeCreateModal">
            <form class="p-6 sm:p-7" @submit.prevent="submitCreate">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Novo lançamento</p><h2 class="mt-1 text-xl font-semibold text-slate-950">Registrar receita ou despesa</h2></div><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Fechar" @click="closeCreateModal"><X :size="20" /></button></div>
                <div class="mt-6 grid grid-cols-2 gap-2"><button type="button" class="flex items-center justify-center gap-2 rounded-xl border px-3 py-3 text-sm font-semibold" :class="createForm.type === 'income' ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-200 text-slate-600'" @click="createForm.type = 'income'"><ArrowUpCircle :size="18" />Receita</button><button type="button" class="flex items-center justify-center gap-2 rounded-xl border px-3 py-3 text-sm font-semibold" :class="createForm.type === 'expense' ? 'border-rose-600 bg-rose-50 text-rose-800' : 'border-slate-200 text-slate-600'" @click="createForm.type = 'expense'"><ArrowDownCircle :size="18" />Despesa</button></div>
                <InputError class="mt-2" :message="createForm.errors.type" />
                <div class="mt-5 grid gap-5">
                    <div><InputLabel for="entry-account" value="Conta" /><select id="entry-account" v-model="createForm.account_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option disabled value="">Selecione uma conta</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option></select><InputError class="mt-2" :message="createForm.errors.account_id" /></div>
                    <div><InputLabel for="entry-category" value="Categoria" /><select id="entry-category" v-model="createForm.category_id" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option disabled value="">Selecione uma categoria</option><option v-for="category in availableCategories" :key="category.id" :value="category.id">{{ category.name }}</option></select><InputError class="mt-2" :message="createForm.errors.category_id" /></div>
                    <div><InputLabel for="entry-description" value="Detalhes (opcional)" /><TextInput id="entry-description" v-model="createForm.description" class="mt-2 block w-full" maxlength="255" autocomplete="off" placeholder="Ex.: parcela do financiamento" /><InputError class="mt-2" :message="createForm.errors.description" /></div>
                    <div class="grid gap-5 sm:grid-cols-2"><div><InputLabel for="entry-amount" value="Valor" /><div class="relative mt-2"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-500">R$</span><TextInput id="entry-amount" v-model="createForm.amount" class="block w-full pl-10" inputmode="decimal" placeholder="0,00" /></div><InputError class="mt-2" :message="createForm.errors.amount" /></div><div><InputLabel for="entry-date" value="Data" /><TextInput id="entry-date" v-model="createForm.occurred_at" type="date" :max="today" class="mt-2 block w-full" /><InputError class="mt-2" :message="createForm.errors.occurred_at" /></div></div>
                </div>
                <div class="mt-7 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100" @click="closeCreateModal">Cancelar</button><button type="submit" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :class="createForm.type === 'income' ? 'bg-emerald-700 hover:bg-emerald-800' : 'bg-rose-600 hover:bg-rose-700'" :disabled="createForm.processing">{{ createForm.processing ? 'Registrando...' : `Registrar ${createForm.type === 'income' ? 'receita' : 'despesa'}` }}</button></div>
            </form>
        </Modal>

        <Modal :show="transferModalOpen" max-width="lg" @close="closeTransferModal">
            <form class="p-6 sm:p-7" @submit.prevent="submitTransfer">
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Movimentação interna</p><h2 class="mt-1 text-xl font-semibold text-slate-950">Transferir saldo</h2><p class="mt-2 text-sm leading-6 text-slate-500">O valor sai da origem e entra no destino sem alterar o saldo geral.</p></div><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Fechar" @click="closeTransferModal"><X :size="20" /></button></div>
                <div class="mt-6 grid gap-5">
                    <div><InputLabel for="transfer-source" value="Origem" /><select id="transfer-source" v-model="sourceKey" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option v-for="reference in transferReferences" :key="reference.key" :value="reference.key">{{ reference.name }} — {{ reference.context }} · {{ formatMoney(reference.balance) }}</option></select><InputError class="mt-2" :message="transferForm.errors.source_type || transferForm.errors.source_id" /></div>
                    <div><InputLabel for="transfer-destination" value="Destino" /><select id="transfer-destination" v-model="destinationKey" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option v-for="reference in availableDestinations" :key="reference.key" :value="reference.key">{{ reference.name }} — {{ reference.context }} · {{ formatMoney(reference.balance) }}</option></select><InputError class="mt-2" :message="transferForm.errors.destination_type || transferForm.errors.destination_id" /></div>
                    <div><InputLabel for="transfer-amount" value="Valor" /><div class="relative mt-2"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-500">R$</span><TextInput id="transfer-amount" v-model="transferForm.amount" class="block w-full pl-10" inputmode="decimal" placeholder="0,00" autofocus /></div><p v-if="selectedSource" class="mt-2 text-xs text-slate-500">Disponível: {{ formatMoney(selectedSource.balance) }}</p><InputError class="mt-2" :message="transferForm.errors.amount" /><InputError class="mt-2" :message="transferForm.errors.operation_id" /></div>
                </div>
                <div class="mt-7 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100" @click="closeTransferModal">Cancelar</button><button type="submit" class="flex items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50" :disabled="transferForm.processing"><ArrowRightLeft :size="17" />{{ transferForm.processing ? 'Transferindo...' : 'Confirmar transferência' }}</button></div>
            </form>
        </Modal>

        <Modal :show="deletingEntry !== null" max-width="md" @close="deletingEntry = null"><div class="p-6"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-700"><Trash2 :size="22" /></span><h2 class="mt-5 text-xl font-semibold text-slate-950">Excluir lançamento?</h2><p class="mt-2 text-sm leading-6 text-slate-500">“{{ deletingEntry?.description }}” deixará de compor o saldo. Você poderá restaurá-lo por 30 dias.</p><div class="mt-7 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100" @click="deletingEntry = null">Cancelar</button><button type="button" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="deleteForm.processing" @click="submitDelete">{{ deleteForm.processing ? 'Excluindo...' : 'Excluir lançamento' }}</button></div></div></Modal>
        <Modal :show="reversingEntry !== null" max-width="md" @close="closeReversalModal"><form class="p-6" @submit.prevent="submitReversal"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-700"><RotateCcw :size="22" /></span><h2 class="mt-5 text-xl font-semibold text-slate-950">Estornar lançamento?</h2><p class="mt-2 text-sm leading-6 text-slate-500">Será criado um lançamento compensatório de {{ formatMoney(reversingEntry?.amount ?? 0) }}. O histórico original permanecerá auditável.</p><p v-if="reversingEntry?.type === 'transfer_out'" class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">O estorno só será concluído se o destino atual tiver saldo suficiente.</p><InputError class="mt-3" :message="reversalForm.errors.ledger_entry || reversalForm.errors.amount || reversalForm.errors.operation_id" /><div class="mt-7 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100" @click="closeReversalModal">Cancelar</button><button type="submit" class="flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50" :disabled="reversalForm.processing"><RotateCcw :size="17" />{{ reversalForm.processing ? 'Estornando...' : 'Confirmar estorno' }}</button></div></form></Modal>
    </AuthenticatedLayout>
</template>
