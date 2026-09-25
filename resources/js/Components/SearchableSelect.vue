<script setup>
import { computed, nextTick, ref } from "vue";

const props = defineProps({
    modelValue: { type: [String, Number], default: "" },
    options: { type: Array, default: () => [] },
    labelKey: { type: String, default: "name" },
    valueKey: { type: String, default: "id" },
    placeholder: { type: String, default: "Digite para buscar..." },
    emptyText: { type: String, default: "Nenhum resultado encontrado." },
    disabled: Boolean,
    maxResults: { type: Number, default: 12 },
});
const emit = defineEmits(["update:modelValue", "select"]);
const query = ref("");
const open = ref(false);
const input = ref(null);

const selected = computed(() => props.options.find((option) => String(option[props.valueKey]) === String(props.modelValue)));
const visible = computed(() => {
    const term = query.value.trim().toLocaleLowerCase("pt-BR");
    const source = term ? props.options.filter((option) => String(option[props.labelKey] ?? "").toLocaleLowerCase("pt-BR").includes(term)) : props.options;
    return source.slice(0, props.maxResults);
});
function focus() {
    query.value = selected.value?.[props.labelKey] ?? "";
    open.value = true;
}
function choose(option) {
    emit("update:modelValue", option[props.valueKey]);
    emit("select", option);
    query.value = option[props.labelKey];
    open.value = false;
}
function clearIfInvalid() {
    setTimeout(() => {
        if (!open.value) return;
        const exact = props.options.find((option) => String(option[props.labelKey]).toLocaleLowerCase("pt-BR") === query.value.trim().toLocaleLowerCase("pt-BR"));
        if (exact) choose(exact);
        else query.value = selected.value?.[props.labelKey] ?? "";
        open.value = false;
    }, 120);
}
async function reopen() { await nextTick(); input.value?.focus(); }
</script>

<template>
    <div class="relative">
        <input ref="input" v-model="query" type="text" autocomplete="off" :disabled="disabled" :placeholder="placeholder"
            class="block w-full rounded-md border-slate-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-slate-100"
            @focus="focus" @input="open = true" @blur="clearIfInvalid" @keydown.esc="open = false" />
        <button v-if="modelValue && !disabled" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded px-2 text-slate-400 hover:bg-slate-100" aria-label="Limpar seleção"
            @mousedown.prevent @click="emit('update:modelValue', ''); query = ''; open = true; reopen()">×</button>
        <div v-if="open" class="absolute z-40 mt-1 max-h-60 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
            <button v-for="option in visible" :key="option[valueKey]" type="button"
                class="block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-emerald-50"
                @mousedown.prevent @click="choose(option)">{{ option[labelKey] }}</button>
            <p v-if="!visible.length" class="px-3 py-3 text-sm text-slate-500">{{ emptyText }}</p>
        </div>
    </div>
</template>
