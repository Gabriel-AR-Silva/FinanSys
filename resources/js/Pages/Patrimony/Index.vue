<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatMoneyInput, normalizeMoneyInput, sanitizeMoneyInput } from '@/Support/money';
import { Head, useForm } from '@inertiajs/vue3';
import { CalendarDays, CircleDollarSign, Landmark, Pencil, Plus, Trash2, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    patrimony: { type: Object, required: true },
    today: { type: String, required: true },
});

const modalOpen = ref(false);
const editingAsset = ref(null);
const deletingAsset = ref(null);
const deleteForm = useForm({});
const form = useForm({
    name: '',
    category: '',
    estimated_value: '',
    debt_balance: '0,00',
    valued_on: props.today,
});

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = value => money.format(Number(value ?? 0));
const summary = computed(() => props.patrimony.summary);
const equityTone = value => Number(value) >= 0 ? 'text-emerald-700' : 'text-rose-700';

const resetForm = () => {
    form.reset();
    form.debt_balance = '0,00';
    form.valued_on = props.today;
    form.clearErrors();
    editingAsset.value = null;
};

const openCreate = () => {
    resetForm();
    modalOpen.value = true;
};

const openEdit = asset => {
    editingAsset.value = asset;
    form.name = asset.name;
    form.category = asset.category ?? '';
    form.estimated_value = formatMoneyInput(String(asset.estimated_value).replace('.', ','));
    form.debt_balance = formatMoneyInput(String(asset.debt_balance).replace('.', ','));
    form.valued_on = asset.valued_on;
    form.clearErrors();
    modalOpen.value = true;
};

const closeModal = () => {
    if (form.processing) return;
    modalOpen.value = false;
    resetForm();
};

const submit = () => {
    const payload = data => ({
        ...data,
        estimated_value: normalizeMoneyInput(data.estimated_value),
        debt_balance: normalizeMoneyInput(data.debt_balance || '0'),
    });
    const options = { preserveScroll: true, onSuccess: closeModal };

    if (editingAsset.value) {
        form.transform(payload).put(route('patrimony.update', editingAsset.value.id), options);
        return;
    }

    form.transform(payload).post(route('patrimony.store'), options);
};

const remove = () => {
    deleteForm.delete(route('patrimony.destroy', deletingAsset.value.id), {
        preserveScroll: true,
        onSuccess: () => { deletingAsset.value = null; },
    });
};

onMounted(() => {
    if (new URLSearchParams(window.location.search).has('create')) openCreate();
});
</script>

