<script setup>
import { useForm } from '@inertiajs/vue3';
import { Download, ImagePlus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    permissions: { type: Object, required: true },
    companyId: { type: Number, required: true },
    unit: { type: Object, required: true },
    nfcRoute: { type: Function, required: true },
});
const emit = defineEmits(['request-delete-image', 'busy', 'feedback']);
const uploadForm = useForm({ images: [] });
const selectedImage = ref(null);
const availableSlots = computed(() => Math.max(0, 10 - props.unit.images.length));
const imageUrl = image => props.nfcRoute('nfc.units.images.show', { company: props.companyId, unit: props.unit.id, image: image.id });
const imageDownloadUrl = image => imageUrl(image) + '?download=1';
function feedback(type, message) {
    emit('feedback', { type, message });
}
function upload(event) {
    const input = event.target;
    const files = Array.from(input.files || []);
    input.value = '';
    if (!props.permissions.create || !files.length || uploadForm.processing) return;
    if (files.length > availableSlots.value) {
        feedback('error', `Você pode adicionar apenas mais ${availableSlots.value} imagem(ns), até o limite de 10.`);
        return;
    }
    if (files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
        feedback('error', 'Aceitamos JPG, PNG ou WebP de até 5 MB por imagem.');
        return;
    }
    uploadForm.images = files;
    uploadForm.post(props.nfcRoute('nfc.units.images.store', { company: props.companyId, unit: props.unit.id }), {
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

</script>

<template>
        <section class="nfc-panel flex min-w-0 flex-col gap-3 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold">Imagens da unidade <span class="nfc-muted text-sm font-normal">({{ unit.images.length }}/10)</span></h3>
                <label v-if="permissions.create && availableSlots" class="nfc-button cursor-pointer" :aria-disabled="uploadForm.processing">
                    <ImagePlus :size="16" aria-hidden="true" /> {{ uploadForm.processing ? 'Enviando…' : 'Adicionar imagens' }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" :disabled="uploadForm.processing" @change="upload" />
                </label>
            </div>
            <p class="nfc-muted text-xs">Até 10 imagens por unidade, nos formatos JPG, PNG ou WebP (máximo de 5 MB cada). As imagens ficam privadas neste módulo.</p>
            <div v-if="unit.images.length" class="grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-3">
                <figure v-for="image in unit.images" :key="image.id" class="nfc-panel min-w-0 overflow-hidden">
                    <button type="button" class="block w-full" :aria-label="'Ampliar ' + image.name" @click="selectedImage = image">
                        <img :src="imageUrl(image)" :alt="image.name" loading="lazy" referrerpolicy="no-referrer" class="aspect-square w-full object-contain" />
                    </button>
                    <figcaption class="flex flex-col gap-2 p-2">
                        <p class="truncate text-xs" :title="image.name">{{ image.name }}</p>
                        <div class="flex items-center justify-between gap-1">
                            <a class="nfc-button nfc-icon-button" :href="imageDownloadUrl(image)" :download="image.name" :aria-label="'Baixar ' + image.name" title="Baixar imagem"><Download :size="16" aria-hidden="true" /></a>
                            <button v-if="permissions.delete" type="button" class="nfc-button nfc-icon-button nfc-danger"  :disabled="uploadForm.processing" :aria-label="'Excluir ' + image.name" title="Excluir imagem" @click="emit('request-delete-image', image)"><Trash2 :size="16" aria-hidden="true" /></button>
                        </div>
                    </figcaption>
                </figure>
            </div>
            <p v-else class="nfc-muted text-sm">Nenhuma imagem anexada. Adicione fotos dos cartões, plaquinhas ou artes de referência.</p>
            <figure v-if="selectedImage && unit.images.some(image => image.id === selectedImage.id)" class="nfc-panel flex min-w-0 flex-col gap-2 p-3">
                <div class="flex items-center justify-between gap-2">
                    <figcaption class="min-w-0 truncate text-sm font-semibold">Pré-visualização: {{ selectedImage.name }}</figcaption>
                    <button type="button" class="nfc-button" @click="selectedImage = null">Fechar prévia</button>
                </div>
                <img :src="imageUrl(selectedImage)" :alt="selectedImage.name" referrerpolicy="no-referrer" class="max-h-[55dvh] w-full object-contain" />
                <a class="nfc-button self-start" :href="imageDownloadUrl(selectedImage)" :download="selectedImage.name"><Download :size="16" aria-hidden="true" /> Baixar imagem</a>
            </figure>
        </section>

</template>
