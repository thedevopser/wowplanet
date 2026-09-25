<template>
    <header class="sticky top-0 z-header shrink-0 border-b border-default bg-surface">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 md:px-6">
            <Link href="/" class="flex shrink-0 items-center gap-2 rounded-ui-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                <img src="/images/logo.png" alt="WowPlanet" width="40" height="40" class="size-10 rounded-ui-md object-cover">
                <span aria-hidden="true" class="hidden font-display text-xl font-semibold text-default sm:inline">WowPlanet</span>
            </Link>

            <nav aria-label="Navigation principale" class="hidden lg:flex">
                <ul class="flex items-center gap-1">
                    <li v-for="entry in entries" :key="entry.href">
                        <Link
                            :href="entry.href"
                            :aria-current="entry.active ? 'page' : undefined"
                            :class="[NAV_LINK, entry.active ? NAV_LINK_ACTIVE : NAV_LINK_IDLE]"
                        >
                            {{ entry.label }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <template v-if="store.isAuthenticated">
                    <DropdownMenu
                        :items="userMenuItems"
                        label="Menu du compte"
                        :choices="THEME_CHOICES"
                        :choice="themeChoice"
                        @update:choice="setThemeChoice"
                        @select="onUserMenuSelect"
                    >
                        <template #trigger>
                            <Button variant="ghost" size="md" class="max-w-48">
                                <Icon :icon="CircleUser" />
                                <span class="truncate">{{ accountName }}</span>
                                <Icon :icon="ChevronDown" size="sm" />
                            </Button>
                        </template>
                    </DropdownMenu>
                </template>
                <template v-else>
                    <!-- The wrapper owns the display: Button sets its own inline-flex, which would beat hidden. -->
                    <div class="hidden sm:block">
                        <Button variant="primary" :href="LOGIN_URL" external>
                            Se connecter avec Battle.net
                        </Button>
                    </div>
                    <div class="hidden lg:block">
                        <IconButton
                            :icon="effectiveTheme === DARK ? Sun : Moon"
                            :label="effectiveTheme === DARK ? 'Passer en mode clair' : 'Passer en mode sombre'"
                            @click="toggleTheme()"
                        />
                    </div>
                </template>

                <Drawer v-model:open="drawerOpen" title="Menu">
                    <template #trigger>
                        <IconButton class="lg:hidden" :icon="Menu" label="Ouvrir le menu" />
                    </template>
                    <nav aria-label="Navigation principale">
                        <ul class="flex flex-col gap-1">
                            <li v-for="entry in entries" :key="entry.href">
                                <Link
                                    :href="entry.href"
                                    :aria-current="entry.active ? 'page' : undefined"
                                    :class="[DRAWER_LINK, entry.active ? DRAWER_LINK_ACTIVE : DRAWER_LINK_IDLE]"
                                >
                                    {{ entry.label }}
                                </Link>
                            </li>
                        </ul>
                    </nav>
                    <div v-if="!store.isAuthenticated" class="mt-6 flex flex-col gap-2 border-t border-default pt-6">
                        <Button variant="primary" :href="LOGIN_URL" external>
                            Se connecter avec Battle.net
                        </Button>
                        <Button variant="ghost" @click="toggleTheme()">
                            <Icon :icon="effectiveTheme === DARK ? Sun : Moon" />
                            {{ effectiveTheme === DARK ? 'Mode clair' : 'Mode sombre' }}
                        </Button>
                    </div>
                </Drawer>
            </div>
        </div>
    </header>
</template>

<script>
import { ChevronDown, CircleUser, LogOut, Menu, MessageCircle, Moon, Shield, Sun } from 'lucide-vue-next';

const DARK = 'dark';
const LOGOUT = 'logout';
const DISCORD_URL = 'https://discord.gg/wa49gGF8cr';
const ACCOUNT_PATH = '/mon-compte';

const THEME_CHOICES = Object.freeze({
    label: 'Thème',
    options: [
        { value: 'system', label: 'Système' },
        { value: 'dark', label: 'Sombre' },
        { value: 'light', label: 'Clair' },
    ],
});

const NAV_LINK = 'relative inline-flex h-11 items-center rounded-ui-sm px-3 text-sm font-medium transition-colors duration-fast '
    + 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
const NAV_LINK_ACTIVE = 'text-default after:absolute after:inset-x-3 after:-bottom-2.5 after:h-0.5 after:rounded-full after:bg-accent';
const NAV_LINK_IDLE = 'text-muted hover:text-default';
const DRAWER_LINK = 'flex h-11 items-center rounded-ui-sm border-l-2 px-3 text-base font-medium transition-colors duration-fast '
    + 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
const DRAWER_LINK_ACTIVE = 'border-accent bg-surface-raised text-default';
const DRAWER_LINK_IDLE = 'border-transparent text-muted hover:text-default';
</script>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useCharacterStore } from '../../stores/character';
import { useTheme } from '../../composables/useTheme';
import Button from '../ui/Button.vue';
import Drawer from '../ui/Drawer.vue';
import DropdownMenu from '../ui/DropdownMenu.vue';
import Icon from '../ui/Icon.vue';
import IconButton from '../ui/IconButton.vue';
import { LOGIN_URL } from '../../utils/auth';

const page = usePage();
const store = useCharacterStore();
const { effective: effectiveTheme, choice: themeChoice, toggle: toggleTheme, setChoice: setThemeChoice } = useTheme();

const currentPath = computed(() => page.url.split('?')[0]);
const drawerOpen = ref(false);

const isAccountPage = computed(() => currentPath.value === ACCOUNT_PATH || currentPath.value.startsWith(`${ACCOUNT_PATH}/`)
    || (currentPath.value.startsWith('/character/') && page.props.isOwner === true));

const entries = computed(() => {
    const list = [
        { label: 'Base de données', href: '/base-de-donnees', active: currentPath.value.startsWith('/base-de-donnees') },
        { label: 'Classements PvP', href: '/classements-pvp', active: currentPath.value.startsWith('/classements-pvp') },
    ];

    if (store.isAuthenticated) {
        list.push({ label: 'Mon compte', href: ACCOUNT_PATH, active: isAccountPage.value });
    }

    return list;
});

// The battletag reads "Name#1234": the number only matters to Battle.net.
const accountName = computed(() => store.battletag.split('#')[0] || 'Mon compte');

const userMenuItems = computed(() => [
    { key: 'discord', label: 'Discord', href: DISCORD_URL, external: true, icon: MessageCircle },
    ...(store.isAdmin ? [{ key: 'admin', label: 'Administration', href: '/admin', icon: Shield }] : []),
    { key: LOGOUT, label: 'Déconnexion', icon: LogOut, tone: 'danger' },
]);

async function onUserMenuSelect(key) {
    if (key !== LOGOUT) {
        return;
    }

    await store.logout();
    router.visit('/');
}

watch(currentPath, () => {
    drawerOpen.value = false;
});
</script>
