<template>
    <footer class="shrink-0 border-t border-default bg-surface">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-8 text-sm sm:grid-cols-3 md:px-6">
            <div class="space-y-2">
                <p class="font-display text-lg font-semibold text-default">WowPlanet</p>
                <p class="text-xs text-subtle">
                    Site fan non officiel, sans lien ni affiliation avec Blizzard Entertainment.
                    &copy; {{ year }} WowPlanet.
                </p>
            </div>

            <nav aria-labelledby="footer-explore">
                <p id="footer-explore" class="mb-2 text-xs font-medium uppercase tracking-wide text-subtle">Explorer</p>
                <ul>
                    <li v-for="link in EXPLORE_LINKS" :key="link.href">
                        <Link :href="link.href" :class="LINK">{{ link.label }}</Link>
                    </li>
                </ul>
            </nav>

            <nav aria-labelledby="footer-about">
                <p id="footer-about" class="mb-2 text-xs font-medium uppercase tracking-wide text-subtle">Informations</p>
                <ul>
                    <li v-for="link in ABOUT_LINKS" :key="link.href">
                        <Link :href="link.href" :class="LINK">{{ link.label }}</Link>
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
    { label: 'Base de données', href: '/base-de-donnees' },
    { label: 'Classements PvP', href: '/classements-pvp' },
    { label: 'Mon compte', href: '/mon-compte' },
]);

const ABOUT_LINKS = Object.freeze([
    { label: 'FAQ', href: '/faq' },
    { label: 'Addons', href: '/addons' },
    { label: 'Confidentialité', href: '/privacy' },
    { label: 'CGU', href: '/cgu' },
]);

const LINK = 'inline-flex min-h-11 items-center sm:min-h-9 rounded-ui-sm text-muted transition-colors duration-fast hover:text-default '
    + 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
</script>

<script setup>
import { Link } from '@inertiajs/vue3';

const year = new Date().getFullYear();
</script>
