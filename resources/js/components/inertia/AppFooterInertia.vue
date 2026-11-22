<template>
    <footer class="shrink-0 border-t border-default bg-surface">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 text-sm sm:grid-cols-3 md:px-6">
            <div class="space-y-2">
                <p class="font-display text-lg font-semibold text-default">WowPlanet</p>
                <p class="text-xs text-subtle">
                    {{ $t('footer.disclaimer') }}
                    &copy; {{ year }} WowPlanet.
                </p>
            </div>

            <nav aria-labelledby="footer-explore">
                <p id="footer-explore" class="mb-2 text-xs font-medium uppercase tracking-wide text-subtle">{{ $t('footer.explore') }}</p>
                <ul>
                    <li v-for="link in EXPLORE_LINKS" :key="link.href">
                        <Link :href="localizedUrl(link.href)" :class="LINK">{{ $t(link.label) }}</Link>
                    </li>
                </ul>
            </nav>

            <nav aria-labelledby="footer-about">
                <p id="footer-about" class="mb-2 text-xs font-medium uppercase tracking-wide text-subtle">{{ $t('footer.about') }}</p>
                <ul>
                    <li v-for="link in ABOUT_LINKS" :key="link.href">
                        <Link :href="localizedUrl(link.href)" :class="LINK">{{ $t(link.label) }}</Link>
                    </li>
                    <li>
                        <a :href="DISCORD_URL" target="_blank" rel="noopener noreferrer" :class="LINK">Discord</a>
                    </li>
                </ul>
            </nav>
        </div>
    </footer>
</template>

<script>
const DISCORD_URL = 'https://discord.gg/wa49gGF8cr';

const EXPLORE_LINKS = Object.freeze([
    { label: 'nav.database', href: '/base-de-donnees' },
    { label: 'nav.pvp', href: '/classements-pvp' },
    { label: 'nav.account', href: '/mon-compte' },
]);

const ABOUT_LINKS = Object.freeze([
    { label: 'nav.faq', href: '/faq' },
    { label: 'nav.addons', href: '/addons' },
    { label: 'nav.privacy', href: '/privacy' },
    { label: 'nav.terms', href: '/cgu' },
]);

const LINK = 'inline-flex min-h-11 min-w-11 items-center lg:min-h-9 rounded-ui-sm text-muted transition-colors duration-fast hover:text-default '
    + 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
</script>

<script setup>
import { Link } from '@inertiajs/vue3';
import { useLocalizedUrl } from '../../composables/useLocalizedUrl';

const { localizedUrl } = useLocalizedUrl();

const year = new Date().getFullYear();
</script>
