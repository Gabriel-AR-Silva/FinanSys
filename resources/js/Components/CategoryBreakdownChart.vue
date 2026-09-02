<script setup>
import { computed } from 'vue';

const props = defineProps({ categories: { type: Array, required: true } });
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const formatMoney = (value) => currency.format(Math.abs(Number(value)));
const maxValue = computed(() => Math.max(...props.categories.map((category) => Math.abs(Number(category.total))), 1));
</script>

<template>
    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div>
            <h2 class="text-sm font-semibold text-slate-950">Movimentação por categoria</h2>
            <p class="mt-0.5 text-xs text-slate-500">Categorias ordenadas pelo maior impacto.</p>
        </div>
        <div v-if="categories.length" class="mt-4 grid max-h-64 gap-3 overflow-y-auto pr-1">
            <div v-for="category in categories" :key="`${category.id ?? 'none'}-${category.type}`">
                <div class="flex items-center justify-between gap-3 text-xs"><span class="truncate font-medium text-slate-700">{{ category.name }}</span><span class="shrink-0 font-semibold" :class="category.type === 'income' ? 'text-emerald-700' : 'text-rose-700'">{{ category.type === 'income' ? '+' : '−' }} {{ formatMoney(category.total) }}</span></div>
                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" :class="category.type === 'income' ? 'bg-emerald-500' : 'bg-rose-500'" :style="{ width: `${Math.max(2, Math.abs(Number(category.total)) / maxValue * 100)}%` }" /></div>
            </div>
        </div>
        <p v-else class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">Sem movimentações no período.</p>
    </article>
</template>
