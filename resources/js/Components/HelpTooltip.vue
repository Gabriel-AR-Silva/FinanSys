<script setup>
import { CircleHelp } from '@lucide/vue';

const props = defineProps({
    label: {
        type: String,
        default: 'Ver explicação',
    },
});

let tooltipSequence = 0;
const tooltipId = `help-tooltip-${++tooltipSequence}`;
</script>

<template>
    <span class="group relative inline-flex shrink-0">
        <button
            type="button"
            class="inline-flex size-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
            :aria-label="props.label"
            :aria-describedby="tooltipId"
        >
            <CircleHelp :size="17" aria-hidden="true" />
        </button>

        <span
            :id="tooltipId"
            role="tooltip"
            class="pointer-events-none absolute right-0 top-full z-40 mt-2 hidden w-[min(18rem,calc(100vw-2.5rem))] rounded-xl bg-slate-950 px-3 py-2.5 text-left text-xs font-normal leading-5 text-white shadow-xl group-hover:block group-focus-within:block"
        >
            <slot />
        </span>
    </span>
</template>
