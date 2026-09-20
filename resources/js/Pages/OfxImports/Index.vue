<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import OfxPixPair from '@/Components/OfxPixPair.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    cards: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    imports: { type: Array, default: () => [] },
    review: { type: Object, default: null },
    pixPairs: { type: Array, default: () => [] },
});
const form = useForm({ account_id: '', file: null });
const fileInput = ref(null);
const isDragging = ref(false);
const fileError = ref('');
const drafts = reactive({});
const pix = reactive({});
const selected = ref([]);
const expenseCategories = () => props.categories.filter((category) => category.type === 'expense');
const categoriesFor = (item) => props.categories.filter((category) => category.type === drafts[item.id]?.classification);
const label = (value) => ({ income: 'Receita', expense: 'Despesa', transfer_candidate: 'Possível transferência', card_credit_pix_candidate: 'Pix no Crédito', duplicate: 'Duplicado', unsupported: 'Não suportado', needs_review: 'Precisa de revisão' }[value] ?? value);
const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
const displayDate = (value) => value ? new Date(value).toLocaleDateString('pt-BR') : '—';
const pairsByDebit = computed(() => new Map(props.pixPairs.map((pair) => [pair.debit_item_id, pair])));
const pairedCredits = computed(() => new Set(props.pixPairs.map((pair) => pair.credit_item_id)));
const itemsById = computed(() => new Map((props.review?.items ?? []).map((item) => [item.id, item])));
const displayItems = computed(() => (props.review?.items ?? []).filter((item) => !pairedCredits.value.has(item.id)));
const summary = computed(() => {
    const items = props.review?.items ?? [];
    const cents = (value) => Math.round(Number(value) * 100);
    const credit = items.filter((item) => item.direction === 'credit').reduce((total, item) => total + cents(item.amount), 0);
    const debit = items.filter((item) => item.direction === 'debit').reduce((total, item) => total + cents(item.amount), 0);
    return { credit: credit / 100, debit: debit / 100, net: (credit - debit) / 100,
        confirmed: items.filter((item) => item.review_status === 'confirmed').length,
        duplicates: items.filter((item) => item.classification === 'duplicate').length,
        pending: items.filter((item) => item.review_status !== 'confirmed' && item.classification !== 'duplicate').length };
});
const selectedAccount = computed(() => props.accounts.find((account) => account.id === Number(props.review?.account_id))?.name ?? 'Conta escolhida na importação');
const hasSelectableItems = computed(() => (props.review?.items ?? []).some(canConfirm));
const isConfirmedPix = (item) => item.classification === 'card_credit_pix_candidate' && item.review_status === 'confirmed';
const resetState = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    Object.keys(pix).forEach((key) => delete pix[key]);
    selected.value = [];
    for (const item of props.review?.items ?? []) {
        drafts[item.id] = { classification: ['income', 'expense'].includes(item.classification) ? item.classification : 'needs_review', category_id: item.category_id ?? '', planning_type: item.planning_type ?? '' };
        if (item.classification === 'card_credit_pix_candidate' && item.direction === 'debit') pix[item.id] = { credit_card_id: '', category_id: '', planning_type: 'ordinary', installments_count: 1, first_due_on: '' };
    }
};
watch(() => props.review, resetState, { immediate: true });
const acceptFile = (file) => {
    if (form.processing || !file) return;
    fileError.value = '';
    form.clearErrors('file');
    if (!/\.ofx$/i.test(file.name)) { form.file = null; fileError.value = 'Escolha um arquivo com extensão .ofx.'; }
    else if (file.size > 2 * 1024 * 1024) { form.file = null; fileError.value = 'O arquivo OFX deve ter no máximo 2 MB.'; }
    else if (file.size === 0) { form.file = null; fileError.value = 'O arquivo está vazio.'; }
    else form.file = file;
};
const onFileChange = (event) => acceptFile(event.target.files?.[0]);
const onDrop = (event) => { isDragging.value = false; acceptFile(event.dataTransfer?.files?.[0]); };
const submit = () => {
    if (form.processing) return;
    if (!form.file) { fileError.value = 'Selecione ou arraste um arquivo OFX antes de analisar.'; return; }
    if (!form.account_id) { form.setError('account_id', 'Selecione a conta do extrato.'); return; }
    form.post(route('ofx-imports.store'), { forceFormData: true, preserveScroll: true });
};
const saveReview = (item) => router.patch(route('ofx-imports.items.update', item.id), drafts[item.id], { preserveScroll: true });
function canConfirm(item) { return item.review_status !== 'confirmed' && ['income', 'expense'].includes(item.classification) && item.category_id && (item.classification !== 'expense' || item.planning_type); }
const confirmSelected = () => props.review && selected.value.length && router.post(route('ofx-imports.confirm', props.review.id), { item_ids: selected.value }, { preserveScroll: true });
const confirmPix = (item) => router.post(route('ofx-imports.pix-credit.confirm', { import: props.review.id, item: item.id }), pix[item.id], { preserveScroll: true });
</script>

