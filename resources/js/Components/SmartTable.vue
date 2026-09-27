<script setup>
import { computed, ref, watch } from 'vue';
import { ChevronDown, ChevronUp, Filter, Search, SlidersHorizontal, X } from '@lucide/vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    columns: { type: Array, required: true },
    rowKey: { type: String, default: 'id' },
    searchable: { type: Boolean, default: true },
    searchPlaceholder: { type: String, default: 'Buscar...' },
    emptyText: { type: String, default: 'Nenhum registro encontrado.' },
    pageSize: { type: Number, default: 10 },
    pageSizeOptions: { type: Array, default: () => [10, 25, 50] },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    filtersCollapsible: { type: Boolean, default: true },
    filtersOpenByDefault: { type: Boolean, default: false },
});

const query = ref('');
const searchColumn = ref('all');
const sortKey = ref('');
const sortDirection = ref('asc');
const page = ref(1);
const localPageSize = ref(props.pageSize);
const filtersOpen = ref(props.filtersOpenByDefault);

const searchableColumns = computed(() => props.columns.filter((column) => column.searchable !== false));
const filtered = computed(() => {
    const term = query.value.trim().toLocaleLowerCase('pt-BR');
    let result = [...props.rows];

    if (term) {
        const columns = searchColumn.value === 'all'
            ? searchableColumns.value
            : searchableColumns.value.filter((column) => column.key === searchColumn.value);
        result = result.filter((row) => columns.some((column) => String(row[column.key] ?? '').toLocaleLowerCase('pt-BR').includes(term)));
    }

    if (sortKey.value) {
        result.sort((a, b) => String(a[sortKey.value] ?? '').localeCompare(String(b[sortKey.value] ?? ''), 'pt-BR', { numeric: true }) * (sortDirection.value === 'asc' ? 1 : -1));
    }

    return result;
});
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / localPageSize.value)));
const safePage = computed(() => Math.min(page.value, pages.value));
const visible = computed(() => filtered.value.slice((safePage.value - 1) * localPageSize.value, safePage.value * localPageSize.value));

watch([query, searchColumn, localPageSize], () => { page.value = 1; });
watch(() => props.rows.length, () => { if (page.value > pages.value) page.value = pages.value; });

function sort(column) {
    if (column.sortable === false) return;
    if (sortKey.value === column.key) sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    else { sortKey.value = column.key; sortDirection.value = 'asc'; }
    page.value = 1;
}
function clearSearch() {
    query.value = '';
    searchColumn.value = 'all';
}
</script>

<template>
    <section class="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header v-if="title || description || searchable || $slots.filters || $slots.headerActions" class="border-b border-slate-100 p-4 sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div v-if="title || description" class="min-w-0">
                    <h2 v-if="title" class="font-semibold text-slate-950">{{ title }}</h2>
                    <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-end">
                    <slot name="headerActions" />
                    <button
                        v-if="$slots.filters && filtersCollapsible"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="filtersOpen = !filtersOpen"
                    >
                        <SlidersHorizontal :size="16" />Filtros
                        <ChevronUp v-if="filtersOpen" :size="15" />
                        <ChevronDown v-else :size="15" />
                    </button>
                </div>
            </div>

            <div v-if="searchable" class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_12rem]">
                <div class="relative">
                    <Search :size="17" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input v-model="query" type="search" :placeholder="searchPlaceholder" class="w-full rounded-xl border-slate-300 py-2.5 pl-9 pr-9 text-sm focus:border-emerald-500 focus:ring-emerald-500" />
                    <button v-if="query" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1 text-slate-400 hover:bg-slate-100" aria-label="Limpar busca" @click="clearSearch"><X :size="15" /></button>
                </div>
                <select v-model="searchColumn" class="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="all">Todos os campos</option>
                    <option v-for="column in searchableColumns" :key="column.key" :value="column.key">{{ column.label }}</option>
                </select>
            </div>

            <div v-if="$slots.filters && (!filtersCollapsible || filtersOpen)" class="mt-4 rounded-xl bg-slate-50 p-3">
                <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500"><Filter :size="14" />Filtros avançados</div>
                <slot name="filters" />
            </div>
        </header>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <button type="button" class="inline-flex items-center gap-1" :disabled="column.sortable === false" @click="sort(column)">
                                {{ column.label }}
                                <span v-if="sortKey === column.key">{{ sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            </button>
                        </th>
                        <th v-if="$slots.actions" class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <tr v-for="row in visible" :key="row[rowKey]" class="hover:bg-slate-50/70">
                        <td v-for="column in columns" :key="column.key" class="px-4 py-3 text-slate-700">
                            <slot :name="'cell-' + column.key" :row="row" :value="row[column.key]">{{ row[column.key] }}</slot>
                        </td>
                        <td v-if="$slots.actions" class="px-4 py-3 text-right"><slot name="actions" :row="row" /></td>
                    </tr>
                    <tr v-if="!visible.length"><td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="px-4 py-10 text-center text-sm text-slate-500">{{ emptyText }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 p-3 md:hidden">
            <template v-if="visible.length">
                <slot v-for="row in visible" :key="row[rowKey]" name="mobile" :row="row">
                    <article class="rounded-xl border border-slate-200 p-4">
                        <dl class="grid gap-2">
                            <div v-for="column in columns" :key="column.key" class="flex items-start justify-between gap-3 text-sm">
                                <dt class="text-slate-500">{{ column.label }}</dt>
                                <dd class="text-right font-medium text-slate-800"><slot :name="'cell-' + column.key" :row="row" :value="row[column.key]">{{ row[column.key] }}</slot></dd>
                            </div>
                        </dl>
                        <div v-if="$slots.actions" class="mt-3 flex justify-end"><slot name="actions" :row="row" /></div>
                    </article>
                </slot>
            </template>
            <p v-else class="py-8 text-center text-sm text-slate-500">{{ emptyText }}</p>
        </div>

        <footer v-if="filtered.length" class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ filtered.length }} registros · página {{ safePage }} de {{ pages }}</span>
            <div class="flex items-center gap-2">
                <select v-model.number="localPageSize" class="rounded-lg border-slate-200 py-1.5 text-xs">
                    <option v-for="size in pageSizeOptions" :key="size" :value="size">{{ size }} por página</option>
                </select>
                <button type="button" class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="page <= 1" @click="page--">Anterior</button>
                <button type="button" class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="page >= pages" @click="page++">Próxima</button>
            </div>
        </footer>
    </section>
</template>
