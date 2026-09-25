<template>
    <div class="space-y-6">
        <SectionHeader title="Mes classes" :description="`Répartition de vos ${totalCharacters} personnages par classe`" />

        <CardGridSkeleton v-if="store.loadingCharacters" label="Chargement de vos classes" />

        <ErrorState
            v-else-if="store.error && !store.userCharacters.length"
            title="Classes indisponibles"
            :message="store.error"
            @retry="store.fetchUserCharacters()"
        />

        <template v-else-if="classStats.length">
            <ol class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
                <li v-for="cls in podiumClasses" :key="cls.classId" :class="PODIUM_PLACES[cls.rank].order">
                    <button
                        type="button"
                        data-class
                        :data-rank="cls.rank"
                        :aria-expanded="String(selectedClassId === cls.classId)"
                        :aria-controls="detailId"
                        class="flex w-full flex-col items-center gap-2 rounded-ui-md border border-t-4 bg-surface px-4 text-center
                            transition-shadow duration-fast ease-enter hover:shadow-elevation-1
                            focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                        :class="[PODIUM_PLACES[cls.rank].padding, selectedClassId === cls.classId ? 'border-strong bg-surface-raised' : 'border-default']"
                        :style="cls.color ? { borderTopColor: cls.color.base } : undefined"
                        @click="toggleClass(cls.classId)"
                    >
                        <span class="text-xs font-semibold text-subtle">{{ PODIUM_PLACES[cls.rank].label }}</span>
                        <ClassEmblem :cls="cls" :size="cls.rank === 1 ? 'size-16' : 'size-12'" />
                        <span data-class-name class="text-base font-semibold" :style="nameStyle(cls)">{{ cls.className }}</span>
                        <span class="text-sm text-muted">
                            <span data-count class="text-2xl font-bold tabular-nums text-default">{{ cls.count }}</span>
                            personnage{{ cls.count > 1 ? 's' : '' }}
                        </span>
                        <Icon :icon="selectedClassId === cls.classId ? ChevronUp : ChevronDown" size="sm" class="text-subtle" />
                    </button>
                </li>
            </ol>

            <section v-if="otherClasses.length" data-other-classes aria-labelledby="other-classes-heading" class="space-y-3">
                <h3 id="other-classes-heading" class="text-lg font-semibold text-default">Autres classes</h3>
                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <li v-for="cls in otherClasses" :key="cls.classId">
                        <button
                            type="button"
                            data-class
                            :data-rank="cls.rank"
                            :aria-expanded="String(selectedClassId === cls.classId)"
                            :aria-controls="detailId"
                            class="flex min-h-11 w-full items-center gap-3 rounded-ui-md border border-l-4 bg-surface p-3 text-left
                                transition-shadow duration-fast ease-enter hover:shadow-elevation-1
                                focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            :class="selectedClassId === cls.classId ? 'border-strong bg-surface-raised' : 'border-default'"
                            :style="cls.color ? { borderLeftColor: cls.color.base } : undefined"
                            @click="toggleClass(cls.classId)"
                        >
                            <ClassEmblem :cls="cls" size="size-10" />
                            <span class="min-w-0 flex-1">
                                <span data-class-name class="block truncate text-sm font-semibold" :style="nameStyle(cls)">{{ cls.className }}</span>
                                <span class="text-sm text-muted"><span data-count class="font-bold tabular-nums text-default">{{ cls.count }}</span> personnage{{ cls.count > 1 ? 's' : '' }}</span>
                            </span>
                            <span class="text-xs tabular-nums text-subtle">#{{ cls.rank }}</span>
                        </button>
                    </li>
                </ul>
            </section>

            <div :id="detailId">
                <Transition
                    enter-active-class="transition duration-base ease-enter motion-reduce:transition-none"
                    enter-from-class="opacity-0 -translate-y-2"
                    leave-active-class="transition duration-fast ease-exit motion-reduce:transition-none"
                    leave-to-class="opacity-0"
                >
                    <section v-if="selectedClass" data-class-detail :aria-labelledby="`${detailId}-heading`" class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 :id="`${detailId}-heading`" class="text-lg font-semibold">
                                <span :style="nameStyle(selectedClass)">{{ selectedClass.className }}</span>
                                <span class="text-muted"> · {{ selectedCharacters.length }} personnage{{ selectedCharacters.length > 1 ? 's' : '' }}</span>
                            </h3>
                            <IconButton :icon="X" label="Fermer le détail de la classe" @click="selectedClassId = null" />
                        </div>
                        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <Card v-for="char in selectedCharacters" :key="`${char.realmSlug}-${char.name}`" as="li" variant="interactive" class="flex items-center gap-3 p-3">
                                <img
                                    v-if="char.avatarUrl"
                                    :src="char.avatarUrl"
                                    :alt="`Avatar de ${char.name}`"
                                    width="40"
                                    height="40"
                                    loading="lazy"
                                    class="size-10 shrink-0 rounded-ui-md border border-default bg-surface-raised object-cover"
                                >
                                <span v-else aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-ui-md border border-default bg-surface-raised font-bold" :style="nameStyle(selectedClass)">
                                    {{ char.name.charAt(0) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <Link
                                        :href="`/character/${char.realmSlug}/${char.name.toLowerCase()}`"
                                        class="block truncate text-sm font-semibold hover:underline focus-visible:outline-none after:absolute after:inset-0"
                                        :style="nameStyle(selectedClass)"
                                    >{{ char.name }}</Link>
                                    <p class="truncate text-xs text-muted">Niveau {{ char.level }} · {{ char.raceName }} · {{ char.realm }}</p>
                                </div>
                            </Card>
                        </ul>
                    </section>
                </Transition>
            </div>
        </template>

        <EmptyState
            v-else
            :icon="UserX"
            title="Aucun personnage sur ce compte"
            message="Battle.net ne renvoie que les personnages de niveau 10 et plus, et un personnage récent peut mettre quelques minutes à apparaître."
        />
    </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onMounted, ref, useId } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, UserX, X } from 'lucide-vue-next';
