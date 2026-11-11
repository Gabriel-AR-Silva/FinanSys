<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { Download, ImagePlus, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { date, money } from './format';

const props = defineProps({
    manufacturer: { type: Object, required: true },
    nfcRoute: { type: Function, required: true },
});
const emit = defineEmits(['edit', 'destroy', 'busy', 'feedback']);
const uploadForm = useForm({ images: [] });
const selectedImage = ref(null);
const deletingImage = ref(null);
const availableSlots = computed(() => Math.max(0, 10 - props.manufacturer.images.length));
const statistics = computed(() => {
    const s = props.manufacturer.statistics;
    return [
        { label: 'Lotes', value: s.batches },
        { label: 'Plaquinhas fabricadas', value: s.manufactured },
        { label: 'Em fabricação', value: s.in_production },
        { label: 'Unidades vinculadas', value: s.units },
        { label: 'Plaquinhas vendidas', value: s.sold },
        { label: 'Defeituosas', value: s.defective },
        { label: 'Custo dos lotes', value: money(s.production_cost_cents) },
        { label: 'Possível prejuízo', value: money(s.estimated_loss_cents) },
        { label: 'Valor de vendas vinculadas', value: money(s.sales_total_cents) },
        { label: 'Recebido', value: money(s.received_cents) },
        { label: 'A receber', value: money(s.outstanding_cents) },
        { label: 'Vendas com saldo pendente', value: s.pending_sales },
        { label: 'Lotes sem custo informado', value: s.unknown_cost_batches },
    ];
});
const imageUrl = image => props.nfcRoute('nfc.manufacturers.images.show', { manufacturer: props.manufacturer.id, image: image.id });
const imageDownloadUrl = image => imageUrl(image) + '?download=1';
function feedback(type, message) {
    emit('feedback', { type, message });
}
function upload(event) {
    const input = event.target;
    const files = Array.from(input.files || []);
    input.value = '';
    if (!files.length || uploadForm.processing) return;
    if (files.length > availableSlots.value) {
        feedback('error', `Você pode adicionar apenas mais ${availableSlots.value} imagem(ns), até o limite de 10.`);
        return;
    }
    if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
        feedback('error', 'Aceitamos JPG, PNG ou WebP de até 5 MB por imagem.');
        return;
    }
    uploadForm.images = files;
    uploadForm.post(props.nfcRoute('nfc.manufacturers.images.store', { manufacturer: props.manufacturer.id }), {
        preserveScroll: true,
        forceFormData: true,
        onStart: () => emit('busy', true),
        onFinish: () => {
            emit('busy', false);
            uploadForm.reset();
        },
        onSuccess: () => feedback('success', 'Imagens adicionadas à galeria.'),
        onError: errors => feedback('error', Object.values(errors).flat().find(Boolean) || 'Não foi possível enviar as imagens.'),
    });
}
function removeImage(image) {
    if (deletingImage.value || !window.confirm(`Excluir a imagem "${image.name}"?`)) return;
    router.delete(props.nfcRoute('nfc.manufacturers.images.destroy', { manufacturer: props.manufacturer.id, image: image.id }), {
        preserveScroll: true,
        onStart: () => { deletingImage.value = image.id; emit('busy', true); },
        onFinish: () => { deletingImage.value = null; emit('busy', false); },
        onSuccess: () => {
            if (selectedImage.value?.id === image.id) selectedImage.value = null;
            feedback('success', 'Imagem removida.');
        },
        onError: errors => feedback('error', Object.values(errors).flat().find(Boolean) || 'Não foi possível remover a imagem.'),
    });
}
</script>

