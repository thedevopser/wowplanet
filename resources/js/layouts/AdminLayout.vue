<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <nav aria-label="Administration" class="relative no-scrollbar flex items-center gap-1 overflow-x-auto border-b border-default bg-surface px-3">
            <Link
                v-for="section in sections"
                :key="section.path"
                :href="section.path"
                :aria-current="isActive(section) ? 'page' : null"
                class="-mb-px inline-flex min-h-11 shrink-0 items-center whitespace-nowrap border-b-2 px-3 text-sm font-medium transition-colors duration-fast
                    focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent"
                :class="isActive(section) ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-default'"
            >{{ section.label }}</Link>
        </nav>

        <div class="flex-1">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const currentPath = computed(() => page.url.split('?')[0]);

const sections = [
    { path: '/admin', label: 'Tableau de bord', exact: true },
    { path: '/admin/imports', label: 'Imports' },
    { path: '/admin/history', label: 'Historique' },
    { path: '/admin/reference', label: 'Socle de référence' },
    { path: '/admin/taxonomy', label: 'Taxonomie' },
    { path: '/admin/health', label: 'Santé' },
    { path: '/admin/tools', label: 'Outils' },
];

function isActive(section) {
    if (section.exact) {
        return currentPath.value === section.path;
    }

    return currentPath.value === section.path || currentPath.value.startsWith(section.path + '/');
}
</script>
