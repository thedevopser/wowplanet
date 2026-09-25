<template>
    <div class="space-y-12 py-6 sm:py-8">
        <Head>
            <title>Primitives d'interface | WowPlanet</title>
            <meta name="robots" content="noindex, nofollow">
        </Head>

        <header>
            <h1 class="font-display text-4xl font-bold text-default">Primitives d'interface</h1>
            <p class="mt-2 text-muted">Page de développement, servie en local uniquement. Basculer le thème depuis l'en-tête pour vérifier les deux.</p>
        </header>

        <section class="space-y-4">
            <SectionHeader title="Boutons" description="Quatre variantes, deux tailles, états désactivé et chargement." />
            <div class="flex flex-wrap items-center gap-3">
                <Button variant="primary">Action principale</Button>
                <Button>Secondaire</Button>
                <Button variant="ghost">Discret</Button>
                <Button variant="danger">Supprimer</Button>
                <Button variant="primary" size="sm">Petit</Button>
                <Button disabled>Désactivé</Button>
                <Button variant="primary" :loading="true">Enregistrer</Button>
                <Button data-demo-loading :loading="loading" @click="simulateLoading">Charger 2 s</Button>
                <Button href="/base-de-donnees" variant="ghost">Lien vers la base</Button>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <IconButton :icon="Search" label="Rechercher" />
                <IconButton :icon="Settings" label="Réglages" variant="secondary" />
                <IconButton :icon="Trash2" label="Supprimer" variant="danger" icon-size="sm" />
            </div>
        </section>

        <section class="space-y-4">
            <SectionHeader title="Cartes et statistiques">
                <template #actions>
                    <Button size="sm">Action de section</Button>
                </template>
            </SectionHeader>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile label="Montures" :value="412" :delta="12" />
                <StatTile label="Score" value="61,2" suffix="/ 100" :delta="-3" />
                <StatTile label="Réputations" :value="87" suffix="%" :delta="0" />
                <StatTile label="Mascottes" :value="1500" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card class="p-4">
                    <SectionHeader title="Carte à plat" :level="3" description="Surface et bordure fine, sans ombre." />
                </Card>
                <Card variant="interactive" as="article" class="p-4">
                    <SectionHeader title="Carte interactive" :level="3" />
                    <a
                        href="#cartes"
                        class="mt-2 inline-block text-accent underline after:absolute after:inset-0 focus-visible:outline-none"
                    >Toute la carte est ce lien</a>
                </Card>
            </div>
        </section>

        <section class="space-y-4">
            <SectionHeader title="Badges" />
            <div class="flex flex-wrap gap-2">
                <Badge>Neutre</Badge>
                <Badge tone="info">Info</Badge>
                <Badge tone="success">Succès</Badge>
                <Badge tone="warning">Attention</Badge>
                <Badge tone="danger">Erreur</Badge>
            </div>
            <div class="flex flex-wrap gap-2">
                <Badge v-for="classId in CLASS_IDS" :key="classId" tone="class" :value="classId">Classe {{ classId }}</Badge>
            </div>
            <div class="flex flex-wrap gap-2">
                <Badge v-for="quality in QUALITIES" :key="quality" tone="quality" :value="quality">{{ quality }}</Badge>
                <Badge v-for="faction in FACTIONS" :key="faction" tone="faction" :value="faction">{{ faction }}</Badge>
                <Badge v-for="rank in RANKS" :key="rank" tone="rank" :value="rank">{{ rank }}</Badge>
            </div>
        </section>

        <section class="space-y-4">
            <SectionHeader title="Onglets" description="Flèches, Début et Fin au clavier. Les sous-onglets défilent en mobile." />
            <Tabs v-model="section" :tabs="SECTIONS" label="Sections de la fiche">
                <template v-for="tab in SECTIONS" #[tab.value] :key="tab.value">
                    <Tabs :tabs="SUB_TABS" :label="`Sous-onglets de ${tab.label}`" variant="secondary">
                        <template v-for="sub in SUB_TABS" #[sub.value] :key="sub.value">
                            <p class="text-muted">{{ tab.label }} — {{ sub.label }}</p>
                        </template>
                    </Tabs>
                </template>
            </Tabs>
            <p class="text-sm text-muted">Section pilotée de l'extérieur : <span class="tabular-nums">{{ section }}</span></p>
        </section>

        <section class="space-y-4">
            <SectionHeader title="Dialogue, tiroir et menu" description="Piège à focus, Échap, retour du focus au déclencheur." />
            <div class="flex flex-wrap items-center gap-3">
                <Dialog title="Partager ce score" description="Copiez le lien ou téléchargez l'image.">
                    <template #trigger>
                        <Button variant="primary">Partager ce score</Button>
                    </template>
                    <label class="block text-sm text-muted" for="share-link">Lien</label>
                    <input id="share-link" class="mt-1 h-11 w-full rounded-ui-sm border border-strong bg-surface px-3 text-default" value="https://wowplanet.fr/character/hyjal/arthas" readonly>
                    <template #footer>
                        <Button>Télécharger l'image</Button>
                        <Button variant="primary">Copier le lien</Button>
                    </template>
                </Dialog>
                <Drawer title="Menu">
                    <template #trigger>
                        <Button>Ouvrir le tiroir</Button>
                    </template>
                    <p class="text-sm text-muted">La navigation principale s'y range sous 1 024 px.</p>
                </Drawer>
                <DropdownMenu :items="MENU_ITEMS" label="Menu du compte" @select="lastSelection = $event">
                    <template #trigger>
                        <Button variant="ghost">Menu du compte</Button>
                    </template>
                </DropdownMenu>
                <span class="text-sm text-muted">Dernier choix : {{ lastSelection || 'aucun' }}</span>
            </div>
        </section>

        <section class="space-y-4">
            <SectionHeader title="États" description="Chargement, vide, erreur et notifications." />
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2" aria-busy="true">
                    <Skeleton class="h-24 w-full" />
                    <Skeleton shape="text" class="w-2/3" />
                    <div class="flex items-center gap-3">
                        <Skeleton shape="circle" class="size-10" />
                        <Skeleton shape="text" class="w-1/2" />
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <Spinner size="sm" />
                    <Spinner />
                    <Spinner size="lg" label="Synchronisation avec Blizzard…" />
                </div>
                <EmptyState title="Aucune monture" message="Aucune monture ne correspond à ce filtre.">
                    <template #action>
                        <Button size="sm">Effacer le filtre</Button>
                    </template>
                </EmptyState>
                <ErrorState message="Le classement PvP est momentanément indisponible." @retry="retries += 1" />
            </div>
            <p class="text-sm text-muted">Nouvelles tentatives : <span class="tabular-nums">{{ retries }}</span></p>
            <div class="flex flex-wrap gap-3">
                <Button v-for="tone in TOAST_TONES" :key="tone" size="sm" :data-demo-toast="tone" @click="notify(tone)">Toast {{ tone }}</Button>
            </div>
        </section>

        <section class="space-y-4">
            <SectionHeader title="Barres de progression" />
            <div class="grid gap-4 sm:grid-cols-2">
                <ProgressBar
                    v-for="(key, index) in DIMENSION_KEYS"
                    :key="key"
                    :label="key"
                    :value="(index + 1) * 10"
                    :color="dimensionColor(key).base"
                />
                <ProgressBar :value="42" aria-label="Avancement sans libellé visible" />
            </div>
        </section>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { LogOut, Search, Settings, Star, Trash2, User } from 'lucide-vue-next';
