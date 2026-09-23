<script setup>
defineProps({
    label: { type: String, default: 'Entenda melhor' },
});
</script>

<template>
    <details class="group relative inline-block align-middle">
        <summary class="inline-flex min-h-9 cursor-pointer list-none items-center gap-1 rounded-lg px-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 [&::-webkit-details-marker]:hidden">
            <span aria-hidden="true" class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-current text-xs font-bold">?</span>
            {{ label }}
        </summary>
        <div class="mt-2 max-w-sm rounded-xl border border-slate-200 bg-white p-3 text-sm leading-6 text-slate-700 shadow-sm">
            <slot />
        </div>
    </details>
</template>
