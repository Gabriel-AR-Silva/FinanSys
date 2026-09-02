<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArchiveRestore, CalendarClock, Landmark, Pencil, Plus, Trash2, WalletCards, X } from '@lucide/vue';
import { onMounted, ref } from 'vue';

const props = defineProps({
    accounts: { type: Array, required: true },
    deletedAccounts: { type: Array, required: true },
});

const createModalOpen = ref(false);
const editingAccount = ref(null);
const deletingAccount = ref(null);
const restoringAccountId = ref(null);

const newOperationId = () => crypto.randomUUID();
const createForm = useForm({ name: '', opening_balance: '0,00', operation_id: newOperationId() });
const editForm = useForm({ name: '' });
const deleteForm = useForm({});

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => money.format(Number(value));
const formatDate = (value) => new Intl.DateTimeFormat('pt-BR').format(new Date(value));
const normalizeMoney = (value) => String(value).replace(/\./g, '').replace(',', '.');

const openCreateModal = () => {
    createForm.clearErrors();
    createModalOpen.value = true;
};

const closeCreateModal = () => {
    if (createForm.processing) return;
    createModalOpen.value = false;
    createForm.reset();
    createForm.operation_id = newOperationId();
};

const submitCreate = () => {
    createForm.transform((data) => ({ ...data, opening_balance: normalizeMoney(data.opening_balance) })).post(route('accounts.store'), {
        preserveScroll: true,
        onSuccess: closeCreateModal,
    });
};

const openEditModal = (account) => {
    editingAccount.value = account;
    editForm.name = account.name;
    editForm.clearErrors();
};

const closeEditModal = () => {
    if (editForm.processing) return;
    editingAccount.value = null;
    editForm.reset();
};

const submitEdit = () => {
    editForm.patch(route('accounts.update', editingAccount.value.id), { preserveScroll: true, onSuccess: closeEditModal });
};

const submitDelete = () => {
    deleteForm.delete(route('accounts.destroy', deletingAccount.value.id), {
        preserveScroll: true,
        onSuccess: () => { deletingAccount.value = null; },
    });
};

const restore = (account) => {
    restoringAccountId.value = account.id;
    router.post(route('accounts.restore', account.id), {}, {
        preserveScroll: true,
        onFinish: () => { restoringAccountId.value = null; },
    });
};

onMounted(() => {
    if (new URLSearchParams(window.location.search).has('create')) openCreateModal();
});
</script>

