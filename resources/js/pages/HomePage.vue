<template>
    <div data-testid="homepage-root" class="space-y-12 py-8 sm:space-y-16 sm:py-12">
        <Head>
            <title>{{ meta.title }}</title>
            <meta name="description" :content="meta.description">
            <link rel="canonical" :href="meta.canonicalUrl">
            <meta property="og:type" :content="meta.ogType">
            <meta property="og:title" :content="meta.ogTitle">
            <meta property="og:description" :content="meta.ogDescription">
            <meta property="og:image" :content="meta.ogImage">
            <meta property="og:url" :content="meta.ogUrl">
            <meta property="og:site_name" content="WowPlanet">
            <meta property="og:locale" :content="$t('locale.og')">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" :content="meta.ogTitle">
            <meta name="twitter:description" :content="meta.ogDescription">
            <meta name="twitter:image" :content="meta.ogImage">
        </Head>

        <section class="grid gap-8 lg:grid-cols-[3fr_2fr] lg:items-center">
            <div class="space-y-6">
                <p class="flex items-center gap-3">
                    <img src="/images/logo.png" alt="" width="40" height="40" class="size-10 rounded-ui-md border border-default object-cover">
                    <span class="font-display text-lg font-semibold text-default">WowPlanet</span>
                </p>
                <h1 class="font-display text-4xl font-bold leading-tight text-default sm:text-5xl">{{ $t('home.title') }}</h1>
                <p class="max-w-prose text-lg leading-relaxed text-muted">
                    {{ $t('home.intro') }}
                </p>
                <div data-hero-actions class="flex flex-wrap gap-3">
                    <Button v-if="isAuthenticated" :href="localizedUrl('/mon-compte')" variant="primary">
                        <Icon :icon="UserRound" size="sm" />
                        {{ $t('nav.account') }}
                    </Button>
                    <Button v-else :href="loginUrl(locale)" external variant="primary">
                        <Icon :icon="LogIn" size="sm" />
                        {{ $t('nav.login') }}
                    </Button>
                    <Button :href="localizedUrl('/base-de-donnees')">
                        <Icon :icon="Library" size="sm" />
                        {{ $t('home.explore_database') }}
                    </Button>
                </div>
                <p v-if="!isAuthenticated" class="text-sm text-subtle">{{ $t('home.login_safety') }}</p>
            </div>

            <dl class="grid grid-cols-2 gap-3">
                <div
                    v-for="item in TRACKED"
                    :key="item.countKey"
                    data-tracked
                    class="rounded-ui-md border border-l-4 border-default bg-surface p-4"
                    :style="{ borderLeftColor: dimensionColor(item.dimension).base }"
                >
                    <dt class="text-sm text-muted">{{ $t(`database.sections.${item.countKey}.label`) }}</dt>
                    <dd class="text-2xl font-bold tabular-nums text-default">{{ formatCount(counts[item.countKey]) }}</dd>
                    <dd class="text-xs text-subtle">{{ $t(`home.tracked.${item.countKey}`) }}</dd>
                </div>
            </dl>
        </section>

        <section data-explore aria-labelledby="explore-heading" class="space-y-4">
            <h2 id="explore-heading" class="font-display text-2xl font-semibold text-default">{{ $t('home.explore_database') }}</h2>
            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card
                    v-for="section in SECTIONS"
                    :key="section.path"
                    as="li"
                    variant="interactive"
                    class="flex items-center gap-3 border-t-4 p-4"
                    :style="{ borderTopColor: dimensionColor(section.dimension).base }"
                >
                    <span class="size-9 shrink-0 rounded-ui-sm border border-default bg-surface-raised p-2" :style="{ color: readable(dimensionColor(section.dimension)) }">
                        <CategoryIcon :category="section.icon" />
                    </span>
                    <div class="min-w-0">
                        <Link :href="localizedUrl(section.path)" class="font-semibold text-default hover:underline focus-visible:outline-none after:absolute after:inset-0">{{ $t(`database.sections.${section.key}.label`) }}</Link>
                        <p class="text-sm text-muted">{{ $t(`database.sections.${section.key}.description`) }}</p>
                    </div>
                </Card>
                <Card as="li" variant="interactive" class="flex items-center gap-3 border-t-4 border-t-accent p-4">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-ui-sm border border-default bg-surface-raised text-accent">
                        <Icon :icon="Swords" size="sm" />
                    </span>
                    <div class="min-w-0">
                        <Link :href="localizedUrl('/classements-pvp')" class="font-semibold text-default hover:underline focus-visible:outline-none after:absolute after:inset-0">{{ $t('nav.pvp') }}</Link>
                        <p class="text-sm text-muted">{{ $t('home.pvp_detail') }}</p>
                    </div>
                </Card>
            </ul>
        </section>

        <DiscordInvite />

        <p class="text-center text-sm text-muted">
            {{ $t('home.footer_data') }}
            <br>{{ $t('home.footer_wowhead') }}
        </p>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Library, LogIn, Swords, UserRound } from 'lucide-vue-next';
import CategoryIcon from '../components/CategoryIcon.vue';
import DiscordInvite from '../components/DiscordInvite.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';
import Icon from '../components/ui/Icon.vue';
import { useWowColor } from '../composables/useWowColor';
import { loginUrl } from '../utils/auth';
import { dimensionColor } from '../utils/wowColors';
import { useActiveLocale } from '../composables/useActiveLocale';
import { useLocalizedUrl } from '../composables/useLocalizedUrl';

const TRACKED = [
    { countKey: 'quests', dimension: 'quests' },
    { countKey: 'achievements', dimension: 'achievements' },
    { countKey: 'mounts', dimension: 'mounts' },
    { countKey: 'pets', dimension: 'pets' },
];

const SECTIONS = [
    { key: 'mounts', path: '/base-de-donnees/montures', icon: 'mounts', dimension: 'mounts' },
    { key: 'achievements', path: '/base-de-donnees/hauts-faits', icon: 'achievements', dimension: 'achievements' },
    { key: 'quests', path: '/base-de-donnees/quetes', icon: 'quests', dimension: 'quests' },
    { key: 'pets', path: '/base-de-donnees/mascottes', icon: 'pets', dimension: 'pets' },
    { key: 'decors', path: '/base-de-donnees/decorations', icon: 'decor', dimension: 'decor' },
    { key: 'appearances', path: '/base-de-donnees/garde-robe', icon: 'transmog', dimension: 'transmog' },
    { key: 'professions', path: '/base-de-donnees/professions', icon: 'professions', dimension: 'professions' },
];

defineProps({
    meta: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
});

const page = usePage();
const { readable } = useWowColor();

const isAuthenticated = computed(() => page.props.auth?.isAuthenticated ?? false);

const locale = useActiveLocale();
const { localizedUrl } = useLocalizedUrl();
const formatCount = (count) => (count ? count.toLocaleString(locale.value) : '—');
</script>
