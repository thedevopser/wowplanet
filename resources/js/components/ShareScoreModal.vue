<template>
    <Dialog :open="show" size="wide" :title="variant === 'account' ? 'Partager le score du compte' : 'Partager ce score'" @update:open="(open) => !open && $emit('close')">
        <div class="flex justify-center overflow-auto rounded-ui-md bg-surface-raised p-2 sm:p-4">
            <canvas ref="canvas" role="img" :aria-label="cardLabel" class="h-auto max-w-full rounded-ui-sm" />
        </div>
        <template #footer>
            <Button @click="downloadImage">
                <Icon :icon="Download" size="sm" />
                Télécharger
            </Button>
            <Button variant="primary" @click="copyImage">
                <Icon :icon="copied ? Check : Copy" size="sm" />
                {{ copied ? 'Image copiée' : 'Copier l’image' }}
            </Button>
        </template>
    </Dialog>
</template>

<script setup>
import { computed, ref, useTemplateRef, watch } from 'vue';
import { Check, Copy, Download } from 'lucide-vue-next';
import { renderScoreCard } from '../utils/scoreCardRenderer';
import { formatScore } from '../utils/formatScore';
import Button from './ui/Button.vue';
import Dialog from './ui/Dialog.vue';
import Icon from './ui/Icon.vue';

const COPIED_FEEDBACK_MS = 2500;

const props = defineProps({
    show: Boolean,
    variant: { type: String, default: 'personal' },
    scoreData: { type: Object, default: () => ({}) },
});

defineEmits(['close']);

const canvas = useTemplateRef('canvas');
const copied = ref(false);

const cardLabel = computed(() => `Carte de score à partager : ${formatScore(props.scoreData.globalScore ?? 0)} sur 100, rang ${props.scoreData.rank ?? ''}`);

// The canvas only exists once the dialog is mounted in its portal: draw when it appears.
watch(canvas, (target) => {
    if (!target) {
        return;
    }
    const rendered = renderScoreCard(props.scoreData);
    target.width = rendered.width;
    target.height = rendered.height;
    target.getContext('2d')?.drawImage(rendered, 0, 0);
});

function downloadImage() {
    if (!canvas.value) {
        return;
    }
    const link = document.createElement('a');
    link.href = canvas.value.toDataURL('image/png');
    link.download = `wowplanet-score-${props.variant}.png`;
    link.click();
}

// Browsers without image clipboard fall back to a download.
async function copyImage() {
    if (!canvas.value) {
        return;
    }
    try {
        const blob = await new Promise((resolve) => canvas.value.toBlob(resolve, 'image/png'));
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, COPIED_FEEDBACK_MS);
    } catch {
        downloadImage();
    }
}
</script>
