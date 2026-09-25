<template>
    <div>
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
            <meta property="og:locale" content="fr_FR">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" :content="meta.ogTitle">
            <meta name="twitter:description" :content="meta.ogDescription">
            <meta name="twitter:image" :content="meta.ogImage">
        </Head>

        <div v-if="store.character" class="space-y-8">
            <BreadcrumbNavInertia :crumbs="breadcrumbs" />
            <CharacterCard :character="store.character" :realm="realm" :name="name" :is-owner="isOwner" />
            <CrossDataBanner :is-owner="isOwner" />

            <Tabs v-model="activeSection" :tabs="SECTIONS" label="Sections de la fiche">
                <template #[OVERVIEW]>
                    <CharacterOverview :character="store.character" :realm="realm" :name="name" />
                </template>
                <template v-for="section in ROUTED_SECTIONS" :key="section.value" #[section.value]>
                    <Tabs v-model="activeSub" :tabs="section.subs" :label="section.label" variant="secondary">
                        <template v-for="sub in section.subs" :key="sub.value" #[sub.value]>
                            <component :is="SUB_COMPONENTS[sub.value]" v-bind="subPropsFor(sub.value)" />
                        </template>
                    </Tabs>
                </template>
            </Tabs>
        </div>
        <div v-else data-not-found class="mx-auto max-w-xl space-y-4 py-16 text-center">
            <BreadcrumbNavInertia :crumbs="[{ label: notFoundName }]" />
            <h1 class="font-display text-3xl font-bold text-default">Personnage introuvable</h1>
            <p class="text-muted">Impossible de récupérer le personnage {{ notFoundName }}. Vérifiez le nom et le royaume.</p>
            <Button href="/">Retour à l’accueil</Button>
        </div>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from '../components/ui/Button.vue';
import Tabs from '../components/ui/Tabs.vue';
import { OVERVIEW, SECTIONS, UnknownSheetViewError, viewOf } from '../utils/characterSheet';
import { useSheetNavigation } from '../composables/useSheetNavigation';
import { useCharacterStore } from '../stores/character';
import CharacterCard from '../components/CharacterCard.vue';
import BreadcrumbNavInertia from '../components/inertia/BreadcrumbNavInertia.vue';
import QuestsTab from '../components/QuestsTab.vue';
import AchievementsTab from '../components/AchievementsTab.vue';
import ReputationsTab from '../components/ReputationsTab.vue';
import ProfessionsTab from '../components/ProfessionsTab.vue';
import CollectionTab from '../components/sheet/CollectionTab.vue';
import CrossDataBanner from '../components/sheet/CrossDataBanner.vue';
import TransmogTab from '../components/TransmogTab.vue';
import CharacterOverview from '../components/CharacterOverview.vue';
import MythicPlusTab from '../components/MythicPlusTab.vue';
import RaidsTab from '../components/RaidsTab.vue';
import PvpTab from '../components/PvpTab.vue';
import EquipmentTab from '../components/EquipmentTab.vue';

const props = defineProps({
    character: { type: Object, default: null },
    realm: { type: String, required: true },
    name: { type: String, required: true },
    meta: { type: Object, required: true },
    isOwner: { type: Boolean, default: false },
    section: { type: String, default: null },
    sub: { type: String, default: null },
});

const store = useCharacterStore();

const ROUTED_SECTIONS = SECTIONS.filter((section) => section.value !== OVERVIEW);

const SUB_COMPONENTS = Object.freeze({
    quetes: QuestsTab,
    'hauts-faits': AchievementsTab,
    reputations: ReputationsTab,
    metiers: ProfessionsTab,
    'mythique-plus': MythicPlusTab,
    raids: RaidsTab,
    pvp: PvpTab,
    equipement: EquipmentTab,
    montures: CollectionTab,
    mascottes: CollectionTab,
    decorations: CollectionTab,
    'garde-robe': TransmogTab,
});

const WITH_CHARACTER = new Set(['garde-robe', 'equipement']);

const COLLECTION_KINDS = Object.freeze({ montures: 'mounts', mascottes: 'pets', decorations: 'decor' });

function subPropsFor(sub) {
    if (sub === 'pvp') {
        return { realm: props.realm, name: props.name };
    }
    if (Object.hasOwn(COLLECTION_KINDS, sub)) {
        return { kind: COLLECTION_KINDS[sub], character: store.character };
    }

    return WITH_CHARACTER.has(sub) ? { character: store.character } : {};
}

// The server validates the segments; a bad pair can only come from a stale history entry.
const view = computed(() => {
    try {
        return viewOf(props.section, props.sub);
    } catch (error) {
        if (error instanceof UnknownSheetViewError) {
            return viewOf(null, null);
        }
        throw error;
    }
});

const { show } = useSheetNavigation(() => props.realm, () => props.name);

const activeSection = computed({
    get: () => view.value.section,
    set: (section) => show(section, viewOf(section, null).sub),
});

const activeSub = computed({
    get: () => view.value.sub,
    set: (sub) => show(view.value.section, sub),
});

// Amorce le store depuis les props serveur (synchrone => rendu SSR immédiat).
function seedCharacter(character) {
    store.character = character;
    store.error = null;
}
seedCharacter(props.character);

const notFoundName = computed(() => props.name ? props.name.charAt(0).toUpperCase() + props.name.slice(1) : '');

const breadcrumbs = computed(() => {
    const crumbs = [];
    if (props.isOwner) {
        crumbs.push({ label: 'Mon compte', to: '/mon-compte' });
    }
    if (store.character) {
        crumbs.push({ label: store.character.name });
    }
    return crumbs;
});


// Sur navigation Inertia vers un autre personnage, le composant est réutilisé :
// on ré-amorce le store et on réinitialise l'onglet.
watch(() => props.character, (character) => {
    seedCharacter(character);
});

onMounted(() => {
    if (store.isAuthenticated && store.crossCharacterStatus !== 'ready') {
        store.loadCrossCharacterData();
    }
});
</script>
