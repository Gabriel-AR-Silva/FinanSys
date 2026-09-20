<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    pair: { type: Object, required: true },
    debit: { type: Object, required: true },
    credit: { type: Object, required: true },
});
const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
const confirmed = () => props.debit.review_status === 'confirmed' && props.credit.review_status === 'confirmed';
</script>

<template>
    <section class="rounded-xl border border-violet-200 bg-violet-50 p-3" :aria-label="`Par Pix no Crédito de ${money(pair.amount)}`">
        <h3 class="font-semibold text-violet-950">Pix no Crédito · par vinculado</h3>
        <p class="mt-1 text-xs text-violet-800">Entrada e saída do mesmo extrato; não são receita e despesa independentes.</p>
        <div class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div class="rounded-lg bg-white p-3"><span class="block text-slate-600">Entrada temporária</span><strong class="text-emerald-700">+{{ money(credit.amount) }}</strong><p class="mt-1 break-words text-xs text-slate-600">{{ credit.description }}</p></div>
            <div class="rounded-lg bg-white p-3"><span class="block text-slate-600">Saída correspondente</span><strong class="text-rose-700">-{{ money(debit.amount) }}</strong><p class="mt-1 break-words text-xs text-slate-600">{{ debit.description }}</p></div>
        </div>
        <p class="mt-3 text-sm font-semibold text-violet-950">Efeito líquido informado no banco: {{ money(pair.bank_effect) }}</p>
        <p class="mt-1 text-xs text-violet-800">{{ confirmed() ? 'Par confirmado como compra no cartão; não gera lançamentos bancários.' : 'Par aguardando confirmação da compra no cartão.' }}</p>
        <Link v-if="confirmed() && pair.purchase_id" :href="route('credit-cards.index', { purchase: pair.purchase_id })" class="mt-2 inline-block text-sm font-semibold text-violet-900 underline underline-offset-2">Ir para a compra no cartão</Link>
    </section>
</template>
