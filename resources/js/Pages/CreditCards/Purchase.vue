<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    card: { type: Object, required: true },
    purchase: { type: Object, required: true },
});
const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
const date = (value) => value.split('-').reverse().join('/');
const status = (value) => ({ pending: 'Pendente', paid: 'Paga', advanced: 'Antecipada' }[value] ?? value);
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Compra · ${purchase.description}`" />
        <main class="mx-auto max-w-3xl space-y-5">
            <Link :href="route('credit-cards.index')" class="inline-block text-sm font-semibold text-emerald-800 underline underline-offset-2">← Voltar aos cartões</Link>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" :aria-label="`Compra ${purchase.description}`">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ card.name }} · compra #{{ purchase.id }}</p>
                <h1 class="mt-2 break-words text-2xl font-semibold text-slate-950">{{ purchase.description }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ purchase.category_name }} · {{ date(purchase.purchased_on) }}</p>
                <p class="mt-4 text-2xl font-bold text-slate-950">{{ money(purchase.gross_amount) }}</p>
                <h2 class="mt-6 text-lg font-semibold text-slate-900">Parcelas ({{ purchase.installments_count }})</h2>
                <ul class="mt-3 divide-y divide-slate-100">
                    <li v-for="installment in purchase.installments" :key="installment.number" class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <div><p class="font-semibold text-slate-900">Parcela {{ installment.number }}/{{ purchase.installments_count }}</p><p class="text-slate-600">Vence {{ date(installment.due_on) }} · {{ status(installment.status) }}</p></div>
                        <div class="text-right"><p class="font-semibold text-slate-900">{{ money(installment.gross_amount) }}</p><p class="text-xs text-slate-600">Pago: {{ money(installment.paid_amount) }}</p></div>
                    </li>
                </ul>
            </section>
        </main>
    </AuthenticatedLayout>
</template>
