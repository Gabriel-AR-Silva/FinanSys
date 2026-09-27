<script setup>
import { CircleMinus, CirclePlus, Pencil, Tags } from '@lucide/vue';

defineProps({
    items: { type: Array, required: true },
    statusProcessing: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'toggle-status']);
</script>

<template>
    <div v-if="items.length" class="grid gap-2">
        <div
            v-for="category in items"
            :key="category.id"
            class="flex items-center gap-3 rounded-2xl border border-slate-100 px-4 py-3"
            :class="category.status === 'inactive' ? 'bg-slate-50 opacity-70' : 'bg-white'"
        >
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                :class="category.type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                :title="category.type === 'income' ? 'Receita' : 'Despesa'"
            >
                <Tags :size="17" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ category.name }}</p>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="category.type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                    >
                        {{ category.type === 'income' ? 'Receita' : 'Despesa' }}
                    </span>
                </div>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ category.entries_count }} lançamentos · {{ category.status === 'active' ? 'Ativa' : 'Inativa' }}
                </p>
            </div>

            <button
                type="button"
                class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-950"
                :aria-label="`Renomear ${category.name}`"
                @click="emit('edit', category)"
            >
                <Pencil :size="17" />
            </button>

            <button
                type="button"
                class="rounded-lg p-2 transition"
                :class="category.status === 'active' ? 'text-rose-600 hover:bg-rose-50' : 'text-emerald-700 hover:bg-emerald-50'"
                :aria-label="category.status === 'active' ? `Desativar ${category.name}` : `Reativar ${category.name}`"
                :disabled="statusProcessing"
                @click="emit('toggle-status', category)"
            >
                <CircleMinus v-if="category.status === 'active'" :size="18" />
                <CirclePlus v-else :size="18" />
            </button>
        </div>
    </div>

    <p v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
        Nenhuma categoria encontrada para este filtro.
    </p>
</template>
