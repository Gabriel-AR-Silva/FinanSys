<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { FileUp, ShieldCheck } from '@lucide/vue';
import { reactive } from 'vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    imports: { type: Array, default: () => [] },
    review: { type: Object, default: null },
});

const form = useForm({ account_id: '', file: null });
const drafts = reactive(Object.fromEntries((props.review?.items ?? []).map((item) => [item.id, {
    classification: ['income', 'expense'].includes(item.classification) ? item.classification : 'needs_review',
    category_id: item.category_id ?? '',
    planning_type: item.planning_type ?? '',
}])));

const submit = () => form.post(route('ofx-imports.store'), { forceFormData: true, preserveScroll: true });
const editable = (item) => !['duplicate', 'unsupported', 'card_credit_pix_candidate', 'transfer_candidate'].includes(item.classification);
const categoriesFor = (item) => props.categories.filter((category) => category.type === drafts[item.id]?.classification);
const saveReview = (item) => router.patch(route('ofx-imports.items.update', item.id), drafts[item.id], { preserveScroll: true });
const label = (value) => ({ income: 'Receita', expense: 'Despesa', transfer_candidate: 'Possível transferência', card_credit_pix_candidate: 'Pix no Crédito / cartão', duplicate: 'Duplicado', unsupported: 'Não suportado', needs_review: 'Precisa de revisão' }[value] ?? value);
const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
</script>

<template>
    <Head title="Importação OFX" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <section>
                <p class="text-sm font-semibold text-emerald-700">Entrada externa</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Importação e conciliação OFX</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">O OFX só prepara sugestões. Nenhuma movimentação financeira é criada sem validação explícita do usuário.</p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <form class="grid gap-4 md:grid-cols-[1fr_1.4fr_auto] md:items-end" @submit.prevent="submit">
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Conta
                        <select v-model="form.account_id" required class="rounded-xl border-slate-300 text-sm">
                            <option value="" disabled>Selecione</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                        </select>
                        <span v-if="form.errors.account_id" class="text-xs text-rose-600">{{ form.errors.account_id }}</span>
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Arquivo OFX
                        <input required type="file" accept=".ofx,text/*" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" @change="form.file = $event.target.files[0]" />
                        <span v-if="form.errors.file" class="text-xs text-rose-600">{{ form.errors.file }}</span>
                    </label>
                    <button type="submit" :disabled="form.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"><FileUp :size="18" />{{ form.processing ? 'Analisando...' : 'Analisar extrato' }}</button>
                </form>
            </section>

            <section v-if="review" class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><h2 class="text-lg font-semibold text-slate-950">Revisão obrigatória</h2><p class="text-sm text-slate-600">{{ review.institution }} · final {{ review.source_account_suffix }} · {{ review.transaction_count }} itens</p></div>
                    <span class="inline-flex w-fit items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800"><ShieldCheck :size="15" />Nada é lançado automaticamente</span>
                </div>

                <div class="grid gap-3">
                    <article v-for="item in review.items" :key="item.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" :class="item.classification === 'duplicate' ? 'opacity-65' : ''">
                        <div class="grid gap-4 lg:grid-cols-[1.3fr_auto_1fr] lg:items-start">
                            <div>
                                <div class="flex flex-wrap items-center gap-2"><span class="text-xs text-slate-500">{{ new Date(item.occurred_at).toLocaleDateString('pt-BR') }}</span><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ label(item.classification) }}</span></div>
                                <p class="mt-2 font-medium text-slate-950">{{ item.description }}</p>
                            </div>
                            <strong class="text-lg" :class="item.direction === 'debit' ? 'text-rose-700' : 'text-emerald-700'">{{ item.direction === 'debit' ? '-' : '+' }}{{ money(item.amount) }}</strong>

                            <div v-if="editable(item) && drafts[item.id]" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                <select v-model="drafts[item.id].classification" class="rounded-xl border-slate-300 text-sm" @change="drafts[item.id].category_id = ''; drafts[item.id].planning_type = ''">
                                    <option value="needs_review">Decidir depois</option>
                                    <option value="income">Receita</option>
                                    <option value="expense">Despesa</option>
                                </select>
                                <select v-if="['income', 'expense'].includes(drafts[item.id].classification)" v-model="drafts[item.id].category_id" class="rounded-xl border-slate-300 text-sm">
                                    <option value="" disabled>Categoria</option>
                                    <option v-for="category in categoriesFor(item)" :key="category.id" :value="category.id">{{ category.name }}</option>
                                </select>
                                <select v-if="drafts[item.id].classification === 'expense'" v-model="drafts[item.id].planning_type" class="rounded-xl border-slate-300 text-sm">
                                    <option value="" disabled>Planejamento</option>
                                    <option value="fixed">Fixa</option>
                                    <option value="ordinary">Ordinária</option>
                                    <option value="extraordinary">Extraordinária</option>
                                </select>
                                <button type="button" class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-semibold text-white" @click="saveReview(item)">Salvar revisão</button>
                            </div>
                            <div v-else class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                {{ item.classification === 'card_credit_pix_candidate' ? 'Operação composta de cartão. Será validada em fluxo específico.' : item.classification === 'duplicate' ? 'Já identificado em importação anterior.' : 'Este item exige um fluxo específico.' }}
                            </div>
                        </div>
                    </article>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">Salvar a revisão ainda não cria lançamento. A confirmação final continuará sendo uma ação separada e explícita.</div>
            </section>

            <section v-if="imports.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Importações recentes</h2>
                <div class="mt-3 divide-y divide-slate-100">
                    <Link v-for="item in imports" :key="item.id" :href="route('ofx-imports.index', { review: item.id })" class="flex items-center justify-between gap-4 py-3 text-sm hover:text-emerald-700"><span><strong>{{ item.institution }}</strong><span class="ml-2 text-slate-500">final {{ item.source_account_suffix }}</span></span><span class="text-xs text-slate-500">{{ item.transaction_count }} itens</span></Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
