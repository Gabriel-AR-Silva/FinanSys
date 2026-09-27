<script setup>
import CategoryList from '@/Components/CategoryList.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({ categories: { type: Array, required: true } });
const createModalOpen = ref(false);
const editingCategory = ref(null);
const typeFilter = ref('all');
const createForm = useForm({ name: '', type: 'expense' });
const editForm = useForm({ name: '' });
const statusForm = useForm({ status: 'inactive' });

const filteredCategories = computed(() => {
    if (typeFilter.value === 'all') {
        return props.categories;
    }

    return props.categories.filter((category) => category.type === typeFilter.value);
});

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

        <section class="mt-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-950">Todas as categorias</h2>
                    <p class="text-sm text-slate-500">{{ filteredCategories.length }} de {{ categories.length }} categorias exibidas</p>
                </div>

                <div class="flex w-full rounded-xl bg-slate-100 p-1 sm:w-auto" role="group" aria-label="Filtrar categorias por tipo">
                    <button
                        v-for="filter in [{ label: 'Todas', value: 'all' }, { label: 'Receitas', value: 'income' }, { label: 'Despesas', value: 'expense' }]"
                        :key="filter.value"
                        type="button"
                        class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition sm:flex-none"
                        :class="typeFilter === filter.value ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                        @click="typeFilter = filter.value"
                    >
                        {{ filter.label }}
                    </button>
                </div>
            </div>

            <div class="mt-6">
                <CategoryList
                    :items="filteredCategories"
                    :status-processing="statusForm.processing"
                    @edit="openEdit"
                    @toggle-status="toggleStatus"
                />
            </div>
        </section>

        <Modal :show="createModalOpen" @close="closeCreate"><form class="p-6" @submit.prevent="submitCreate"><h2 class="text-lg font-semibold text-slate-950">Nova categoria</h2><div class="mt-5"><InputLabel for="category-type" value="Natureza" /><select id="category-type" v-model="createForm.type" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="income">Receita</option><option value="expense">Despesa</option></select><InputError class="mt-2" :message="createForm.errors.type" /></div><div class="mt-4"><InputLabel for="category-name" value="Nome" /><TextInput id="category-name" v-model="createForm.name" class="mt-2 block w-full" maxlength="255" autofocus /><InputError class="mt-2" :message="createForm.errors.name" /></div><div class="mt-6 flex justify-end gap-3"><SecondaryButton type="button" @click="closeCreate">Cancelar</SecondaryButton><PrimaryButton :disabled="createForm.processing">Criar categoria</PrimaryButton></div></form></Modal>
        <Modal :show="editingCategory !== null" @close="closeEdit"><form class="p-6" @submit.prevent="submitEdit"><h2 class="text-lg font-semibold text-slate-950">Renomear categoria</h2><div class="mt-5"><InputLabel for="edit-category-name" value="Nome" /><TextInput id="edit-category-name" v-model="editForm.name" class="mt-2 block w-full" maxlength="255" autofocus /><InputError class="mt-2" :message="editForm.errors.name" /></div><div class="mt-6 flex justify-end gap-3"><SecondaryButton type="button" @click="closeEdit">Cancelar</SecondaryButton><PrimaryButton :disabled="editForm.processing">Salvar</PrimaryButton></div></form></Modal>
    </AuthenticatedLayout>
</template>