<template>
    <div class="flex min-w-0 flex-col gap-4">
        <div class="flex flex-wrap gap-2">
            <button type="button" class="nfc-button" @click="emit('edit', 'manufacturer-form', { manufacturerId: manufacturer.id })"><Pencil :size="16" aria-hidden="true" /> Editar fabricante</button>
            <button type="button" class="nfc-button nfc-danger" @click="emit('destroy', 'manufacturer', { manufacturerId: manufacturer.id })"><Trash2 :size="16" aria-hidden="true" /> Excluir fabricante</button>
        </div>
        <dl v-if="manufacturer.phone || manufacturer.notes" class="nfc-panel grid gap-3 p-3 text-sm sm:grid-cols-2">
            <div v-if="manufacturer.phone"><dt class="nfc-muted">Telefone / WhatsApp</dt><dd class="break-words font-medium">{{ manufacturer.phone }}</dd></div>
            <div v-if="manufacturer.notes" class="sm:col-span-2"><dt class="nfc-muted">Observações</dt><dd class="whitespace-pre-wrap break-words">{{ manufacturer.notes }}</dd></div>
        </dl>

        <section class="nfc-panel flex min-w-0 flex-col gap-3 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold">Imagens do fabricante <span class="nfc-muted text-sm font-normal">({{ manufacturer.images.length }}/10)</span></h3>
                <label v-if="availableSlots" class="nfc-button cursor-pointer" :aria-disabled="uploadForm.processing">
                    <ImagePlus :size="16" aria-hidden="true" /> {{ uploadForm.processing ? 'Enviando…' : 'Adicionar imagens' }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" :disabled="uploadForm.processing" @change="upload" />
                </label>
            </div>
            <p class="nfc-muted text-xs">Até 10 imagens por fabricante, nos formatos JPG, PNG ou WebP (máximo de 5 MB cada). As imagens ficam privadas neste módulo.</p>
            <div v-if="manufacturer.images.length" class="grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-3">
                <figure v-for="image in manufacturer.images" :key="image.id" class="nfc-panel min-w-0 overflow-hidden">
                    <button type="button" class="block w-full" :aria-label="'Ampliar ' + image.name" @click="selectedImage = image">
                        <img :src="imageUrl(image)" :alt="image.name" loading="lazy" referrerpolicy="no-referrer" class="aspect-square w-full object-contain" />
                    </button>
                    <figcaption class="flex flex-col gap-2 p-2">
                        <p class="truncate text-xs" :title="image.name">{{ image.name }}</p>
                        <div class="flex items-center justify-between gap-1">
                            <a class="nfc-button nfc-icon-button" :href="imageDownloadUrl(image)" :download="image.name" :aria-label="'Baixar ' + image.name" title="Baixar imagem"><Download :size="16" aria-hidden="true" /></a>
                            <button type="button" class="nfc-button nfc-icon-button nfc-danger" :disabled="deletingImage !== null || uploadForm.processing" :aria-label="'Excluir ' + image.name" title="Excluir imagem" @click="removeImage(image)"><Trash2 :size="16" aria-hidden="true" /></button>
                        </div>
                    </figcaption>
                </figure>
            </div>
            <p v-else class="nfc-muted text-sm">Nenhuma imagem anexada. Adicione fotos dos cartões, plaquinhas ou artes de referência.</p>
            <figure v-if="selectedImage && manufacturer.images.some(image => image.id === selectedImage.id)" class="nfc-panel flex min-w-0 flex-col gap-2 p-3">
                <div class="flex items-center justify-between gap-2">
                    <figcaption class="min-w-0 truncate text-sm font-semibold">Pré-visualização: {{ selectedImage.name }}</figcaption>
                    <button type="button" class="nfc-button" @click="selectedImage = null">Fechar prévia</button>
                </div>
                <img :src="imageUrl(selectedImage)" :alt="selectedImage.name" referrerpolicy="no-referrer" class="max-h-[55dvh] w-full object-contain" />
                <a class="nfc-button self-start" :href="imageDownloadUrl(selectedImage)" :download="selectedImage.name"><Download :size="16" aria-hidden="true" /> Baixar imagem</a>
            </figure>
        </section>

        <section class="flex flex-col gap-2">
            <h3 class="font-semibold">Indicadores e pendências</h3>
            <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <div v-for="item in statistics" :key="item.label" class="nfc-panel min-w-0 p-3">
                    <dt class="nfc-muted text-xs">{{ item.label }}</dt>
                    <dd class="mt-1 break-words font-semibold tabular-nums">{{ item.value }}</dd>
                </div>
            </dl>
            <p class="nfc-muted text-xs">Valores de vendas e pagamentos consideram somente unidades vinculadas aos lotes deste fabricante. Os custos são informativos; não representam contas a pagar ao fabricante.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h3 class="font-semibold">Lotes de fabricação ({{ manufacturer.batches.length }})</h3>
            <ul v-if="manufacturer.batches.length" class="flex flex-col gap-2">
                <li v-for="batch in manufacturer.batches" :key="batch.id" class="nfc-panel flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
                    <div><p class="font-semibold">Lote #{{ batch.id }} · {{ batch.quantity }} plaquinhas</p><p class="nfc-muted">{{ batch.produced_on ? date(batch.produced_on) : 'Sem data' }} · {{ batch.status === 'in_progress' ? 'Em fabricação' : 'Fabricado' }}</p></div>
                    <span>{{ batch.cost_cents == null ? 'Custo pendente' : money(batch.cost_cents) }}</span>
                </li>
            </ul>
            <p v-else class="nfc-muted text-sm">Nenhum lote vinculado. Selecione este fabricante ao cadastrar um lote.</p>
        </section>

        <section class="flex flex-col gap-2">
            <h3 class="font-semibold">Empresas e unidades ({{ manufacturer.linked_units.length }})</h3>
            <ul v-if="manufacturer.linked_units.length" class="flex flex-col gap-2">
                <li v-for="unit in manufacturer.linked_units" :key="unit.company_id + ':' + unit.unit_id" class="nfc-panel p-3 text-sm">
                    <p class="font-semibold">{{ unit.company_name }} · {{ unit.unit_name }}</p>
                    <p class="nfc-muted text-xs">Lote #{{ unit.batch_id }}</p>
                </li>
            </ul>
            <p v-else class="nfc-muted text-sm">Nenhuma unidade vinculada aos lotes deste fabricante.</p>
        </section>
    </div>
</template>
