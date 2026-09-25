<template>
    <section aria-labelledby="discord-heading" class="rounded-ui-md border border-default bg-surface p-5 sm:p-6">
        <h2 id="discord-heading" class="mb-4 font-display text-2xl font-semibold text-default">Message Discord</h2>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Select v-model="discord.channel" label="Canal" :options="CHANNELS" />
                <fieldset>
                    <legend class="mb-1 text-sm font-medium text-muted">Couleur</legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="c in colorPresets"
                            :key="c.value"
                            type="button"
                            :aria-label="c.name"
                            :aria-pressed="discord.color === c.value ? 'true' : 'false'"
                            class="size-11 rounded-ui-md border border-default transition-transform duration-fast
                                focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            :class="discord.color === c.value ? 'ring-2 ring-accent ring-offset-2 ring-offset-surface' : 'opacity-70 hover:opacity-100'"
                            :style="{ backgroundColor: c.hex }"
                            @click="discord.color = c.value"
                        ></button>
                    </div>
                </fieldset>
            </div>

            <div>
                <label for="discord-title" class="mb-1 block text-sm font-medium text-default">Titre</label>
                <input
                    id="discord-title"
                    v-model="discord.title"
                    type="text"
                    maxlength="256"
                    class="min-h-11 w-full rounded-ui-md border border-strong bg-surface px-3 text-base text-default placeholder:text-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    placeholder="Titre de l'embed"
                >
            </div>

            <div>
                <label for="discord-description" class="mb-1 block text-sm font-medium text-default">Description</label>
                <textarea
                    id="discord-description"
                    v-model="discord.description"
                    rows="4"
                    maxlength="4096"
                    class="resize-y py-2 w-full rounded-ui-md border border-strong bg-surface px-3 text-base text-default placeholder:text-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    placeholder="Contenu du message (supporte le Markdown Discord)"
                ></textarea>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium text-default">Champs (optionnel)</span>
                    <Button size="sm" variant="ghost" @click="addField">
                        + Ajouter un champ
                    </Button>
                </div>
                <div v-for="(field, i) in discord.fields" :key="i" class="mb-2 flex flex-wrap items-center gap-2">
                    <input
                        v-model="field.name"
                        type="text"
                        maxlength="256"
                        :aria-label="`Nom du champ ${i + 1}`"
                        class="min-h-11 min-w-32 flex-1 w-full rounded-ui-md border border-strong bg-surface px-3 text-base text-default placeholder:text-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        placeholder="Nom"
                    >
                    <input
                        v-model="field.value"
                        type="text"
                        maxlength="1024"
                        :aria-label="`Valeur du champ ${i + 1}`"
                        class="min-h-11 min-w-32 flex-1 w-full rounded-ui-md border border-strong bg-surface px-3 text-base text-default placeholder:text-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        placeholder="Valeur"
                    >
                    <label class="flex items-center gap-2 whitespace-nowrap text-sm text-muted">
                        <input v-model="field.inline" type="checkbox" class="size-4 accent-accent">
                        Inline
                    </label>
                    <IconButton :icon="X" :label="`Retirer le champ ${i + 1}`" @click="discord.fields.splice(i, 1)" />
                </div>
            </div>

            <div>
                <label for="discord-footer" class="mb-1 block text-sm font-medium text-default">Footer</label>
                <input
                    id="discord-footer"
                    v-model="discord.footer"
                    type="text"
                    maxlength="2048"
                    class="min-h-11 w-full rounded-ui-md border border-strong bg-surface px-3 text-base text-default placeholder:text-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                    placeholder="Texte affiché en bas de l'embed"
                >
            </div>

            <div
                v-if="discord.title || discord.description"
                data-discord-preview
                aria-label="Aperçu du message"
                role="group"
                class="rounded-ui-md border-l-4 bg-brand-discord-embed p-4 text-brand-discord-text"
                :style="{ borderColor: currentColorHex }"
            >
                <p v-if="discord.title" class="mb-1 text-sm font-semibold">{{ discord.title }}</p>
                <div v-if="discord.description" class="discord-markdown text-sm" v-html="renderedDescription"></div>
                <div v-if="discord.fields.length" class="mt-2 grid gap-1" :class="discord.fields.some(f => f.inline) ? 'grid-cols-3' : 'grid-cols-1'">
                    <div v-for="(field, i) in discord.fields" :key="i" :class="field.inline ? '' : 'col-span-3'">
                        <p class="text-sm font-semibold">{{ field.name }}</p>
                        <p class="text-sm text-brand-discord-muted">{{ field.value }}</p>
                    </div>
                </div>
                <div v-if="discord.footer" class="mt-3 border-t border-brand-discord-muted/30 pt-2">
                    <p class="text-xs text-brand-discord-muted">{{ discord.footer }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button variant="primary" :disabled="discordLoading || !discord.title || !discord.description" @click="sendDiscord">
                    {{ discordLoading ? 'Envoi…' : 'Envoyer' }}
                </Button>
                <span v-if="discordResult !== null" role="status" :class="discordResult ? 'text-success' : 'text-danger'" class="text-sm">
                    {{ discordResult ? 'Envoyé avec succès' : 'Échec de l’envoi' }}
                </span>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import Button from '../ui/Button.vue';
import IconButton from '../ui/IconButton.vue';
import Select from '../ui/Select.vue';

marked.setOptions({
    breaks: true,
    gfm: true,
});

const CHANNELS = [
    { value: 'changelog', label: 'Changelog' },
    { value: 'discussion', label: 'Discussion' },
];

// Embed colours sent to Discord as integers; the hex only paints the swatch and the preview.
const colorPresets = [
    { name: 'Bleu', value: 3447003, hex: '#3498db' },
    { name: 'Vert', value: 3066993, hex: '#2ecc71' },
    { name: 'Rouge', value: 15158332, hex: '#e74c3c' },
    { name: 'Orange', value: 15105570, hex: '#e67e22' },
    { name: 'Violet', value: 10181046, hex: '#9b59b6' },
    { name: 'Or', value: 15844367, hex: '#f1c40f' },
];

const discord = reactive({
    channel: 'changelog',
    title: '',
    description: '',
    color: 3447003,
    fields: [],
    footer: '',
});

const discordLoading = ref(false);
const discordResult = ref(null);

const currentColorHex = computed(() => {
    const preset = colorPresets.find(c => c.value === discord.color);
    return preset ? preset.hex : '#3498db';
});

const renderedDescription = computed(() => {
    if (!discord.description) return '';
    return DOMPurify.sanitize(marked(discord.description));
});

const addField = () => {
    discord.fields.push({ name: '', value: '', inline: false });
};

const sendDiscord = async () => {
    discordLoading.value = true;
    discordResult.value = null;

    try {
        const payload = {
            channel: discord.channel,
            title: discord.title,
            description: discord.description,
            color: discord.color,
        };
        const filledFields = discord.fields.filter(f => f.name && f.value);
        if (filledFields.length) {
            payload.fields = filledFields;
        }
        if (discord.footer) {
            payload.footer = discord.footer;
        }

        const response = await axios.post('/api/admin/discord', payload);
        discordResult.value = response.data.success;

        if (response.data.success) {
            discord.title = '';
            discord.description = '';
            discord.fields = [];
            discord.footer = '';
        }
    } catch {
        discordResult.value = false;
    } finally {
        discordLoading.value = false;
        setTimeout(() => { discordResult.value = null; }, 5000);
    }
};
</script>

<style scoped>
.discord-markdown :deep(h1),
.discord-markdown :deep(h2),
.discord-markdown :deep(h3) {
    color: var(--color-brand-discord-text);
    font-weight: 700;
    margin-top: 0.5rem;
    margin-bottom: 0.25rem;
}
.discord-markdown :deep(h1) { font-size: 1rem; }
.discord-markdown :deep(h2) { font-size: 0.875rem; }
.discord-markdown :deep(h3) { font-size: 0.8rem; }
.discord-markdown :deep(p) {
    margin-bottom: 0.25rem;
}
.discord-markdown :deep(strong) {
    color: var(--color-brand-discord-text);
    font-weight: 700;
}
.discord-markdown :deep(em) {
    font-style: italic;
}
.discord-markdown :deep(a) {
    color: var(--color-brand-discord-link);
    text-decoration: none;
}
.discord-markdown :deep(a:hover) {
    text-decoration: underline;
}
.discord-markdown :deep(code) {
    background: rgba(0, 0, 0, 0.3);
    padding: 0.1rem 0.3rem;
    border-radius: 3px;
    font-size: 0.75rem;
    font-family: 'Consolas', 'Monaco', monospace;
}
.discord-markdown :deep(pre) {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 4px;
    padding: 0.5rem;
    margin: 0.25rem 0;
    overflow-x: auto;
}
.discord-markdown :deep(pre code) {
    background: none;
    padding: 0;
}
.discord-markdown :deep(blockquote) {
    border-left: 3px solid rgba(255, 255, 255, 0.2);
    padding-left: 0.5rem;
    margin: 0.25rem 0;
    color: var(--color-brand-discord-muted);
}
.discord-markdown :deep(ul),
.discord-markdown :deep(ol) {
    padding-left: 1.25rem;
    margin: 0.25rem 0;
}
.discord-markdown :deep(li) {
    margin-bottom: 0.1rem;
}
.discord-markdown :deep(hr) {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin: 0.5rem 0;
}
</style>
