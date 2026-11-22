<script setup>
import { useForm } from '@inertiajs/vue3';
import { BellRing, CheckCheck, Clock3, Plus, ShieldCheck, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { date } from './format';

const props = defineProps({
    permissions: { type: Object, default: () => ({ create: false, update: false, delete: false }) },
    hiddenValues: { type: Boolean, default: false },
    readOnly: { type: Boolean, default: false },
    notices: { type: Array, required: true },
    warrantyNotices: { type: Array, required: true },
    nfcRoute: { type: Function, required: true },
});
const emit = defineEmits(['create', 'open-company', 'request-delete', 'busy', 'feedback']);
const tab = ref('pending');
const pending = computed(() => props.notices.filter(notice => !notice.resolved_at));
const resolved = computed(() => props.notices.filter(notice => notice.resolved_at));
const tabs = computed(() => [
    { key: 'pending', label: 'Pendentes', count: pending.value.length },
    { key: 'warranty', label: 'Garantias', count: props.warrantyNotices.length },
    { key: 'resolved', label: 'Resolvidos', count: resolved.value.length },
]);
const selectedNotices = computed(() => tab.value === 'resolved' ? resolved.value : pending.value);
async function onTabKeydown(event, current) {
    const keys = tabs.value.map(item => item.key);
    const index = keys.indexOf(current);
    let next = null;
    if (event.key === 'ArrowRight') next = keys[(index + 1) % keys.length];
    if (event.key === 'ArrowLeft') next = keys[(index - 1 + keys.length) % keys.length];
    if (event.key === 'Home') next = keys[0];
    if (event.key === 'End') next = keys[keys.length - 1];
    if (next === null) return;
    event.preventDefault();
    tab.value = next;
    await nextTick();
    document.getElementById(`nfc-tab-${next}`)?.focus();
}
const form = useForm({});

function showError(errors, fallback) {
    const first = Object.values(errors ?? {}).flat().find(Boolean);
    emit('feedback', { type: 'error', message: first ? String(first) : fallback });
}

function toggle(notice, event) {
    if (!props.permissions.update || form.processing) return;
    const checkbox = event?.target;
    form.patch(props.nfcRoute('nfc.notices.resolve', { notice: notice.id }), {
        preserveScroll: true,
        onStart: () => emit('busy', true),
        onFinish: () => emit('busy', false),
        onSuccess: () => emit('feedback', { type: 'success', message: notice.resolved_at ? 'Aviso reaberto.' : 'Aviso resolvido.' }),
        onError: errors => {
            if (checkbox) checkbox.checked = Boolean(notice.resolved_at);
            showError(errors, 'Não foi possível atualizar o aviso.');
        },
        onCancel: () => { if (checkbox) checkbox.checked = Boolean(notice.resolved_at); },
    });
}


function context(notice) {
    return [notice.company_name, notice.unit_name, notice.plate_label].filter(Boolean).join(' · ');
}
</script>

<template>
    <section class="flex min-w-0 flex-col gap-3" aria-label="Quadro de avisos NFC">
        <header class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <span class="nfc-notice-heading-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" aria-hidden="true">
                    <BellRing :size="20" />
                </span>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold">Quadro de avisos</h3>
                    <p class="nfc-muted mt-0.5 text-xs leading-5">Acompanhe pendências, garantias e avisos concluídos.</p>
                </div>
            </div>
            <button v-if="permissions.create" type="button" class="nfc-button nfc-primary w-full shrink-0 sm:w-auto" :disabled="form.processing" @click="emit('create')">
                <Plus :size="16" aria-hidden="true" /> Novo aviso
            </button>
        </header>

        <div role="tablist" aria-label="Categorias de avisos" class="nfc-notice-tabs grid min-w-0 grid-cols-3 gap-1 rounded-xl p-1">
            <button v-for="item in tabs" :key="item.key" :id="'nfc-tab-' + item.key" type="button" role="tab"
                :aria-selected="tab === item.key" :aria-controls="'nfc-panel-' + item.key"
                class="nfc-notice-tab flex min-h-11 min-w-0 flex-col items-center justify-center gap-0.5 rounded-lg px-1 py-1.5 text-xs font-semibold sm:flex-row sm:gap-2 sm:text-sm"
                :class="{ 'nfc-notice-tab-active': tab === item.key }"
                :tabindex="tab === item.key ? 0 : -1"
                @keydown="onTabKeydown($event, item.key)" @click="tab = item.key">
                <span class="truncate">{{ item.label }}</span>
                <span class="nfc-badge nfc-neutral shrink-0 px-1.5 py-0.5 tabular-nums">{{ item.count }}</span>
            </button>
        </div>

        <div :id="'nfc-panel-' + tab" role="tabpanel" :aria-labelledby="'nfc-tab-' + tab" tabindex="0" class="min-w-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
            <template v-if="tab === 'warranty'">
                <div v-if="!warrantyNotices.length" class="nfc-notice-empty flex min-h-36 flex-col items-center justify-center gap-2 rounded-xl p-5 text-center">
                    <ShieldCheck :size="26" aria-hidden="true" />
                    <p class="text-sm font-semibold">Nenhuma garantia próxima do vencimento</p>
                    <p class="nfc-muted max-w-sm text-xs leading-5">Não há garantias vencendo nos próximos sete dias.</p>
                </div>
                <ul v-else class="flex min-w-0 flex-col gap-2">
                    <li v-for="notice in warrantyNotices" :key="notice.sale_id" class="min-w-0">
                        <button type="button" class="nfc-notice-item flex w-full min-w-0 flex-col items-start gap-3 rounded-xl p-3 text-left sm:flex-row sm:items-center sm:justify-between sm:p-4"
                            @click="emit('open-company', notice.company_id, notice.sale_id)">
                            <span class="flex min-w-0 items-start gap-3">
                                <span class="nfc-notice-warranty-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" aria-hidden="true"><Clock3 :size="19" /></span>
                                <span class="min-w-0">
                                    <span class="block break-words text-sm font-semibold">{{ notice.company_name }}</span>
                                    <span class="nfc-muted mt-1 block break-words text-xs">{{ notice.unit_name }} · {{ notice.plate_count }} plaquinha(s)</span>
                                </span>
                            </span>
                            <span class="nfc-badge nfc-warning shrink-0">Até {{ date(notice.warranty_until) }}</span>
                        </button>
                    </li>
                </ul>
            </template>
            <template v-else>
                <div v-if="!selectedNotices.length" class="nfc-notice-empty flex min-h-36 flex-col items-center justify-center gap-2 rounded-xl p-5 text-center">
                    <CheckCheck :size="26" aria-hidden="true" />
                    <p class="text-sm font-semibold">{{ tab === 'pending' ? 'Tudo em dia por aqui' : 'Nenhum aviso concluído' }}</p>
                    <p class="nfc-muted max-w-sm text-xs leading-5">{{ tab === 'pending' ? 'Não há avisos pendentes no momento.' : 'Os avisos resolvidos aparecerão aqui.' }}</p>
                </div>
                <ul v-else class="flex min-w-0 flex-col gap-2">
                    <li v-for="notice in selectedNotices" :key="notice.id" class="nfc-notice-item flex min-w-0 items-start gap-2.5 rounded-xl p-3 sm:gap-3 sm:p-4"
                        :class="{ 'nfc-notice-important': notice.important && !notice.resolved_at, 'nfc-notice-resolved': notice.resolved_at }">
                        <label class="flex min-h-11 min-w-8 shrink-0 cursor-pointer items-start justify-center pt-2">
                            <input class="nfc-check h-5 w-5" type="checkbox" :checked="Boolean(notice.resolved_at)"
                                :disabled="!permissions.update || form.processing"
                                :aria-label="(notice.resolved_at ? 'Reabrir aviso: ' : 'Marcar aviso como resolvido: ') + notice.text"
                                @change="toggle(notice, $event)" />
                        </label>
                        <div class="flex min-w-0 flex-1 flex-col gap-2.5">
                            <div class="flex min-w-0 items-start justify-between gap-2">
                                <p class="min-w-0 flex-1 whitespace-pre-wrap break-words text-sm font-medium leading-6" :class="{ 'nfc-muted': notice.resolved_at }">{{ notice.text }}</p>
                                <button v-if="permissions.delete" type="button" class="nfc-button nfc-icon-button nfc-danger shrink-0"
                                    :disabled="form.processing" :aria-label="'Excluir aviso: ' + notice.text" title="Excluir aviso"
                                    @click="emit('request-delete', notice)">
                                    <Trash2 :size="16" aria-hidden="true" />
                                </button>
                            </div>
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <span v-if="notice.important" class="nfc-badge nfc-danger">Importante</span>
                                <span v-if="notice.resolved_at" class="nfc-badge nfc-success">Resolvido</span>
                                <span v-else-if="!notice.important" class="nfc-badge nfc-neutral">Pendente</span>
                            </div>
                            <button v-if="notice.company_id" type="button" class="nfc-notice-company min-h-11 self-start break-words rounded-lg px-2.5 py-2 text-left text-xs font-semibold"
                                @click="emit('open-company', notice.company_id)">{{ context(notice) }}</button>
                        </div>
                    </li>
                </ul>
            </template>
        </div>
    </section>
</template>