<template>
    <Head title="Contas" />
    <AuthenticatedLayout>
        <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div><p class="text-sm font-medium text-blue-700">Estrutura financeira</p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Contas</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Cadastre bancos, carteiras ou outros lugares onde seu dinheiro fica disponível.</p></div>
            <button type="button" class="flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800" @click="openCreateModal"><Plus :size="18" />Criar conta</button>
        </section>

        <section v-if="accounts.length" class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="account in accounts" :key="account.id" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-4"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-700"><Landmark :size="23" /></span><div class="flex items-center gap-1"><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" :aria-label="`Renomear ${account.name}`" @click="openEditModal(account)"><Pencil :size="17" /></button><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-700" :aria-label="`Excluir ${account.name}`" @click="deletingAccount = account"><Trash2 :size="17" /></button></div></div>
                <p class="mt-6 text-sm font-medium text-slate-500">{{ account.name }}</p><p class="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{{ formatMoney(account.available_balance) }}</p><div class="mt-4 flex items-center justify-between gap-3 text-xs"><span class="text-slate-400">Disponível na conta</span><span class="font-medium text-violet-700">{{ formatMoney(account.pockets_total) }} em caixinhas</span></div>
            </article>
        </section>

        <div v-else class="mt-8 flex min-h-[28rem] flex-col items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center shadow-sm"><span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-600"><WalletCards :size="29" /></span><h2 class="mt-6 text-xl font-semibold tracking-tight text-slate-950">Nenhuma conta cadastrada</h2><p class="mt-2 max-w-md text-sm leading-6 text-slate-500">Sua primeira conta será a base para registrar lançamentos e criar caixinhas.</p><button type="button" class="mt-6 flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800" @click="openCreateModal"><Plus :size="18" />Criar conta</button></div>

        <section v-if="deletedAccounts.length" class="mt-10"><div class="flex items-center gap-2"><ArchiveRestore :size="19" class="text-slate-500" /><h2 class="font-semibold text-slate-900">Excluídas recentemente</h2></div><div class="mt-4 grid gap-3"><article v-for="account in deletedAccounts" :key="account.id" class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center"><div><p class="text-sm font-semibold text-slate-800">{{ account.name }}</p><p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"><CalendarClock :size="14" />Disponível até {{ formatDate(account.restorable_until) }}</p></div><button type="button" class="flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50" :disabled="restoringAccountId === account.id" @click="restore(account)"><ArchiveRestore :size="16" />{{ restoringAccountId === account.id ? 'Restaurando...' : 'Restaurar' }}</button></article></div></section>

        <Modal :show="createModalOpen" max-width="lg" @close="closeCreateModal"><form class="p-6 sm:p-7" @submit.prevent="submitCreate"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-700">Nova conta</p><h2 class="mt-1 text-xl font-semibold text-slate-950">Onde seu dinheiro fica?</h2></div><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Fechar" @click="closeCreateModal"><X :size="20" /></button></div><div class="mt-6 grid gap-5"><div><InputLabel for="account-name" value="Nome da conta" /><TextInput id="account-name" v-model="createForm.name" class="mt-2 block w-full" autocomplete="off" autofocus /><InputError class="mt-2" :message="createForm.errors.name" /></div><div><InputLabel for="opening-balance" value="Saldo inicial" /><div class="relative mt-2"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-500">R$</span><TextInput id="opening-balance" v-model="createForm.opening_balance" inputmode="decimal" class="block w-full pl-10" /></div><p class="mt-2 text-xs text-slate-500">Use zero se a conta ainda não possui saldo.</p><InputError class="mt-2" :message="createForm.errors.opening_balance" /></div></div><div class="mt-7 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100" @click="closeCreateModal">Cancelar</button><button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50" :disabled="createForm.processing">{{ createForm.processing ? 'Criando...' : 'Criar conta' }}</button></div></form></Modal>

        <Modal :show="editingAccount !== null" max-width="md" @close="closeEditModal"><form class="p-6" @submit.prevent="submitEdit"><div class="flex items-center justify-between gap-4"><h2 class="text-xl font-semibold text-slate-950">Renomear conta</h2><button type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100" aria-label="Fechar" @click="closeEditModal"><X :size="20" /></button></div><div class="mt-6"><InputLabel for="edit-account-name" value="Nome da conta" /><TextInput id="edit-account-name" v-model="editForm.name" class="mt-2 block w-full" /><InputError class="mt-2" :message="editForm.errors.name" /></div><div class="mt-7 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100" @click="closeEditModal">Cancelar</button><button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="editForm.processing">Salvar</button></div></form></Modal>

        <Modal :show="deletingAccount !== null" max-width="md" @close="deletingAccount = null"><div class="p-6"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-700"><Trash2 :size="22" /></span><h2 class="mt-5 text-xl font-semibold text-slate-950">Excluir {{ deletingAccount?.name }}?</h2><p class="mt-2 text-sm leading-6 text-slate-500">Caixinhas e lançamentos vinculados também serão excluídos. Você poderá restaurar o conjunto por 30 dias.</p><div class="mt-7 flex justify-end gap-3"><button type="button" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100" @click="deletingAccount = null">Cancelar</button><button type="button" class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="deleteForm.processing" @click="submitDelete">{{ deleteForm.processing ? 'Excluindo...' : 'Excluir conta' }}</button></div></div></Modal>
    </AuthenticatedLayout>
</template>