import Badge from '../components/ui/Badge.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';
import Dialog from '../components/ui/Dialog.vue';
import Drawer from '../components/ui/Drawer.vue';
import DropdownMenu from '../components/ui/DropdownMenu.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import ErrorState from '../components/ui/ErrorState.vue';
import IconButton from '../components/ui/IconButton.vue';
import ProgressBar from '../components/ui/ProgressBar.vue';
import SectionHeader from '../components/ui/SectionHeader.vue';
import Skeleton from '../components/ui/Skeleton.vue';
import Spinner from '../components/ui/Spinner.vue';
import StatTile from '../components/ui/StatTile.vue';
import Tabs from '../components/ui/Tabs.vue';
import { TOAST_TONES, useToastStore } from '../stores/toasts';
import { CLASS_IDS, DIMENSION_KEYS, FACTIONS, QUALITIES, RANKS, dimensionColor } from '../utils/wowColors';

const LOADING_DEMO_MS = 2000;

const SECTIONS = [
    { value: 'overview', label: 'Aperçu' },
    { value: 'progress', label: 'Progression' },
    { value: 'endgame', label: 'Endgame' },
    { value: 'collections', label: 'Collections' },
];

const SUB_TABS = ['Quêtes', 'Hauts-faits', 'Réputations', 'Métiers', 'Montures', 'Mascottes', 'Décorations', 'Garde-robe']
    .map((label, index) => ({ value: `sub-${index}`, label }));

const MENU_ITEMS = [
    { key: 'account', label: 'Mon compte', icon: User },
    { key: 'favorites', label: 'Mes favoris', icon: Star },
    { key: 'logout', label: 'Déconnexion', icon: LogOut, tone: 'danger' },
];

const TOAST_TITLES = {
    info: 'Données croisées en cours de calcul',
    success: 'Favori ajouté',
    warning: 'Quota Blizzard bientôt atteint',
    error: 'Échec de la synchronisation',
};

const toasts = useToastStore();
const retries = ref(0);

function notify(tone) {
    toasts.show({ title: TOAST_TITLES[tone], description: tone === 'error' ? 'Cette notification reste affichée.' : '', tone });
}

const section = ref('overview');
const lastSelection = ref('');

const loading = ref(false);
let timer = null;

function simulateLoading() {
    loading.value = true;
    timer = setTimeout(() => {
        loading.value = false;
    }, LOADING_DEMO_MS);
}

onBeforeUnmount(() => clearTimeout(timer));
</script>
