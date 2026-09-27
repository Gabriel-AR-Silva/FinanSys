<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SmartTable from '@/Components/SmartTable.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CircleMinus, CirclePlus, Pencil, Plus, Tags } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({ categories: { type: Array, required: true } });
const createModalOpen = ref(false);
const editingCategory = ref(null);
const typeFilter = ref('all');
const createForm = useForm({ name: '', type: 'expense' });
const editForm = useForm({ name: '' });
const statusForm = useForm({ status: 'inactive' });
const columns = [
    { key: 'name', label: 'Categoria' },
    { key: 'type_label', label: 'Tipo' },
    { key: 'entries_count', label: 'Lançamentos' },
    { key: 'status_label', label: 'Status' },
];

const filteredCategories = computed(() => props.categories
    .filter((category) => typeFilter.value === 'all' || category.type === typeFilter.value)
    .map((category) => ({
        ...category,
        type_label: category.type === 'income' ? 'Receita' : 'Despesa',
        status_label: category.status === 'active' ? 'Ativa' : 'Inativa',
    })));

const closeCreate = () => { if (!createForm.processing) { createModalOpen.value = false; createForm.reset(); createForm.clearErrors(); } };
const submitCreate = () => createForm.post(route('categories.store'), { preserveScroll: true, onSuccess: closeCreate });
const openEdit = (category) => { editingCategory.value = category; editForm.name = category.name; editForm.clearErrors(); };
const closeEdit = () => { if (!editForm.processing) { editingCategory.value = null; editForm.reset(); editForm.clearErrors(); } };
const submitEdit = () => editForm.put(route('categories.update', editingCategory.value.id), { preserveScroll: true, onSuccess: closeEdit });
const toggleStatus = (category) => {
    statusForm.status = category.status === 'active' ? 'inactive' : 'active';
    statusForm.patch(route('categories.status.update', category.id), { preserveScroll: true });
};

onMounted(() => {
    const requestedType = new URLSearchParams(window.location.search).get('create');
    if (['income', 'expense'].includes(requestedType)) {
        createForm.type = requestedType;
        createModalOpen.value = true;
    }
});
</script>

<template>
    <Head title="Categorias" />
    <AuthenticatedLayout>
        <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div><p class="text-sm font-medium text-emerald-700">Organização</p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Categorias</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Classifique receitas e despesas sem perder o histórico das categorias desativadas.</p></div>
            <button type="button" class="flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800" @click="createModalOpen = true"><Plus :size="18" />Nova categoria</button>
        </section>

        <div class="mt-8">
            <SmartTable
                :rows="filteredCategories"
                :columns="columns"
                title="Todas as categorias"
                :description="`${filteredCategories.length} de ${categories.length} categorias exibidas`"
                search-placeholder="Buscar categoria..."
                empty-text="Nenhuma categoria encontrada para este filtro."
                :page-size="10"
            >
                <template #headerActions>
                    <div class="flex w-full rounded-xl bg-slate-100 p-1 sm:w-auto" role="group" aria-label="Filtrar categorias por tipo">
                        <button
                            v-for="filter in [{ label: 'Todas', value: 'all' }, { label: 'Receitas', value: 'income' }, { label: 'Despesas', value: 'expense' }]"
                            :key="filter.value"
                            type="button"
                            class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition sm:flex-none"
                            :class="typeFilter === filter.value ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                            @click="typeFilter = filter.value"
                        >{{ filter.label }}</button>
                    </div>
                </template>

                <template #cell-name="{ row }">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl" :class="row.type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><Tags :size="17" /></span>
                        <span class="truncate font-semibold text-slate-900">{{ row.name }}</span>
                    </div>
                </template>
                <template #cell-type_label="{ row }"><span class="rounded-full px-2 py-1 text-xs font-semibold" :class="row.type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">{{ row.type_label }}</span></template>
                <template #cell-status_label="{ row }"><span :class="row.status === 'active' ? 'text-emerald-700' : 'text-slate-400'">{{ row.status_label }}</span></template>
                <template #actions="{ row }">
                    <div class="flex justify-end gap-1">
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-950" :aria-label="`Renomear ${row.name}`" @click="openEdit(row)"><Pencil :size="17" /></button>
                        <button type="button" class="rounded-lg p-2 transition" :class="row.status === 'active' ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-700 hover:bg-emerald-50'" :aria-label="row.status === 'active' ? `Desativar ${row.name}` : `Reativar ${row.name}`" :disabled="statusForm.processing" @click="toggleStatus(row)"><CircleMinus v-if="row.status === 'active'" :size="18" /><CirclePlus v-else :size="18" /></button>
                    </div>
                </template>
                <template #mobile="{ row }">
                    <article class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="row.type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><Tags :size="18" /></span>
                            <div class="min-w-0 flex-1"><p class="truncate font-semibold text-slate-900">{{ row.name }}</p><p class="mt-1 text-xs text-slate-500">{{ row.type_label }} · {{ row.entries_count }} lançamentos · {{ row.status_label }}</p></div>
                            <div class="flex shrink-0 gap-1"><button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click="openEdit(row)"><Pencil :size="17" /></button><button type="button" class="rounded-lg p-2" :class="row.status === 'active' ? 'text-rose-600' : 'text-emerald-700'" :disabled="statusForm.processing" @click="toggleStatus(row)"><CircleMinus v-if="row.status === 'active'" :size="18" /><CirclePlus v-else :size="18" /></button></div>
                        </div>
                    </article>
                </template>
            </SmartTable>
        </div>

        <Modal :show="createModalOpen" @close="closeCreate"><form class="p-6" @submit.prevent="submitCreate"><h2 class="text-lg font-semibold text-slate-950">Nova categoria</h2><div class="mt-5"><InputLabel for="category-type" value="Natureza" /><select id="category-type" v-model="createForm.type" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="income">Receita</option><option value="expense">Despesa</option></select><InputError class="mt-2" :message="createForm.errors.type" /></div><div class="mt-4"><InputLabel for="category-name" value="Nome" /><TextInput id="category-name" v-model="createForm.name" class="mt-2 block w-full" maxlength="255" autofocus /><InputError class="mt-2" :message="createForm.errors.name" /></div><div class="mt-6 flex justify-end gap-3"><SecondaryButton type="button" @click="closeCreate">Cancelar</SecondaryButton><PrimaryButton :disabled="createForm.processing">Criar categoria</PrimaryButton></div></form></Modal>
        <Modal :show="editingCategory !== null" @close="closeEdit"><form class="p-6" @submit.prevent="submitEdit"><h2 class="text-lg font-semibold text-slate-950">Renomear categoria</h2><div class="mt-5"><InputLabel for="edit-category-name" value="Nome" /><TextInput id="edit-category-name" v-model="editForm.name" class="mt-2 block w-full" maxlength="255" autofocus /><InputError class="mt-2" :message="editForm.errors.name" /></div><div class="mt-6 flex justify-end gap-3"><SecondaryButton type="button" @click="closeEdit">Cancelar</SecondaryButton><PrimaryButton :disabled="editForm.processing">Salvar</PrimaryButton></div></form></Modal>
    </AuthenticatedLayout>
</template>
