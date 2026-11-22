<template>
    <div class="flex min-h-0 min-w-0 flex-1">
        <aside class="hidden w-60 shrink-0 flex-col border-r border-default bg-surface lg:sticky lg:top-header lg:flex lg:h-below-header lg:self-start">
            <div class="border-b border-default p-4">
                <Link :href="localizedUrl('/base-de-donnees')" class="text-sm font-semibold text-default hover:underline">{{ $t('database.sections.index') }}</Link>
            </div>
            <nav :aria-label="$t('database.layout.sections')" class="no-scrollbar flex-1 overflow-y-auto py-2">
                <div v-for="section in sections" :key="section.key">
                    <button
                        type="button"
                        :aria-expanded="String(Boolean(expanded[section.key]))"
                        :aria-controls="`${idPrefix}-${section.key}`"
                        :data-active="isSectionActive(section.path) ? '' : undefined"
                        class="flex min-h-11 w-full items-center gap-2.5 border-l-4 px-4 text-left text-sm font-medium transition-colors duration-fast
                            focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent"
                        :class="isSectionActive(section.path) ? 'bg-surface-raised text-default' : 'border-transparent text-muted hover:bg-surface-raised hover:text-default'"
                        :style="isSectionActive(section.path) ? { borderLeftColor: colorOf(section).base } : undefined"
                        @click="onSectionClick(section)"
                    >
                        <span class="size-4.5 shrink-0" :style="{ color: readable(colorOf(section)) }"><CategoryIcon :category="section.icon" /></span>
                        <span class="flex-1 truncate">{{ $t(`database.sections.${section.key}.label`) }}</span>
                        <span v-if="counts[section.countKey]" data-count class="text-xs tabular-nums text-subtle">{{ formatCount(counts[section.countKey]) }}</span>
                        <Icon :icon="ChevronRight" size="sm" class="text-subtle transition-transform duration-fast" :class="{ 'rotate-90': expanded[section.key] }" />
                    </button>

                    <div
                        :id="`${idPrefix}-${section.key}`"
                        :inert="!expanded[section.key]"
                        class="grid transition-[grid-template-rows] duration-base ease-enter motion-reduce:transition-none"
                        :class="expanded[section.key] ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                    >
                        <ul class="overflow-hidden">
                            <template v-if="subCategories[section.key]">
                                <li>
                                    <Link
                                        :href="localizedUrl(section.path)"
                                        :aria-current="isExactActive(section.path) ? 'page' : undefined"
                                        :class="[SUB_LINK, isExactActive(section.path) ? SUB_CURRENT : SUB_IDLE]"
                                    >{{ $t('database.layout.all') }}</Link>
                                </li>
                                <li v-for="sub in namedSubCategories(section)" :key="sub.slug">
                                    <Link
                                        :href="localizedUrl(`${section.path}/${sub.slug}`)"
                                        :aria-current="isExactActive(`${section.path}/${sub.slug}`) ? 'page' : undefined"
                                        :class="[SUB_LINK, isExactActive(`${section.path}/${sub.slug}`) ? SUB_CURRENT : SUB_IDLE]"
                                    >
                                        <span class="truncate">{{ sub.label }}</span>
                                        <span class="ml-2 shrink-0 tabular-nums text-subtle">{{ sub.count }}</span>
                                    </Link>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </nav>
            <div class="border-t border-default p-4">
                <Link :href="localizedUrl('/')" class="flex min-h-11 items-center gap-2 text-sm text-muted hover:text-default">
                    <Icon :icon="ArrowLeft" size="sm" />
                    {{ $t('database.layout.back') }}
                </Link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <nav ref="mobileSections" data-mobile-sections :aria-label="$t('database.layout.sections')" class="relative no-scrollbar flex items-center gap-2 overflow-x-auto border-b border-default bg-surface px-3 py-2 lg:hidden">
                <Link
                    v-for="section in sections"
                    :key="section.path"
                    :href="localizedUrl(section.path)"
                    :aria-current="isSectionActive(section.path) ? 'page' : undefined"
                    class="flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-ui-md border px-3 text-sm font-medium transition-colors duration-fast"
                    :class="isSectionActive(section.path) ? 'bg-surface-raised text-default' : 'border-default text-muted hover:text-default'"
                    :style="isSectionActive(section.path) ? { borderColor: colorOf(section).base } : undefined"
                >
                    <span class="size-4 shrink-0" :style="{ color: readable(colorOf(section)) }"><CategoryIcon :category="section.icon" /></span>{{ $t(`database.sections.${section.key}.label`) }}
                </Link>
            </nav>

            <nav
                v-if="activeMobileSubCategories.length > 0"
                data-mobile-subcategories
                :aria-label="$t('database.layout.categories', { section: $t(`database.sections.${activeMobileSection.key}.label`) })"
                class="relative no-scrollbar flex items-center gap-1 overflow-x-auto border-b border-default px-3 py-1 lg:hidden"
            >
                <Link
                    :href="localizedUrl(activeMobileSection.path)"
                    :aria-current="isExactActive(activeMobileSection.path) ? 'page' : undefined"
                    :class="[PILL, isExactActive(activeMobileSection.path) ? SUB_CURRENT : SUB_IDLE]"
                >{{ $t('database.layout.all') }}</Link>
                <Link
                    v-for="sub in activeMobileSubCategories"
                    :key="sub.slug"
                    :href="localizedUrl(`${activeMobileSection.path}/${sub.slug}`)"
                    :aria-current="isExactActive(`${activeMobileSection.path}/${sub.slug}`) ? 'page' : undefined"
                    :class="[PILL, isExactActive(`${activeMobileSection.path}/${sub.slug}`) ? SUB_CURRENT : SUB_IDLE]"
                >{{ sub.label }}</Link>
            </nav>

            <div class="relative flex-1">
                <Transition
                    enter-active-class="transition-opacity duration-fast motion-reduce:transition-none"
                    leave-active-class="transition-opacity duration-fast motion-reduce:transition-none"
                    enter-from-class="opacity-0"
                    leave-to-class="opacity-0"
                >
                    <div v-if="navigating" class="absolute inset-0 z-20 flex items-start justify-center bg-surface/60 pt-20 sm:pt-28">
                        <div data-navigating role="status" class="flex items-center gap-3 rounded-ui-md border border-default bg-surface-raised px-4 py-2.5 shadow-elevation-1">
                            <Icon :icon="LoaderCircle" size="sm" class="text-accent motion-safe:animate-spin" />
                            <span class="text-sm text-default">{{ $t('common.loading') }}</span>
                        </div>
                    </div>
                </Transition>
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ChevronRight, LoaderCircle } from 'lucide-vue-next';
import CategoryIcon from '../components/CategoryIcon.vue';
import Icon from '../components/ui/Icon.vue';
import { useWowColor } from '../composables/useWowColor';
import { translateCategory } from '../utils/collections';
import { DEFAULT_LOCALE, localizePath } from '../i18n/localizePath';
import { useLocalizedUrl } from '../composables/useLocalizedUrl';
import { dimensionColor } from '../utils/wowColors';

