<script setup>
import { useForm } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { date } from './format';

const props = defineProps({
    notices: { type: Array, required: true },
    warrantyNotices: { type: Array, required: true },
});
const emit = defineEmits(['create', 'open-company', 'busy']);

const pending = computed(() => props.notices.filter(notice => !notice.resolved_at));
const resolved = computed(() => props.notices.filter(notice => notice.resolved_at));
const form = useForm({});

function resolve(notice) {
    form.patch(route('nfc.notices.resolve', { notice: notice.id }), {
        preserveScroll: true,
        onStart: () => emit('busy', true),
        onFinish: () => emit('busy', false),
    });
}

function context(notice) {
    return [notice.company_name, notice.unit_name, notice.plate_label].filter(Boolean).join(' · ');
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4">
        <button type="button" class="nfc-button nfc-primary self-start" :disabled="form.processing" @click="emit('create')">Novo aviso</button>
        <section aria-labelledby="nfc-notices-manual" class="flex min-w-0 flex-col gap-2">
            <h3 id="nfc-notices-manual" class="font-semibold">Pendentes</h3>
            <p v-if="!pending.length" class="nfc-muted text-sm">Nenhum aviso pendente.</p>
            <ul v-else class="flex min-w-0 flex-col gap-2">
                <li v-for="notice in pending" :key="notice.id" class="nfc-panel flex min-w-0 flex-col gap-2 p-3">
                    <span v-if="notice.important" class="nfc-badge nfc-danger self-start">Importante</span>
                    <p class="whitespace-pre-wrap break-words text-sm">{{ notice.text }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <button v-if="notice.company_id" type="button" class="nfc-button" :disabled="form.processing" @click="emit('open-company', notice.company_id)">{{ context(notice) }}</button>
                        <button type="button" class="nfc-button" :disabled="form.processing" @click="resolve(notice)">
                            <Check :size="17" aria-hidden="true" /> Marcar como resolvido
                        </button>
                    </div>
                </li>
            </ul>
        </section>
        <section aria-labelledby="nfc-notices-warranty" class="flex min-w-0 flex-col gap-2">
            <h3 id="nfc-notices-warranty" class="font-semibold">Garantias a vencer</h3>
            <p v-if="!warrantyNotices.length" class="nfc-muted text-sm">Nenhuma garantia vence nos próximos sete dias.</p>
            <ul v-else class="flex min-w-0 flex-col gap-2">
                <li v-for="notice in warrantyNotices" :key="notice.sale_id">
                    <button type="button" class="nfc-panel nfc-row flex w-full min-w-0 flex-wrap items-center justify-between gap-2 p-3 text-left text-sm" :disabled="form.processing" @click="emit('open-company', notice.company_id, notice.sale_id)">
                        <span class="min-w-0 break-words font-medium">{{ notice.company_name }} · {{ notice.unit_name }} · {{ notice.plate_count }} {{ notice.plate_count === 1 ? 'plaquinha' : 'plaquinhas' }}</span>
                        <span class="nfc-badge nfc-warning">Até {{ date(notice.warranty_until) }}</span>
                    </button>
                </li>
            </ul>
        </section>
        <details v-if="resolved.length" class="nfc-panel min-w-0">
            <summary class="cursor-pointer rounded-xl px-3 py-3 font-semibold">Resolvidos · {{ resolved.length }}</summary>
            <ul class="nfc-rule flex flex-col gap-2 border-t p-3">
                <li v-for="notice in resolved" :key="notice.id" class="min-w-0 whitespace-pre-wrap break-words text-sm">{{ notice.text }}</li>
            </ul>
        </details>
    </div>
</template>
