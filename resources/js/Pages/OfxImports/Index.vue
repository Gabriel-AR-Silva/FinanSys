<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    cards: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    imports: { type: Array, default: () => [] },
    review: { type: Object, default: null },
});

const form = useForm({ account_id: '', file: null });
const drafts = reactive({});
const pix = reactive({});
const selected = ref([]);

const expenseCategories = () => props.categories.filter((category) => category.type === 'expense');
const categoriesFor = (item) => props.categories.filter((category) => category.type === drafts[item.id]?.classification);
const label = (value) => ({ income: 'Receita', expense: 'Despesa', transfer_candidate: 'Possível transferência', card_credit_pix_candidate: 'Pix no Crédito', duplicate: 'Duplicado', unsupported: 'Não suportado', needs_review: 'Precisa de revisão' }[value] ?? value);
const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));

const resetState = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    Object.keys(pix).forEach((key) => delete pix[key]);
    selected.value = [];
    for (const item of props.review?.items ?? []) {
        drafts[item.id] = {
            classification: ['income', 'expense'].includes(item.classification) ? item.classification : 'needs_review',
            category_id: item.category_id ?? '',
            planning_type: item.planning_type ?? '',
        };
        if (item.classification === 'card_credit_pix_candidate' && item.direction === 'debit') {
            pix[item.id] = { credit_card_id: '', category_id: '', planning_type: 'ordinary', installments_count: 1, first_due_on: '' };
        }
    }
};
watch(() => props.review, resetState, { immediate: true });

const submit = () => form.post(route('ofx-imports.store'), { forceFormData: true, preserveScroll: true });
const saveReview = (item) => router.patch(route('ofx-imports.items.update', item.id), drafts[item.id], { preserveScroll: true });
const canConfirm = (item) => item.review_status !== 'confirmed' && ['income', 'expense'].includes(item.classification) && item.category_id && (item.classification !== 'expense' || item.planning_type);
const confirmSelected = () => props.review && selected.value.length && router.post(route('ofx-imports.confirm', props.review.id), { item_ids: selected.value }, { preserveScroll: true });
const confirmPix = (item) => router.post(route('ofx-imports.pix-credit.confirm', { import: props.review.id, item: item.id }), pix[item.id], { preserveScroll: true });
</script>

