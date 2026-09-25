<template>
    <Card
        variant="interactive"
        data-class-rule
        class="flex h-full flex-col gap-3 border-l-4 p-4"
        :class="{ 'bg-surface-raised': isFavorite }"
        :style="color ? { borderLeftColor: color.base } : undefined"
    >
        <div class="flex items-start gap-3">
            <img
                v-if="character.avatarUrl"
                :src="character.avatarUrl"
                :alt="`Avatar de ${character.name}`"
                width="48"
                height="48"
                loading="lazy"
                class="size-12 shrink-0 rounded-ui-md border border-default bg-surface-raised object-cover"
            >
            <span
                v-else
                aria-hidden="true"
                class="flex size-12 shrink-0 items-center justify-center rounded-ui-md border border-default bg-surface-raised text-lg font-bold"
                :style="nameStyle"
            >{{ character.name.charAt(0) }}</span>
            <div class="min-w-0 flex-1">
                <Link
                    :href="`/character/${character.realmSlug}/${character.name.toLowerCase()}`"
                    data-name
                    class="block truncate text-base font-semibold text-default hover:underline focus-visible:outline-none after:absolute after:inset-0"
                    :style="nameStyle"
                >{{ character.name }}</Link>
                <p class="truncate text-sm text-muted">{{ character.realm }}</p>
            </div>
            <IconButton
                :icon="Star"
                :label="ariaLabel"
                :aria-pressed="String(isFavorite)"
                :title="favoriteDisabled ? `${maxFavorites} favoris maximum` : ariaLabel"
                :disabled="favoriteDisabled"
                :class="['relative z-10 -mr-2 -mt-2', { '[&_svg]:fill-current text-accent': isFavorite }]"
                @click="$emit('toggle-favorite')"
            />
        </div>
        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
            <span class="tabular-nums">Niveau {{ character.level }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ character.raceName }}</span>
            <span aria-hidden="true">·</span>
            <span class="font-medium" :style="nameStyle">{{ character.className }}</span>
        </p>
        <div>
            <Badge v-if="hasFactionColor" data-faction tone="faction" :value="character.faction">{{ character.faction }}</Badge>
            <Badge v-else data-faction>{{ character.faction }}</Badge>
        </div>
    </Card>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Star } from 'lucide-vue-next';
import { useWowColor } from '../composables/useWowColor';
import { MAX_FAVORITES } from '../stores/favorites';
import { classColor, factionColor } from '../utils/wowColors';
import Badge from './ui/Badge.vue';
import Card from './ui/Card.vue';
import IconButton from './ui/IconButton.vue';

const props = defineProps({
    character: { type: Object, required: true },
    isFavorite: { type: Boolean, default: false },
    favoriteDisabled: { type: Boolean, default: false },
});

defineEmits(['toggle-favorite']);

const maxFavorites = MAX_FAVORITES;
const { readable, safe } = useWowColor();

const color = computed(() => safe(classColor, props.character.classId));
const nameStyle = computed(() => (color.value ? { color: readable(color.value) } : undefined));
const hasFactionColor = computed(() => safe(factionColor, props.character.faction) !== null);

const ariaLabel = computed(() => (props.isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'));
</script>
