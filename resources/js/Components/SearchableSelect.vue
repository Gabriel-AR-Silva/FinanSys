<script setup>
import { computed, nextTick, ref, watch } from "vue";

const props = defineProps({
    modelValue: { type: [String, Number], default: "" },
    options: { type: Array, default: () => [] },
    labelKey: { type: String, default: "name" },
    valueKey: { type: String, default: "id" },
    placeholder: { type: String, default: "Digite para buscar..." },
    emptyText: { type: String, default: "Nenhum resultado encontrado." },
    disabled: Boolean,
    clearable: { type: Boolean, default: true },
    maxResults: { type: Number, default: 12 },
});
const emit = defineEmits(["update:modelValue", "select"]);
const query = ref("");
const open = ref(false);
const input = ref(null);

const getLabel = (option) => String(option?.label ?? option?.[props.labelKey] ?? "");
const selected = computed(() => props.options.find((option) => String(option[props.valueKey]) === String(props.modelValue)));
const visible = computed(() => {
    const term = query.value.trim().toLocaleLowerCase("pt-BR");
    const source = term ? props.options.filter((option) => getLabel(option).toLocaleLowerCase("pt-BR").includes(term)) : props.options;
    return source.slice(0, props.maxResults);
});

watch(selected, (option) => {
    if (!open.value) query.value = option ? getLabel(option) : "";
}, { immediate: true });

function focus() {
    query.value = selected.value ? getLabel(selected.value) : "";
    open.value = true;
}
function choose(option) {
    emit("update:modelValue", option[props.valueKey]);
    emit("select", option);
    query.value = getLabel(option);
    open.value = false;
}
function clear() {
    emit("update:modelValue", "");
    emit("select", null);
    query.value = "";
    open.value = true;
    reopen();
}
function clearIfInvalid() {
    setTimeout(() => {
        if (!open.value) return;
        const normalized = query.value.trim().toLocaleLowerCase("pt-BR");
        const exact = props.options.find((option) => getLabel(option).toLocaleLowerCase("pt-BR") === normalized);
        if (exact) choose(exact);
        else query.value = selected.value ? getLabel(selected.value) : "";
        open.value = false;
    }, 120);
}
async function reopen() { await nextTick(); input.value?.focus(); }
</script>

<template>
    <div class="relative">
        <input
            ref="input"
            v-model="query"
            type="text"
            autocomplete="off"
            :disabled="disabled"
            :placeholder="placeholder"
            role="combobox"
            :aria-expanded="open"
            class="block w-full rounded-xl border-slate-300 pr-10 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-slate-100"
            @focus="focus"
            @input="open = true"
            @blur="clearIfInvalid"
            @keydown.esc="open = false"
        />
        <button
            v-if="clearable && modelValue && !disabled"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Limpar seleção"
            @mousedown.prevent
            @click="clear"
        >×</button>
        <div v-if="open" class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
            <button
                v-for="option in visible"
                :key="option[valueKey]"
                type="button"
                class="block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-emerald-50 hover:text-emerald-900"
                @mousedown.prevent
                @click="choose(option)"
            >{{ getLabel(option) }}</button>
            <p v-if="!visible.length" class="px-3 py-3 text-sm text-slate-500">{{ emptyText }}</p>
        </div>
    </div>
</template>
