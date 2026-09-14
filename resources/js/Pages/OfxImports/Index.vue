<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FileUp, ShieldCheck, TriangleAlert } from '@lucide/vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    imports: { type: Array, default: () => [] },
    review: { type: Object, default: null },
});

const form = useForm({ account_id: '', file: null });

const submit = () => {
    form.post(route('ofx-imports.store'), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const classificationLabel = (value) => ({
    income: 'Receita sugerida',
    expense: 'Despesa sugerida',
    transfer_candidate: 'Possível transferência',
    card_credit_pix_candidate: 'Pix no Crédito / cartão',
    duplicate: 'Duplicado',
    unsupported: 'Não suportado',
    needs_review: 'Precisa de revisão',
}[value] ?? value);

const money = (value) => new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
}).format(Number(value));
</script>

<template>
    <Head title="Importação OFX" />
    <AuthenticatedLayout>
        <div class="space-y-6">
            <section>
                <p class="text-sm font-semibold text-emerald-700">Entrada externa</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Importação e conciliação OFX</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">O arquivo apenas prepara sugestões. Nenhuma movimentação financeira é criada sem sua validação explícita.</p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <form class="grid gap-4 md:grid-cols-[1fr_1.4fr_auto] md:items-end" @submit.prevent="submit">
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                        Conta do FinanSys
                        <select v-model="form.account_id" required class="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="" disabled>Selecione a conta</option>
                            <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                        </select>
                        <span v-if="form.errors.account_id" class="text-xs text-rose-600">{{ form.errors.account_id }}</span>
                    </label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">
                        Extrato OFX
                        <input required type="file" accept=".ofx,text/*" class="rounded-xl border border-slate-300 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:font-semibold" @change="form.file = $event.target.files[0]" />
                        <span v-if="form.errors.file" class="text-xs text-rose-600">{{ form.errors.file }}</span>
                    </label>
                    <button type="submit" :disabled="form.processing" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-50">
                        <FileUp :size="18" />{{ form.processing ? 'Analisando...' : 'Analisar extrato' }}
                    </button>
                </form>
            </section>

            <section v-if="review" class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">Etapa de revisão</h2>
                        <p class="text-sm text-slate-600">{{ review.institution }} · conta final {{ review.source_account_suffix }} · {{ review.transaction_count }} itens</p>
                    </div>
                    <span class="inline-flex w-fit items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800"><ShieldCheck :size="15" />Validação humana obrigatória</span>
                </div>

                <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Data</th><th class="px-4 py-3">Descrição</th><th class="px-4 py-3">Valor</th><th class="px-4 py-3">Sugestão</th><th class="px-4 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in review.items" :key="item.id" :class="item.classification === 'duplicate' ? 'bg-slate-50 opacity-70' : ''">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ new Date(item.occurred_at).toLocaleDateString('pt-BR') }}</td>
                                <td class="max-w-lg px-4 py-3 font-medium text-slate-900">{{ item.description }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-semibold" :class="item.direction === 'debit' ? 'text-rose-700' : 'text-emerald-700'">{{ item.direction === 'debit' ? '-' : '+' }}{{ money(item.amount) }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ classificationLabel(item.classification) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Aguardando você</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 md:hidden">
                    <article v-for="item in review.items" :key="item.id" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><p class="font-semibold text-slate-950">{{ item.description }}</p><TriangleAlert v-if="item.classification === 'needs_review'" :size="18" class="shrink-0 text-amber-600" /></div>
                        <div class="mt-3 flex items-center justify-between gap-3"><span class="text-xs text-slate-500">{{ new Date(item.occurred_at).toLocaleDateString('pt-BR') }}</span><strong :class="item.direction === 'debit' ? 'text-rose-700' : 'text-emerald-700'">{{ item.direction === 'debit' ? '-' : '+' }}{{ money(item.amount) }}</strong></div>
                        <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700">{{ classificationLabel(item.classification) }}</div>
                    </article>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">Esta etapa ainda não grava receitas, despesas ou saldo. Itens automáticos continuam aguardando sua confirmação antes de entrarem no domínio financeiro.</div>
            </section>

            <section v-if="imports.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Importações recentes</h2>
                <div class="mt-3 divide-y divide-slate-100">
                    <Link v-for="item in imports" :key="item.id" :href="route('ofx-imports.index', { review: item.id })" class="flex items-center justify-between gap-4 py-3 text-sm hover:text-emerald-700">
                        <span><strong>{{ item.institution }}</strong><span class="ml-2 text-slate-500">final {{ item.source_account_suffix }}</span></span>
                        <span class="text-xs text-slate-500">{{ item.transaction_count }} itens</span>
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
