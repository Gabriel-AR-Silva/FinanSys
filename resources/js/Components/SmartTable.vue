<script setup>
import { computed, ref } from "vue";

const props = defineProps({
    rows: { type: Array, default: () => [] },
    columns: { type: Array, required: true },
    rowKey: { type: String, default: "id" },
    searchable: { type: Boolean, default: true },
    searchPlaceholder: { type: String, default: "Buscar..." },
    emptyText: { type: String, default: "Nenhum registro encontrado." },
    pageSize: { type: Number, default: 10 },
});
const query = ref("");
const sortKey = ref("");
const sortDirection = ref("asc");
const page = ref(1);
const filtered = computed(() => {
    const term = query.value.trim().toLocaleLowerCase("pt-BR");
    let result = term ? props.rows.filter((row) => props.columns.some((column) => column.searchable !== false && String(row[column.key] ?? "").toLocaleLowerCase("pt-BR").includes(term))) : [...props.rows];
    if (sortKey.value) result.sort((a, b) => String(a[sortKey.value] ?? "").localeCompare(String(b[sortKey.value] ?? ""), "pt-BR", { numeric: true }) * (sortDirection.value === "asc" ? 1 : -1));
    return result;
});
const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / props.pageSize)));
const visible = computed(() => filtered.value.slice((Math.min(page.value, pages.value) - 1) * props.pageSize, Math.min(page.value, pages.value) * props.pageSize));
function sort(column) {
    if (column.sortable === false) return;
    if (sortKey.value === column.key) sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
    else { sortKey.value = column.key; sortDirection.value = "asc"; }
    page.value = 1;
}
function search() { page.value = 1; }
</script>

<template>
    <div class="min-w-0">
        <div v-if="searchable" class="mb-3"><input v-model="query" type="search" :placeholder="searchPlaceholder" class="w-full rounded-xl border-slate-300 text-sm sm:max-w-sm" @input="search" /></div>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th v-for="column in columns" :key="column.key" class="whitespace-nowrap px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <button type="button" class="inline-flex items-center gap-1" :disabled="column.sortable === false" @click="sort(column)">{{ column.label }}<span v-if="sortKey === column.key">{{ sortDirection === "asc" ? "↑" : "↓" }}</span></button>
                </th><th v-if="$slots.actions" class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-500">Ações</th></tr></thead>
                <tbody class="divide-y divide-slate-100 bg-white"><tr v-for="row in visible" :key="row[rowKey]"><td v-for="column in columns" :key="column.key" class="px-3 py-3 text-slate-700"><slot :name="'cell-' + column.key" :row="row" :value="row[column.key]">{{ row[column.key] }}</slot></td><td v-if="$slots.actions" class="px-3 py-3 text-right"><slot name="actions" :row="row" /></td></tr>
                <tr v-if="!visible.length"><td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="px-4 py-8 text-center text-sm text-slate-500">{{ emptyText }}</td></tr></tbody>
            </table>
        </div>
        <div v-if="pages > 1" class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500"><span>{{ filtered.length }} registros · página {{ Math.min(page, pages) }} de {{ pages }}</span><div class="flex gap-2"><button type="button" class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="page <= 1" @click="page--">Anterior</button><button type="button" class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="page >= pages" @click="page++">Próxima</button></div></div>
    </div>
</template>
