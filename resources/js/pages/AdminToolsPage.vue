<template>
    <div class="space-y-8">
        <Head>
            <title>Outils - Administration WowPlanet</title>
        </Head>

        <AdminPageHeader title="Outils" description="Caches applicatifs, mode maintenance et annonces Discord." />

        <Card as="section" aria-labelledby="cache-heading" class="p-5 sm:p-6">
            <h2 id="cache-heading" class="mb-4 font-display text-2xl font-semibold text-default">Cache</h2>
            <div class="flex flex-wrap items-center gap-3">
                <Button variant="primary" :disabled="cacheLoading" @click="clearCache">
                    {{ cacheLoading ? 'Nettoyage…' : 'Vider les caches' }}
                </Button>
            </div>
            <pre v-if="cacheOutput" class="mt-4 whitespace-pre-wrap rounded-ui-md border border-default bg-background p-4 font-mono text-xs text-muted">{{ cacheOutput }}</pre>
        </Card>

        <Card as="section" aria-labelledby="maintenance-heading" class="p-5 sm:p-6">
            <h2 id="maintenance-heading" class="mb-4 font-display text-2xl font-semibold text-default">Mode maintenance</h2>

            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <span aria-hidden="true" class="size-3 rounded-full" :class="maintenanceActive ? 'bg-danger motion-safe:animate-pulse' : 'bg-success'"></span>
                    <span role="status" class="text-sm text-default">
                        {{ maintenanceActive ? 'Mode maintenance actif' : 'Application en ligne' }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button v-if="!maintenanceActive" variant="danger" :disabled="maintenanceLoading" @click="toggleMaintenance(true)">
                        Activer la maintenance
                    </Button>
                    <Button v-else :disabled="maintenanceLoading" @click="toggleMaintenance(false)">
                        Désactiver la maintenance
                    </Button>
                </div>

                <div v-if="maintenanceBypassUrl" class="rounded-ui-md border border-default bg-background p-3">
                    <p class="mb-1 text-sm text-muted">URL de bypass (visitez cette URL pour accéder au site en maintenance) :</p>
                    <a :href="maintenanceBypassUrl" class="break-all text-sm text-accent hover:underline">{{ maintenanceBypassUrl }}</a>
                </div>
            </div>
        </Card>

        <DiscordComposer />
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
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import DiscordComposer from '../components/admin/DiscordComposer.vue';
import AdminPageHeader from '../components/admin/AdminPageHeader.vue';
import Button from '../components/ui/Button.vue';
import Card from '../components/ui/Card.vue';

const maintenanceActive = ref(false);
const maintenanceLoading = ref(false);
const maintenanceBypassUrl = ref('');

const fetchStatus = async () => {
    try {
        const response = await axios.get('/api/admin/status');
        maintenanceActive.value = response.data.maintenance;
    } catch {
        // ignore
    }
};

onMounted(() => {
    fetchStatus();
});

const cacheLoading = ref(false);
const cacheOutput = ref('');

const clearCache = async () => {
    cacheLoading.value = true;
    cacheOutput.value = '';
    try {
        const response = await axios.post('/api/admin/clear-cache');
        cacheOutput.value = response.data.output;
    } catch (err) {
        cacheOutput.value = err.response?.data?.message || 'Erreur';
    } finally {
        cacheLoading.value = false;
    }
};

const generateSecret = () => {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < 32; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
};

const toggleMaintenance = async (enable) => {
    maintenanceLoading.value = true;
    const secret = enable ? generateSecret() : null;

    try {
        const response = await axios.post('/api/admin/maintenance', { enable, secret });
        maintenanceActive.value = response.data.maintenance;

        if (enable && secret) {
            maintenanceBypassUrl.value = `${window.location.origin}/${secret}`;
        } else {
            maintenanceBypassUrl.value = '';
        }
    } catch {
        // ignore
    } finally {
        maintenanceLoading.value = false;
    }
};
</script>
