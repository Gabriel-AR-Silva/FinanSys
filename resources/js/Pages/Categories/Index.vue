<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CircleMinus, CirclePlus, Pencil, Plus, Tags } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({ categories: { type: Array, required: true } });
const createModalOpen = ref(false);
const editingCategory = ref(null);
const createForm = useForm({ name: '', type: 'expense' });
const editForm = useForm({ name: '' });
const statusForm = useForm({ status: 'inactive' });
const incomeCategories = computed(() => props.categories.filter((category) => category.type === 'income'));
const expenseCategories = computed(() => props.categories.filter((category) => category.type === 'expense'));

const closeCreate = () => { if (!createForm.processing) { createModalOpen.value = false; createForm.reset(); createForm.clearErrors(); } };
const submitCreate = () => createForm.post(route('categories.store'), { preserveScroll: true, onSuccess: closeCreate });
const openEdit = (category) => { editingCategory.value = category; editForm.name = category.name; editForm.clearErrors(); };
const closeEdit = () => { if (!editForm.processing) { editingCategory.value = null; editForm.reset(); editForm.clearErrors(); } };
const submitEdit = () => editForm.put(route('categories.update', editingCategory.value.id), { preserveScroll: true, onSuccess: closeEdit });
const toggleStatus = (category) => {
    statusForm.status = category.status === 'active' ? 'inactive' : 'active';
    statusForm.patch(route('categories.status.update', category.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Categorias" />
    <AuthenticatedLayout>
        <section class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div><p class="text-sm font-medium text-emerald-700">Organização</p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">Categorias</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Classifique receitas e despesas sem perder o histórico das categorias desativadas.</p></div>
            <button type="button" class="flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800" @click="createModalOpen = true"><Plus :size="18" />Nova categoria</button>
        </section>

        <section class="mt-8 grid gap-6 lg:grid-cols-2">
            <article v-for="group in [{ title: 'Receitas', type: 'income', items: incomeCategories }, { title: 'Despesas', type: 'expense', items: expenseCategories }]" :key="group.type" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-2xl" :class="group.type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"><Tags :size="21" /></span><div><h2 class="font-semibold text-slate-950">{{ group.title }}</h2><p class="text-sm text-slate-500">{{ group.items.length }} categorias</p></div></div>
                <div v-if="group.items.length" class="mt-6 grid gap-2">
                    <div v-for="category in group.items" :key="category.id" class="flex items-center gap-3 rounded-2xl border border-slate-100 px-4 py-3" :class="category.status === 'inactive' ? 'bg-slate-50 opacity-70' : 'bg-white'">
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-800">{{ category.name }}</p><p class="text-xs text-slate-500">{{ category.entries_count }} lançamentos · {{ category.status === 'active' ? 'Ativa' : 'Inativa' }}</p></div>
                        <button type="button" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-950" :aria-label="`Renomear ${category.name}`" @click="openEdit(category)"><Pencil :size="17" /></button>
                        <button type="button" class="rounded-lg p-2 transition" :class="category.status === 'active' ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-700 hover:bg-emerald-50'" :aria-label="category.status === 'active' ? `Desativar ${category.name}` : `Reativar ${category.name}`" :disabled="statusForm.processing" @click="toggleStatus(category)"><CircleMinus v-if="category.status === 'active'" :size="18" /><CirclePlus v-else :size="18" /></button>
                    </div>
                </div>
                <p v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Nenhuma categoria neste grupo.</p>
            </article>
        </section>

        <Modal :show="createModalOpen" @close="closeCreate"><form class="p-6" @submit.prevent="submitCreate"><h2 class="text-lg font-semibold text-slate-950">Nova categoria</h2><div class="mt-5"><InputLabel for="category-type" value="Natureza" /><select id="category-type" v-model="createForm.type" class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="income">Receita</option><option value="expense">Despesa</option></select><InputError class="mt-2" :message="createForm.errors.type" /></div><div class="mt-4"><InputLabel for="category-name" value="Nome" /><TextInput id="category-name" v-model="createForm.name" class="mt-2 block w-full" maxlength="255" autofocus /><InputError class="mt-2" :message="createForm.errors.name" /></div><div class="mt-6 flex justify-end gap-3"><SecondaryButton type="button" @click="closeCreate">Cancelar</SecondaryButton><PrimaryButton :disabled="createForm.processing">Criar categoria</PrimaryButton></div></form></Modal>
        <Modal :show="editingCategory !== null" @close="closeEdit"><form class="p-6" @submit.prevent="submitEdit"><h2 class="text-lg font-semibold text-slate-950">Renomear categoria</h2><div class="mt-5"><InputLabel for="edit-category-name" value="Nome" /><TextInput id="edit-category-name" v-model="editForm.name" class="mt-2 block w-full" maxlength="255" autofocus /><InputError class="mt-2" :message="editForm.errors.name" /></div><div class="mt-6 flex justify-end gap-3"><SecondaryButton type="button" @click="closeEdit">Cancelar</SecondaryButton><PrimaryButton :disabled="editForm.processing">Salvar</PrimaryButton></div></form></Modal>
    </AuthenticatedLayout>
</template>
