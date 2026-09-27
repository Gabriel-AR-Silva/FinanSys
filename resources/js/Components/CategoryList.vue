<script setup>
import { CircleMinus, CirclePlus, Pencil, Tags } from '@lucide/vue';

defineProps({
    title: { type: String, required: true },
    type: { type: String, required: true },
    items: { type: Array, required: true },
    statusProcessing: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'toggle-status']);
</script>

<template>
    <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-3">
            <span
                class="flex h-11 w-11 items-center justify-center rounded-2xl"
                :class="type === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
            >
                <Tags :size="21" />
            </span>
            <div>
                <h2 class="font-semibold text-slate-950">{{ title }}</h2>
                <p class="text-sm text-slate-500">{{ items.length }} categorias</p>
            </div>
        </div>

        <div v-if="items.length" class="mt-6 grid gap-2">
            <div
                v-for="category in items"
                :key="category.id"
                class="flex items-center gap-3 rounded-2xl border border-slate-100 px-4 py-3"
                :class="category.status === 'inactive' ? 'bg-slate-50 opacity-70' : 'bg-white'"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ category.name }}</p>
                    <p class="text-xs text-slate-500">
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

        <p v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
            Nenhuma categoria neste grupo.
        </p>
    </article>
</template>