const SUB_LINK = 'flex min-h-9 items-center justify-between py-1.5 pl-11 pr-4 text-sm transition-colors duration-fast';
const SUB_IDLE = 'text-muted hover:text-default';
const SUB_CURRENT = 'font-semibold text-default';
const PILL = 'flex min-h-11 min-w-11 shrink-0 items-center justify-center whitespace-nowrap rounded-ui-sm px-3 text-sm transition-colors duration-fast';

const page = usePage();
const idPrefix = useId();
const { readable } = useWowColor();

// Compared in French, the reference language of the paths: the English pages match too.
const currentPath = computed(() => localizePath(page.url.split('?')[0], DEFAULT_LOCALE));
const counts = computed(() => page.props.counts ?? {});
const { locale, localizedUrl } = useLocalizedUrl();
const subCategories = computed(() => page.props.subCategories ?? {});

// Only a full visit between sections (possibly heavy data) shows the veil: partial reloads
// (pagination, search, a non-empty `only`) are quick and keep the progress bar.
const navigating = ref(false);
const stopStart = router.on('start', (event) => {
    const visit = event.detail.visit;
    const isPartial = Array.isArray(visit.only) && visit.only.length > 0;
    if (localizePath(visit.url.pathname, DEFAULT_LOCALE).startsWith('/base-de-donnees') && ! isPartial) {
        navigating.value = true;
    }
});
const stopFinish = router.on('finish', () => {
    navigating.value = false;
});
onBeforeUnmount(() => {
    stopStart();
    stopFinish();
});

