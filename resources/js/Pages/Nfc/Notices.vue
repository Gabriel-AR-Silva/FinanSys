<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { date } from './format';

const props = defineProps({
    notices: { type: Array, required: true },
    warrantyNotices: { type: Array, required: true },
});
const emit = defineEmits(['create', 'open-company', 'busy']);
const tab = ref('pending');
const pending = computed(() => props.notices.filter(notice => !notice.resolved_at));
const resolved = computed(() => props.notices.filter(notice => notice.resolved_at));
const tabs = computed(() => [
    { key: 'pending', label: 'Pendentes', count: pending.value.length },
    { key: 'warranty', label: 'Garantias', count: props.warrantyNotices.length },
    { key: 'resolved', label: 'Resolvidos', count: resolved.value.length },
]);
const selectedNotices = computed(() => tab.value === 'resolved' ? resolved.value : pending.value);
const form = useForm({});

function toggle(notice) {
    if (form.processing) return;
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
    <section class="flex min-w-0 flex-col gap-4" aria-label="Quadro de avisos NFC">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <p class="nfc-muted text-sm">Acompanhe pendências e prazos de garantia.</p>
            <button type="button" class="nfc-button nfc-primary" :disabled="form.processing" @click="emit('create')">Novo aviso</button>
        </header>
        <div role="tablist" aria-label="Categorias de avisos" class="nfc-panel flex min-w-0 gap-1 overflow-x-auto p-1">
            <button v-for="item in tabs" :key="item.key" :id="'nfc-tab-' + item.key" type="button" role="tab"
                :aria-selected="tab === item.key" :aria-controls="'nfc-panel-' + item.key"
                class="nfc-button min-w-max flex-1" :aria-pressed="tab === item.key"
                @click="tab = item.key">{{ item.label }} <span class="nfc-badge nfc-neutral tabular-nums">{{ item.count }}</span></button>
        </div>
        <div :id="'nfc-panel-' + tab" role="tabpanel" :aria-labelledby="'nfc-tab-' + tab" class="min-w-0">
            <template v-if="tab === 'warranty'">
                <p v-if="!warrantyNotices.length" class="nfc-panel nfc-muted p-4 text-sm">Nenhuma garantia vence nos próximos sete dias.</p>
                <ul v-else class="flex min-w-0 flex-col gap-2">
                    <li v-for="notice in warrantyNotices" :key="notice.sale_id">
                        <button type="button" class="nfc-panel nfc-row flex w-full min-w-0 flex-wrap items-center justify-between gap-2 p-4 text-left text-sm"
                            @click="emit('open-company', notice.company_id, notice.sale_id)">
                            <span class="min-w-0 break-words font-medium">{{ notice.company_name }} · {{ notice.unit_name }} · {{ notice.plate_count }} plaquinha(s)</span>
                            <span class="nfc-badge nfc-warning">Até {{ date(notice.warranty_until) }}</span>
                        </button>
                    </li>
                </ul>
            </template>
            <template v-else>
                <p v-if="!selectedNotices.length" class="nfc-panel nfc-muted p-4 text-sm">{{ tab === 'pending' ? 'Nenhum aviso pendente.' : 'Ainda não há avisos resolvidos.' }}</p>
                <ul v-else class="flex min-w-0 flex-col gap-2">
                    <li v-for="notice in selectedNotices" :key="notice.id" class="nfc-panel flex min-w-0 items-start gap-3 p-3 sm:p-4">
                        <label class="flex min-h-8 shrink-0 cursor-pointer items-start pt-1">
                            <input class="nfc-check" type="checkbox" :checked="Boolean(notice.resolved_at)"
                                :disabled="form.processing" :aria-label="'Alternar resolução do aviso: ' + notice.text"
                                @change="toggle(notice)" />
                        </label>
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span v-if="notice.important" class="nfc-badge nfc-danger">Importante</span>
                                <span v-if="notice.resolved_at" class="nfc-badge nfc-success">Resolvido</span>
                            </div>
                            <p class="whitespace-pre-wrap break-words text-sm" :class="notice.resolved_at ? 'nfc-muted' : ''">{{ notice.text }}</p>
                            <button v-if="notice.company_id" type="button" class="nfc-button self-start text-left" @click="emit('open-company', notice.company_id)">{{ context(notice) }}</button>
                        </div>
                    </li>
                </ul>
            </template>
        </div>
    </section>
</template>