import { useWowColor } from '../../composables/useWowColor';
import { useCharacterStore } from '../../stores/character';
import { classColor } from '../../utils/wowColors';
import CardGridSkeleton from '../CardGridSkeleton.vue';
import Card from '../ui/Card.vue';
import EmptyState from '../ui/EmptyState.vue';
import ErrorState from '../ui/ErrorState.vue';
import Icon from '../ui/Icon.vue';
import IconButton from '../ui/IconButton.vue';
import SectionHeader from '../ui/SectionHeader.vue';

// The DOM keeps the ranking order for screen readers; only the visual podium puts the first in the middle.
const PODIUM_PLACES = {
    1: { label: '1re place', order: 'sm:order-2', padding: 'py-6 sm:py-8' },
    2: { label: '2e place', order: 'sm:order-1', padding: 'py-5' },
    3: { label: '3e place', order: 'sm:order-3', padding: 'py-4' },
};
const PODIUM_SIZE = 3;

const store = useCharacterStore();
const { readable, safe } = useWowColor();
const selectedClassId = ref(null);
const detailId = useId();

const nameStyle = (cls) => (cls.color ? { color: readable(cls.color) } : undefined);

const ClassEmblem = defineComponent({
    props: { cls: { type: Object, required: true }, size: { type: String, required: true } },
    setup(props) {
        return () => (props.cls.iconUrl
            ? h('img', { src: props.cls.iconUrl, alt: '', class: `${props.size} shrink-0 rounded-ui-md border border-default object-cover` })
            : h('span', {
                'aria-hidden': 'true',
                class: `${props.size} flex shrink-0 items-center justify-center rounded-ui-md border border-default bg-surface-raised text-lg font-bold`,
                style: nameStyle(props.cls),
            }, props.cls.className.charAt(0)));
    },
});

onMounted(() => {
    store.fetchClassIcons();
    if (!store.userCharacters.length) {
        store.fetchUserCharacters();
    }
});

const classStats = computed(() => {
    const byClass = Map.groupBy(store.userCharacters, (char) => char.classId);

    return [...byClass.entries()]
        .map(([classId, characters]) => ({
            classId,
            className: characters[0].className,
            count: characters.length,
            color: safe(classColor, classId),
            iconUrl: store.classIcons[classId] || '',
        }))
        .toSorted((a, b) => b.count - a.count || a.className.localeCompare(b.className, 'fr'))
        .map((cls, index) => ({ ...cls, rank: index + 1 }));
});

const podiumClasses = computed(() => classStats.value.slice(0, PODIUM_SIZE));
const otherClasses = computed(() => classStats.value.slice(PODIUM_SIZE));
const totalCharacters = computed(() => store.userCharacters.length);

const selectedClass = computed(() => classStats.value.find((cls) => cls.classId === selectedClassId.value) ?? null);

const selectedCharacters = computed(() => store.userCharacters
    .filter((char) => char.classId === selectedClassId.value)
    .toSorted((a, b) => b.level - a.level || a.name.localeCompare(b.name, 'fr')));

function toggleClass(classId) {
    selectedClassId.value = selectedClassId.value === classId ? null : classId;
    if (selectedClassId.value === null) return;

    nextTick(() => document.getElementById(detailId)?.scrollIntoView({ block: 'nearest' }));
}
</script>
