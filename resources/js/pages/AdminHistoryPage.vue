<template>
    <div class="space-y-8">
        <Head>
            <title>Historique des imports - Administration WowPlanet</title>
        </Head>

        <AdminPageHeader
            title="Historique des imports"
            description="Chaque passage de la chaîne d'import, lancé depuis le panneau ou la console. Le rapport de chaque import est gardé douze mois ; son journal détaillé, une journée. Cochez deux imports pour les comparer."
        />

        <Card class="space-y-6 p-5 sm:p-6">
            <ImportHistoryTable
                :entries="history.entries"
                :selected="selected"
                @update:selected="selected = $event"
            />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <Button data-action="compare" variant="primary" :disabled="selected.length !== COMPARED" @click="compare">
                    Comparer les deux imports
                </Button>

                <nav v-if="history.pages > 1" data-role="pagination" aria-label="Pages de l’historique" class="flex items-center gap-3 text-sm">
                    <Link v-if="history.page > 1" data-action="previous-page" :href="pageUrl(history.page - 1)" class="inline-flex min-h-11 items-center text-accent hover:underline">
                        ← Plus récents
                    </Link>
                    <span class="tabular-nums text-muted">{{ history.page }} / {{ history.pages }}</span>
                    <Link v-if="history.page < history.pages" data-action="next-page" :href="pageUrl(history.page + 1)" class="inline-flex min-h-11 items-center text-accent hover:underline">
                        Plus anciens →
                    </Link>
                </nav>
            </div>
        </Card>
    </div>
</template>

<script>
import AppLayout from '../layouts/AppLayout.vue';
import AdminLayout from '../layouts/AdminLayout.vue';

export default {
    layout: [AppLayout, AdminLayout],
};
</script>

<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ImportHistoryTable from '../components/admin/ImportHistoryTable.vue';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';

const COMPARED = 2;

defineProps({
    history: { type: Object, required: true },
});

const selected = ref([]);

function compare() {
    const [first, second] = selected.value;
    router.visit(`/admin/history/compare?first=${encodeURIComponent(first)}&second=${encodeURIComponent(second)}`);
}

function pageUrl(page) {
    return `/admin/history?page=${page}`;
}
</script>
