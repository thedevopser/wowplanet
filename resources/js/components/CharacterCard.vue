<template>
    <header
        data-class-rule
        class="rounded-ui-md border border-l-4 border-default bg-surface p-5 sm:p-6"
        :style="classColor ? { borderLeftColor: classColor.base } : undefined"
    >
        <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-start gap-4">
                <img
                    :src="character.avatarUrl"
                    :alt="`Avatar de ${character.name}`"
                    width="80"
                    height="80"
                    class="size-20 shrink-0 rounded-ui-md border border-default bg-surface-raised object-cover"
                >
                <div class="min-w-0 space-y-2">
                    <h1
                        class="font-display text-3xl font-bold leading-tight text-default sm:text-4xl"
                        :style="classColor ? { color: readable(classColor) } : undefined"
                    >
                        {{ character.name }}
                    </h1>
                    <p v-if="character.guild" class="text-base text-muted">&lt;{{ character.guild }}&gt;</p>
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
                        <span>Niveau {{ character.level }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ character.race }}</span>
                        <span aria-hidden="true">·</span>
                        <span data-class-name class="font-semibold" :style="classColor ? { color: readable(classColor) } : undefined">{{ character.class }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ character.realm }}</span>
                        <Badge v-if="factionColor" data-faction tone="faction" :value="character.faction">{{ character.faction }}</Badge>
                        <Badge v-else data-faction>{{ character.faction }}</Badge>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <ScoreBadge v-if="score" :score="score.global" :rank="score.rank" />
                <template v-if="isOwner">
                    <Button :aria-pressed="String(isFavorite)" @click="toggleFavorite">
                        <Icon :icon="Star" :class="{ 'fill-current text-accent': isFavorite }" />
                        Favori
                    </Button>
                    <Button @click="taskStore.openFor(realm, name)">
                        <Icon :icon="ListPlus" />
                        Ajouter une tâche
                    </Button>
                </template>
            </div>
        </div>
    </header>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { ListPlus, Star } from 'lucide-vue-next';
import { useFavoriteStore } from '../stores/favorites';
import { useTaskStore } from '../stores/tasks';
import { useToastStore } from '../stores/toasts';
import { useWowColor } from '../composables/useWowColor';
import { classColor as classColorOf, factionColor as factionColorOf } from '../utils/wowColors';
import Badge from './ui/Badge.vue';
import Button from './ui/Button.vue';
import Icon from './ui/Icon.vue';
import ScoreBadge from './ScoreBadge.vue';

const props = defineProps({
    character: { type: Object, required: true },
    realm: { type: String, required: true },
    name: { type: String, required: true },
    isOwner: { type: Boolean, default: false },
});

const favoriteStore = useFavoriteStore();
const taskStore = useTaskStore();
const toasts = useToastStore();
const { readable, safe } = useWowColor();

const classColor = computed(() => safe(classColorOf, props.character.classId));
const factionColor = computed(() => safe(factionColorOf, props.character.faction));
const score = computed(() => props.character.score ?? null);
const isFavorite = computed(() => favoriteStore.isFavorite(props.realm, props.name));

onMounted(() => {
    if (props.isOwner) {
        favoriteStore.fetchFavorites();
    }
});

async function toggleFavorite() {
    if (!isFavorite.value && favoriteStore.isFull) {
        toasts.show({
            title: 'Trois favoris au maximum',
            description: 'Retirez un favori depuis Mon compte pour épingler celui-ci.',
            tone: 'warning',
            action: { label: 'Gérer mes favoris', href: '/mon-compte' },
        });
        return;
    }

    try {
        await favoriteStore.toggleFavorite(props.realm, props.name);
    } catch {
        toasts.show({ title: 'Le favori n’a pas pu être enregistré', description: 'Réessayez dans un instant.', tone: 'error' });
    }
}
</script>