<template>
    <Head title="Importação OFX" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <header>
                <p class="text-sm font-semibold text-emerald-700">Entrada externa</p>
                <h1 class="text-2xl font-bold text-slate-950">Importação e conciliação OFX</h1>
                <p class="mt-2 text-sm text-slate-600">O arquivo só prepara sugestões. Nada é lançado sem sua confirmação.</p>
            </header>

            <form class="grid gap-3 rounded-2xl border bg-white p-5 md:grid-cols-[1fr_1.5fr_auto] md:items-end" @submit.prevent="submit">
                <label class="grid gap-1 text-sm">Conta
                    <select v-model="form.account_id" required class="rounded-xl border-slate-300">
                        <option value="" disabled>Selecione</option>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm">Arquivo OFX
                    <input required type="file" accept=".ofx,text/*" class="rounded-xl border p-2" @change="form.file = $event.target.files[0]" />
                    <span v-if="form.errors.file" class="text-xs text-rose-600">{{ form.errors.file }}</span>
                </label>
                <button :disabled="form.processing" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">{{ form.processing ? 'Analisando...' : 'Analisar extrato' }}</button>
            </form>

            <section v-if="review" class="space-y-3">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <strong>Revisão obrigatória:</strong> {{ review.institution }} · final {{ review.source_account_suffix }} · {{ review.transaction_count }} itens.
                </div>

                <article v-for="item in review.items" :key="item.id" class="rounded-2xl border bg-white p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap gap-2 text-xs text-slate-500"><span>{{ new Date(item.occurred_at).toLocaleDateString('pt-BR') }}</span><span class="rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-700">{{ label(item.classification) }}</span><span v-if="item.review_status === 'confirmed'" class="rounded-full bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">Confirmado</span></div>
                            <p class="mt-2 font-medium text-slate-950">{{ item.description }}</p>
                        </div>
                        <strong :class="item.direction === 'debit' ? 'text-rose-700' : 'text-emerald-700'">{{ item.direction === 'debit' ? '-' : '+' }}{{ money(item.amount) }}</strong>
                    </div>

                    <div v-if="item.review_status !== 'confirmed' && !['duplicate', 'unsupported', 'transfer_candidate', 'card_credit_pix_candidate'].includes(item.classification)" class="mt-4 grid gap-2 md:grid-cols-4">
                        <select v-model="drafts[item.id].classification" class="rounded-xl border-slate-300" @change="drafts[item.id].category_id = ''; drafts[item.id].planning_type = ''">
                            <option value="needs_review">Decidir depois</option><option value="income">Receita</option><option value="expense">Despesa</option>
                        </select>
                        <select v-if="['income','expense'].includes(drafts[item.id].classification)" v-model="drafts[item.id].category_id" class="rounded-xl border-slate-300">
                            <option value="" disabled>Categoria</option><option v-for="category in categoriesFor(item)" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                        <select v-if="drafts[item.id].classification === 'expense'" v-model="drafts[item.id].planning_type" class="rounded-xl border-slate-300">
                            <option value="" disabled>Planejamento</option><option value="fixed">Fixa</option><option value="ordinary">Ordinária</option><option value="extraordinary">Extraordinária</option>
                        </select>
                        <div class="flex gap-2"><button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white" @click="saveReview(item)">Salvar</button><label v-if="canConfirm(item)" class="flex items-center gap-2 text-sm"><input v-model="selected" type="checkbox" :value="item.id" />Confirmar</label></div>
                    </div>

                    <div v-else-if="item.classification === 'card_credit_pix_candidate' && item.direction === 'debit' && item.review_status !== 'confirmed'" class="mt-4 grid gap-2 rounded-xl border border-violet-200 bg-violet-50 p-3 md:grid-cols-2">
                        <select v-model="pix[item.id].credit_card_id" class="rounded-xl border-violet-200"><option value="" disabled>Cartão</option><option v-for="card in cards" :key="card.id" :value="card.id">{{ card.name }}</option></select>
                        <select v-model="pix[item.id].category_id" class="rounded-xl border-violet-200"><option value="" disabled>Categoria</option><option v-for="category in expenseCategories()" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                        <select v-model="pix[item.id].planning_type" class="rounded-xl border-violet-200"><option value="ordinary">Ordinária</option><option value="extraordinary">Extraordinária</option></select>
                        <div class="grid grid-cols-2 gap-2"><input v-model.number="pix[item.id].installments_count" type="number" min="1" max="120" class="rounded-xl border-violet-200" aria-label="Parcelas" /><input v-model="pix[item.id].first_due_on" type="date" class="rounded-xl border-violet-200" aria-label="Primeiro vencimento" /></div>
                        <button type="button" :disabled="!pix[item.id].credit_card_id || !pix[item.id].category_id || !pix[item.id].first_due_on" class="md:col-span-2 rounded-xl bg-violet-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40" @click="confirmPix(item)">Validar Pix no Crédito e criar compra no cartão</button>
                    </div>

                    <p v-else-if="item.review_status !== 'confirmed'" class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">{{ item.classification === 'card_credit_pix_candidate' ? 'Use o item de saída correspondente para validar a compra no cartão.' : item.classification === 'duplicate' ? 'Movimentação já identificada em importação anterior.' : 'Este item precisa de um fluxo específico.' }}</p>
                </article>

                <button type="button" :disabled="selected.length === 0" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-40" @click="confirmSelected">Confirmar {{ selected.length }} selecionado(s)</button>
            </section>

            <section v-if="imports.length" class="rounded-2xl border bg-white p-5">
                <h2 class="font-semibold">Importações recentes</h2>
                <Link v-for="item in imports" :key="item.id" :href="route('ofx-imports.index', { review: item.id })" class="mt-2 flex justify-between text-sm"><span>{{ item.institution }} · final {{ item.source_account_suffix }}</span><span>{{ item.transaction_count }} itens</span></Link>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