// Kept by the persistent layout across Inertia visits.
const expanded = ref({});

const sections = [
    {
        key: 'mounts',
        dimension: 'mounts',
        path: '/base-de-donnees/montures',
        icon: 'mounts',
        countKey: 'mounts',
        collection: 'mounts',
    },
    {
        key: 'achievements',
        dimension: 'achievements',
        path: '/base-de-donnees/hauts-faits',
        icon: 'achievements',
        countKey: 'achievements',
    },
    {
        key: 'quests',
        dimension: 'quests',
        path: '/base-de-donnees/quetes',
        icon: 'quests',
        countKey: 'quests',
    },
    {
        key: 'pets',
        dimension: 'pets',
        path: '/base-de-donnees/mascottes',
        icon: 'pets',
        countKey: 'pets',
        collection: 'pets',
    },
    {
        key: 'decors',
        dimension: 'decor',
        path: '/base-de-donnees/decorations',
        icon: 'decor',
        countKey: 'decors',
        collection: 'decor',
    },
    {
        key: 'appearances',
        dimension: 'transmog',
        path: '/base-de-donnees/garde-robe',
        icon: 'transmog',
        countKey: 'appearances',
    },
    {
        key: 'professions',
        dimension: 'professions',
        path: '/base-de-donnees/professions',
        icon: 'professions',
        countKey: 'recipes',
    },
];

const colorOf = (section) => dimensionColor(section.dimension);

// Collection categories are curated in English: shown as is in English, translated in
// French, and sorted as read.
function namedSubCategories(section) {
    const subs = subCategories.value[section.key] ?? [];
    if (!section.collection) {
        return subs.map((sub) => ({ ...sub, label: sub.name }));
    }

    return subs
        .map((sub) => ({ ...sub, label: locale.value === 'en' ? sub.name : translateCategory(section.collection, sub.name) }))
        .toSorted((a, b) => a.label.localeCompare(b.label, locale.value));
}

function isSectionActive(path) {
    return currentPath.value === path || currentPath.value.startsWith(path + '/');
}

function isExactActive(path) {
    return currentPath.value === path;
}

function formatCount(n) {
    if (!n) return '';
    return n.toLocaleString(locale.value);
}

function onSectionClick(section) {
    // On the active section, the button only folds or unfolds its list.
    if (isSectionActive(section.path)) {
        expanded.value[section.key] = !expanded.value[section.key];
        return;
    }
    router.visit(localizedUrl(section.path));
}

function expandActiveSection() {
    for (const section of sections) {
        expanded.value[section.key] = isSectionActive(section.path);
    }
}

const activeMobileSection = computed(() => sections.find(s => isSectionActive(s.path)) || null);
const activeMobileSubCategories = computed(() => {
    if (!activeMobileSection.value) return [];
    return namedSubCategories(activeMobileSection.value);
});

const mobileSections = ref(null);

// The bar scrolls sideways: the section being read may sit beyond the edge of a phone screen.
function revealActiveSection() {
    nextTick(() => mobileSections.value?.querySelector('[aria-current="page"]')?.scrollIntoView({ block: 'nearest', inline: 'center' }));
}

watch(currentPath, () => {
    expandActiveSection();
    revealActiveSection();
});

onMounted(revealActiveSection);

expandActiveSection();
</script>
