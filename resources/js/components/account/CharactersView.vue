<template>
    <div class="space-y-6">
        <SectionHeader :title="$t('account.characters.title')" :description="$t('account.characters.description')" />

        <Transition
            leave-active-class="transition-opacity duration-slow ease-exit motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-if="crossStatus && store.userCharacters.length"
                data-cross-status
                role="status"
                class="flex flex-wrap items-center gap-3 rounded-ui-md border px-4 py-3 text-sm"
                :class="crossStatus.classes"
            >
                <Icon :icon="crossStatus.icon" size="sm" :class="{ 'motion-safe:animate-spin': crossStatus.busy }" />
                <span class="flex-1 text-default">{{ $t(crossStatus.message) }}</span>
                <Button v-if="crossStatus.retry" size="sm" variant="secondary" @click="store.computeCrossCharacter()">
                    <Icon :icon="RotateCw" size="sm" />
                    {{ $t('account.characters.cross.retry') }}
                </Button>
            </div>
        </Transition>

        <CardGridSkeleton v-if="store.loadingCharacters" :label="$t('account.characters.loading')" />

        <ErrorState
            v-else-if="store.error && !store.userCharacters.length"
            :title="$t('account.characters.unavailable')"
            :message="$t(store.error)"
            @retry="store.fetchUserCharacters()"
        />

        <template v-else-if="store.userCharacters.length">
            <div class="space-y-3">
                <SearchFilter
                    v-model:search="characterSearch"
                    :show-hide-toggle="false"
                    :placeholder="$t('account.characters.search')"
                />
                <div class="flex flex-wrap items-end gap-3">
                    <Select v-model="sortBy" :label="$t('account.characters.sort_by')" :options="sortOptions" />
                    <Button :variant="groupByClass ? 'secondary' : 'ghost'" :aria-pressed="String(groupByClass)" @click="groupByClass = !groupByClass">
                        <Icon :icon="LayoutList" size="sm" />
                        {{ $t('account.characters.group_by_class') }}
                    </Button>
                </div>
            </div>

            <section v-if="favoriteCharacters.length" aria-labelledby="favorites-heading" class="space-y-3">
                <h3 id="favorites-heading" class="flex items-center gap-2 text-lg font-semibold text-default">
                    <Icon :icon="Star" size="sm" class="text-accent [&_svg]:fill-current" />
                    {{ $t('account.characters.favorites') }}
                    <span class="text-sm font-normal tabular-nums text-subtle">{{ favoritesStore.favoriteCount }}/{{ MAX_FAVORITES }}</span>
                </h3>
                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <li v-for="char in favoriteCharacters" :key="characterKey(char)">
                        <MyCharacterCard :character="char" :is-favorite="true" @toggle-favorite="toggleFavorite(char)" />
                    </li>
                </ul>
            </section>

            <section v-if="filteredUserCharacters.length" :aria-label="$t('account.characters.all')" class="space-y-4">
                <h3 v-if="favoritesStore.favoriteCount" class="text-lg font-semibold text-default">{{ $t('account.characters.all') }}</h3>
                <div v-for="group in characterGroups" :key="group.name" class="space-y-3">
                    <component :is="favoritesStore.favoriteCount ? 'h4' : 'h3'" v-if="group.name" class="flex items-baseline gap-2 text-base font-semibold" :style="group.style">
                        {{ group.name }}
                        <span class="text-sm font-normal tabular-nums text-subtle">{{ group.characters.length }}</span>
                    </component>
                    <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <li v-for="char in group.characters" :key="characterKey(char)">
                            <MyCharacterCard :character="char" :favorite-disabled="favoritesStore.isFull" @toggle-favorite="toggleFavorite(char)" />
                        </li>
                    </ul>
                </div>
            </section>

            <EmptyState
                v-if="!favoriteCharacters.length && !filteredUserCharacters.length"
                :icon="SearchX"
                :title="$t('common.no_result')"
                :message="$t('account.characters.no_match')"
            />
        </template>

        <EmptyState
            v-else
            :icon="UserX"
            :title="$t('account.characters.none.title')"
            :message="$t('account.characters.none.message')"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { CircleAlert, CircleCheck, LayoutList, LoaderCircle, RotateCw, SearchX, Star, UserX } from 'lucide-vue-next';
import { useWowColor } from '../../composables/useWowColor';
import { useLocaleFormat } from '../../composables/useLocaleFormat';
import { useTranslate } from '../../composables/useTranslate';
import { useCharacterStore } from '../../stores/character';
import { MAX_FAVORITES, useFavoriteStore } from '../../stores/favorites';
import { classColor } from '../../utils/wowColors';
import CardGridSkeleton from '../CardGridSkeleton.vue';
import MyCharacterCard from '../MyCharacterCard.vue';
import SearchFilter from '../SearchFilter.vue';
import Button from '../ui/Button.vue';
import EmptyState from '../ui/EmptyState.vue';
import ErrorState from '../ui/ErrorState.vue';
import Icon from '../ui/Icon.vue';
import SectionHeader from '../ui/SectionHeader.vue';
import Select from '../ui/Select.vue';