<template>
    <Head title="Importação OFX" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <header><p class="text-sm font-semibold text-emerald-700">Entrada externa</p><h1 class="text-2xl font-bold text-slate-950">Importação e conciliação OFX</h1><p class="mt-2 text-sm text-slate-600">1. Envie o extrato · 2. Revise cada operação · 3. Confirme o que deve entrar no financeiro.</p><p class="mt-1 text-xs text-slate-500">Analisar um extrato não altera seu saldo. Alguns itens podem virar compras no cartão, sem criar receita bancária.</p></header>
            <form class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 lg:grid-cols-[minmax(12rem,1fr)_minmax(18rem,2fr)]" @submit.prevent="submit">
                <div class="space-y-2"><label for="ofx-account" class="block text-sm font-semibold text-slate-800">1. Conta do extrato</label><select id="ofx-account" v-model="form.account_id" :disabled="form.processing" required class="w-full rounded-xl border-slate-300" @change="form.clearErrors('account_id')"><option value="" disabled>Selecione a conta</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option></select><p v-if="form.errors.account_id" role="alert" class="text-xs text-rose-700">{{ form.errors.account_id }}</p><p class="text-xs leading-5 text-slate-500">Escolha a conta à qual este extrato pertence. Não selecione outra só para prosseguir.</p></div>
                <div class="space-y-2"><span id="ofx-file-label" class="block text-sm font-semibold text-slate-800">2. Arquivo OFX</span><div role="group" aria-labelledby="ofx-file-label" class="rounded-xl border-2 border-dashed p-4 transition-colors" :class="isDragging ? 'border-emerald-600 bg-emerald-50' : 'border-slate-300 bg-slate-50'" @dragenter.prevent="isDragging = true" @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false" @drop.prevent="onDrop"><input id="ofx-file" ref="fileInput" type="file" accept=".ofx" class="sr-only" :disabled="form.processing" @change="onFileChange" /><p class="text-sm font-semibold text-slate-800">Arraste seu OFX aqui ou</p><button type="button" :disabled="form.processing" class="mt-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-800 hover:border-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600 disabled:opacity-50" @click="fileInput?.click()">Escolher arquivo</button><p class="mt-2 break-all text-xs text-slate-600" aria-live="polite">{{ form.file ? `${form.file.name} · ${(form.file.size / 1024).toFixed(1)} KB` : 'Nenhum arquivo selecionado · .ofx · até 2 MB' }}</p></div><p v-if="fileError || form.errors.file" role="alert" class="text-xs text-rose-700">{{ fileError || form.errors.file }}</p></div>
                <div class="lg:col-span-2 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"><p class="text-xs text-slate-500">Você revisará os itens antes de qualquer confirmação.</p><button type="submit" :disabled="form.processing || !form.account_id || !form.file" class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">{{ form.processing ? 'Analisando...' : 'Analisar extrato' }}</button></div>
            </form>
            <section v-if="review" class="space-y-4" aria-labelledby="ofx-review-title">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><h2 id="ofx-review-title" class="font-semibold text-amber-950">3. Revisão do extrato</h2><p class="mt-1 text-sm text-amber-950">{{ review.institution }} · final {{ review.source_account_suffix }} · {{ selectedAccount }} · {{ review.transaction_count }} itens.</p><p class="mt-1 text-xs text-amber-800">Os totais abaixo são os movimentos informados pelo banco, não lançamentos criados pelo FinanSys.</p></div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><div class="rounded-xl border border-slate-200 bg-white p-3"><p class="text-xs text-slate-500">Entradas no extrato</p><p class="mt-1 font-semibold text-emerald-700">{{ money(summary.credit) }}</p></div><div class="rounded-xl border border-slate-200 bg-white p-3"><p class="text-xs text-slate-500">Saídas no extrato</p><p class="mt-1 font-semibold text-rose-700">{{ money(summary.debit) }}</p></div><div class="rounded-xl border border-slate-200 bg-white p-3"><p class="text-xs text-slate-500">Diferença líquida informada</p><p class="mt-1 font-semibold text-slate-900">{{ money(summary.net) }}</p></div><div class="rounded-xl border border-slate-200 bg-white p-3"><p class="text-xs text-slate-500">Situação da revisão</p><p class="mt-1 text-sm font-semibold text-slate-900">{{ summary.confirmed }} confirmados · {{ summary.pending }} para revisar</p><p v-if="summary.duplicates" class="text-xs text-slate-500">{{ summary.duplicates }} duplicados</p></div></div>
                <div v-if="review.items.some((item) => item.classification === 'card_credit_pix_candidate')" class="rounded-xl border border-violet-200 bg-violet-50 p-3 text-sm text-violet-900"><strong>Pix no Crédito:</strong> a entrada temporária e a saída correspondente representam uma compra no cartão. Confirmar o par cria a compra no cartão, não uma receita e despesa duplicadas na conta.</div>
                <article v-for="item in displayItems" :key="item.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <OfxPixPair v-if="pairsByDebit.has(item.id)" :pair="pairsByDebit.get(item.id)" :debit="item" :credit="itemsById.get(pairsByDebit.get(item.id).credit_item_id)" />
                    <div v-else class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2 text-xs text-slate-500"><span>{{ displayDate(item.occurred_at) }}</span><span class="rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-700">{{ label(item.classification) }}</span><span v-if="isConfirmedPix(item)" class="rounded-full bg-violet-100 px-2 py-1 font-semibold text-violet-800">Compra registrada no cartão</span><span v-else-if="item.review_status === 'confirmed'" class="rounded-full bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">Lançado na conta</span><span v-else-if="item.classification === 'duplicate'" class="rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-600">Possível duplicidade</span><span v-else class="rounded-full bg-amber-50 px-2 py-1 font-semibold text-amber-800">Aguardando revisão</span></div><p class="mt-2 break-words font-medium text-slate-950">{{ item.description }}</p></div><strong class="shrink-0" :class="item.direction === 'debit' ? 'text-rose-700' : 'text-emerald-700'">{{ item.direction === 'debit' ? '-' : '+' }}{{ money(item.amount) }}</strong></div>
                    <p v-if="isConfirmedPix(item) && !pairsByDebit.has(item.id)" class="mt-3 rounded-xl bg-violet-50 p-3 text-xs leading-5 text-violet-900">Este item foi confirmado como parte de uma compra no cartão; não adiciona nem desconta dinheiro da conta por si só. <Link :href="route('credit-cards.index')" class="font-semibold underline underline-offset-2">Ver cartões</Link></p>
                    <div v-if="item.review_status !== 'confirmed' && !['duplicate', 'unsupported', 'transfer_candidate', 'card_credit_pix_candidate'].includes(item.classification)" class="mt-4 grid gap-2 md:grid-cols-4"><select v-model="drafts[item.id].classification" class="rounded-xl border-slate-300" :aria-label="`Classificação de ${item.description}`" @change="drafts[item.id].category_id = ''; drafts[item.id].planning_type = ''"><option value="needs_review">Decidir depois</option><option value="income">Receita</option><option value="expense">Despesa</option></select><select v-if="['income','expense'].includes(drafts[item.id].classification)" v-model="drafts[item.id].category_id" class="rounded-xl border-slate-300" aria-label="Categoria da movimentação"><option value="" disabled>Categoria</option><option v-for="category in categoriesFor(item)" :key="category.id" :value="category.id">{{ category.name }}</option></select><select v-if="drafts[item.id].classification === 'expense'" v-model="drafts[item.id].planning_type" class="rounded-xl border-slate-300" aria-label="Tipo de planejamento"><option value="" disabled>Planejamento</option><option value="fixed">Fixa</option><option value="ordinary">Ordinária</option><option value="extraordinary">Extraordinária</option></select><div class="flex flex-wrap items-center gap-2"><button type="button" class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-semibold text-white" @click="saveReview(item)">Salvar revisão</button><label v-if="canConfirm(item)" class="flex items-center gap-2 text-sm"><input v-model="selected" type="checkbox" :value="item.id" />Selecionar para lançar</label></div></div>
                    <div v-else-if="item.classification === 'card_credit_pix_candidate' && item.direction === 'debit' && item.review_status !== 'confirmed'" class="mt-4 grid gap-2 rounded-xl border border-violet-200 bg-violet-50 p-3 md:grid-cols-2"><select v-model="pix[item.id].credit_card_id" class="rounded-xl border-violet-200" aria-label="Cartão do Pix no Crédito"><option value="" disabled>Cartão</option><option v-for="card in cards" :key="card.id" :value="card.id">{{ card.name }}</option></select><select v-model="pix[item.id].category_id" class="rounded-xl border-violet-200" aria-label="Categoria do Pix no Crédito"><option value="" disabled>Categoria</option><option v-for="category in expenseCategories()" :key="category.id" :value="category.id">{{ category.name }}</option></select><select v-model="pix[item.id].planning_type" class="rounded-xl border-violet-200" aria-label="Planejamento do Pix no Crédito"><option value="ordinary">Ordinária</option><option value="extraordinary">Extraordinária</option></select><div class="grid grid-cols-2 gap-2"><input v-model.number="pix[item.id].installments_count" type="number" min="1" max="120" class="rounded-xl border-violet-200" aria-label="Parcelas" /><input v-model="pix[item.id].first_due_on" type="date" class="rounded-xl border-violet-200" aria-label="Primeiro vencimento" /></div><button type="button" :disabled="!pix[item.id].credit_card_id || !pix[item.id].category_id || !pix[item.id].first_due_on" class="rounded-xl bg-violet-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40 md:col-span-2" @click="confirmPix(item)">Confirmar compra no cartão (não altera o saldo da conta)</button></div>
                    <p v-else-if="item.review_status !== 'confirmed' && !pairsByDebit.has(item.id)" class="mt-3 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">{{ item.classification === 'card_credit_pix_candidate' ? 'Use o item de saída correspondente para validar a compra no cartão.' : item.classification === 'duplicate' ? 'Movimentação já identificada em importação anterior. Não será lançada novamente.' : 'Este item precisa de um fluxo específico; nada foi lançado.' }}</p>
                </article>
                <div v-if="hasSelectableItems || selected.length" class="rounded-xl border border-emerald-200 bg-emerald-50 p-3"><p class="mb-2 text-xs text-emerald-900">Somente receitas e despesas revisadas e selecionadas serão lançadas na conta. Compras no cartão são confirmadas separadamente.</p><button type="button" :disabled="selected.length === 0" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-40" @click="confirmSelected">Lançar {{ selected.length }} movimentação(ões) selecionada(s)</button></div><p v-else class="rounded-xl bg-slate-100 p-3 text-sm text-slate-600">Não há receitas ou despesas selecionáveis para lançamento nesta revisão.</p>
            </section>
            <section v-if="imports.length" class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-semibold text-slate-900">Extratos analisados recentemente</h2><p class="mt-1 text-xs text-slate-500">A presença nesta lista não significa que todas as movimentações tenham sido lançadas na conta.</p><Link v-for="item in imports" :key="item.id" :href="route('ofx-imports.index', { review: item.id })" class="mt-3 flex flex-wrap justify-between gap-2 rounded-lg border border-slate-100 p-3 text-sm hover:border-emerald-300"><span>{{ item.institution }} · final {{ item.source_account_suffix }}</span><span class="text-slate-500">{{ item.transaction_count }} itens · {{ item.status === 'confirmed' ? 'Revisão concluída' : 'Revisão pendente' }}</span></Link></section>
        </div>
    </AuthenticatedLayout>
</template>