<template>
    <Head title="Patrimônio" />
    <AuthenticatedLayout>
        <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-emerald-700">Bens e patrimônio</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Patrimônio estimado</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Registre bens pelo valor aproximado de hoje e, quando houver, o saldo devedor vinculado. Isso não altera seu saldo nem cria movimentações.</p>
            </div>
            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800" @click="openCreate"><Plus :size="18" />Adicionar bem</button>
        </section>

        <section class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Resumo patrimonial">
            <article class="rounded-2xl bg-slate-950 p-4 text-white sm:col-span-2 xl:col-span-1"><p class="text-xs text-slate-400">Patrimônio estimado total</p><p class="mt-2 text-2xl font-semibold">{{ formatMoney(summary.estimated_net_worth) }}</p><p class="mt-2 text-[11px] text-slate-400">Financeiro + patrimônio líquido dos bens</p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Liquidez financeira</p><p class="mt-2 text-xl font-semibold text-slate-950">{{ formatMoney(summary.financial_balance) }}</p><p class="mt-1 text-[11px] text-slate-400">Contas + caixinhas</p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Bens estimados</p><p class="mt-2 text-xl font-semibold text-slate-950">{{ formatMoney(summary.assets_total) }}</p><p class="mt-1 text-[11px] text-slate-400">{{ summary.asset_count }} bem(ns)</p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Dívidas patrimoniais</p><p class="mt-2 text-xl font-semibold text-rose-700">{{ formatMoney(summary.debts_total) }}</p><p class="mt-1 text-[11px] text-slate-400">Saldo devedor informado</p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-xs text-slate-500">Patrimônio líquido dos bens</p><p class="mt-2 text-xl font-semibold" :class="equityTone(summary.asset_equity)">{{ formatMoney(summary.asset_equity) }}</p><p class="mt-1 text-[11px] text-slate-400">Valor dos bens menos dívidas</p></article>
        </section>

        <section v-if="patrimony.assets.length" class="mt-7 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <article v-for="asset in patrimony.assets" :key="asset.id" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"><Landmark :size="21" /></span>
                    <div class="flex gap-1">
                        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" :aria-label="`Editar ${asset.name}`" @click="openEdit(asset)"><Pencil :size="16" /></button>
                        <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-700" :aria-label="`Excluir ${asset.name}`" @click="deletingAsset = asset"><Trash2 :size="16" /></button>
                    </div>
                </div>
                <p class="mt-5 truncate text-sm font-semibold text-slate-950">{{ asset.name }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ asset.category || 'Sem categoria' }}</p>
                <dl class="mt-5 grid grid-cols-3 gap-2 text-xs">
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Estimado</dt><dd class="mt-1 font-semibold text-slate-900">{{ formatMoney(asset.estimated_value) }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Dívida</dt><dd class="mt-1 font-semibold text-rose-700">{{ formatMoney(asset.debt_balance) }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Líquido</dt><dd class="mt-1 font-semibold" :class="equityTone(asset.equity)">{{ formatMoney(asset.equity) }}</dd></div>
                </dl>
                <p class="mt-4 flex items-center gap-1.5 text-xs text-slate-400"><CalendarDays :size="14" />Avaliado em {{ new Intl.DateTimeFormat('pt-BR', { timeZone: 'UTC' }).format(new Date(`${asset.valued_on}T12:00:00Z`)) }}</p>
            </article>
        </section>

        <div v-else class="mt-8 flex min-h-[25rem] flex-col items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"><CircleDollarSign :size="29" /></span>
            <h2 class="mt-5 text-xl font-semibold text-slate-950">Nenhum bem cadastrado</h2>
            <p class="mt-2 max-w-lg text-sm leading-6 text-slate-500">Exemplo: uma moto avaliada hoje em R$ 18 mil com R$ 11 mil ainda de saldo devedor representa R$ 7 mil de patrimônio líquido estimado.</p>
            <button type="button" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white" @click="openCreate"><Plus :size="18" />Adicionar primeiro bem</button>
        </div>

        <Modal :show="modalOpen" max-width="lg" @close="closeModal">
            <form class="p-6 sm:p-7" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Patrimônio</p><h2 class="mt-1 text-xl font-semibold text-slate-950">{{ editingAsset ? 'Atualizar bem' : 'Adicionar bem' }}</h2></div>
                    <button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Fechar" @click="closeModal"><X :size="20" /></button>
                </div>
                <div class="mt-6 grid gap-4">
                    <div><label for="asset-name" class="text-sm font-medium text-slate-700">Nome do bem</label><input id="asset-name" v-model="form.name" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Ex.: Moto Honda" /><InputError class="mt-1" :message="form.errors.name" /></div>
                    <div><label for="asset-category" class="text-sm font-medium text-slate-700">Categoria</label><input id="asset-category" v-model="form.category" list="asset-categories" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Ex.: Veículo" /><datalist id="asset-categories"><option value="Veículo" /><option value="Imóvel" /><option value="Eletrônico" /><option value="Equipamento" /><option value="Outro" /></datalist><InputError class="mt-1" :message="form.errors.category" /></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label for="asset-value" class="text-sm font-medium text-slate-700">Valor estimado atual</label><input id="asset-value" v-model="form.estimated_value" inputmode="decimal" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="18.000,00" @input="form.estimated_value = sanitizeMoneyInput($event.target.value)" @blur="form.estimated_value = formatMoneyInput(form.estimated_value)" /><InputError class="mt-1" :message="form.errors.estimated_value" /></div>
                        <div><label for="asset-debt" class="text-sm font-medium text-slate-700">Saldo devedor vinculado</label><input id="asset-debt" v-model="form.debt_balance" inputmode="decimal" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="0,00" @input="form.debt_balance = sanitizeMoneyInput($event.target.value)" @blur="form.debt_balance = formatMoneyInput(form.debt_balance)" /><InputError class="mt-1" :message="form.errors.debt_balance" /></div>
                    </div>
                    <div><label for="asset-date" class="text-sm font-medium text-slate-700">Data da avaliação</label><input id="asset-date" v-model="form.valued_on" type="date" class="mt-2 block w-full rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /><InputError class="mt-1" :message="form.errors.valued_on" /></div>
                    <p class="rounded-xl bg-emerald-50 p-3 text-xs leading-5 text-emerald-900">Use o valor aproximado do bem hoje. Parcelas já pagas não definem o valor patrimonial; juros também não viram patrimônio. O FinanSys calcula: valor estimado − saldo devedor.</p>
                </div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600" @click="closeModal">Cancelar</button><button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="form.processing">{{ form.processing ? 'Salvando…' : 'Salvar bem' }}</button></div>
            </form>
        </Modal>

        <Modal :show="deletingAsset !== null" max-width="md" @close="deletingAsset = null">
            <div class="p-6"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-700"><Trash2 :size="22" /></span><h2 class="mt-5 text-xl font-semibold text-slate-950">Remover {{ deletingAsset?.name }}?</h2><p class="mt-2 text-sm leading-6 text-slate-500">Isso remove apenas a estimativa patrimonial. Nenhuma movimentação financeira será criada ou apagada.</p><div class="mt-7 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600" @click="deletingAsset = null">Cancelar</button><button type="button" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white" @click="remove">Remover bem</button></div></div>
        </Modal>
    </AuthenticatedLayout>
</template>