const store = useCharacterStore();
const favoritesStore = useFavoriteStore();
const { readable, safe } = useWowColor();

const characterSearch = ref('');
const sortBy = ref('name');
const groupByClass = ref(false);

const t = useTranslate();
const { compareNames } = useLocaleFormat();

const sortOptions = computed(() => [
    { value: 'name', label: t('account.characters.sort.name') },
    { value: 'level', label: t('account.characters.sort.level') },
    { value: 'class', label: t('account.characters.sort.class') },
    { value: 'realm', label: t('account.characters.sort.realm') },
]);

const COMPARATORS = {
    name: (a, b) => compareNames(a.name, b.name),
    level: (a, b) => b.level - a.level || compareNames(a.name, b.name),
    class: (a, b) => compareNames(a.className, b.className) || compareNames(a.name, b.name),
    realm: (a, b) => compareNames(a.realm, b.realm) || compareNames(a.name, b.name),
};

const CROSS_STATUSES = {
    loading: { icon: LoaderCircle, busy: true, message: 'account.characters.cross.loading', classes: 'border-info/40 bg-info/10 text-info' },
    ready: { icon: CircleCheck, message: 'account.characters.cross.ready', classes: 'border-success/40 bg-success/10 text-success' },
    error: { icon: CircleAlert, retry: true, message: 'account.characters.cross.error', classes: 'border-warning/40 bg-warning/10 text-warning' },
};

const READY_NOTICE_MS = 4000;

// The confirmation only answers a computation the user saw running, then leaves.
const showReadyNotice = ref(false);
let readyNoticeTimer = null;

watch(() => store.crossCharacterStatus, (status, previous) => {
    if (status !== 'ready' || previous !== 'loading') return;
    showReadyNotice.value = true;
    clearTimeout(readyNoticeTimer);
    readyNoticeTimer = setTimeout(() => {
        showReadyNotice.value = false;
    }, READY_NOTICE_MS);
});

onUnmounted(() => clearTimeout(readyNoticeTimer));

const crossStatus = computed(() => {
    const status = store.crossCharacterStatus;
    if (status === 'ready' && !showReadyNotice.value) return null;

    return CROSS_STATUSES[status] ?? null;
});

onMounted(async () => {
    if (!store.userCharacters.length) {
        await store.fetchUserCharacters();
    }
    if (store.isAuthenticated && store.crossCharacterStatus !== 'ready') {
        store.computeCrossCharacter();
    }
    if (store.isAuthenticated) {
        favoritesStore.fetchFavorites();
    }
});

const matchesSearch = (char) => {
    const query = characterSearch.value.toLowerCase().trim();
    if (!query) return true;

    return [char.name, char.realm, char.className, char.raceName, char.faction]
        .some((value) => value.toLowerCase().includes(query));
};

const characterKey = (char) => `${char.realmSlug.toLowerCase()}|${char.name.toLowerCase()}`;

// Favorites keep their own order (order they were starred), not the current sort.
const favoriteCharacters = computed(() => {
    const keys = favoritesStore.favoriteKeys;
    if (!keys.size) return [];
    const byKey = new Map(store.userCharacters.map((char) => [characterKey(char), char]));

    return favoritesStore.favorites
        .map((favorite) => byKey.get(`${favorite.realm_slug}|${favorite.character_name}`))
        .filter((char) => char !== undefined && matchesSearch(char));
});

const toggleFavorite = (char) => favoritesStore.toggleFavorite(char.realmSlug, char.name);

const filteredUserCharacters = computed(() => {
    const keys = favoritesStore.favoriteKeys;

    return store.userCharacters
        .filter((char) => !keys.has(characterKey(char)) && matchesSearch(char))
        .toSorted(COMPARATORS[sortBy.value] ?? COMPARATORS.name);
});

const characterGroups = computed(() => {
    if (!groupByClass.value) {
        return [{ name: '', characters: filteredUserCharacters.value }];
    }

    const byClass = Map.groupBy(filteredUserCharacters.value, (char) => char.className);

    return [...byClass.entries()]
        .toSorted(([a], [b]) => compareNames(a, b))
        .map(([name, characters]) => {
            const color = safe(classColor, characters[0].classId);

            return { name, characters, style: color ? { color: readable(color) } : undefined };
        });
});
</script>
