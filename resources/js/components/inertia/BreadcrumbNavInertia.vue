<template>
    <nav aria-label="Fil d’Ariane" class="mb-6 text-sm text-subtle">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li>
                <a href="/" class="inline-flex min-h-11 items-center rounded-ui-sm hover:text-default transition-colors">WowPlanet</a>
            </li>
            <li v-for="(crumb, i) in crumbs" :key="i" class="flex items-center gap-1.5">
                <span aria-hidden="true">/</span>
                <span v-if="i === crumbs.length - 1" aria-current="page" class="font-medium text-muted">{{ crumb.label }}</span>
                <a v-else-if="crumb.to" :href="crumb.to" class="inline-flex min-h-11 items-center rounded-ui-sm hover:text-default transition-colors">{{ crumb.label }}</a>
                <span v-else>{{ crumb.label }}</span>
            </li>
        </ol>
    </nav>
</template>

<script setup>
defineProps({
    crumbs: {
        type: Array,
        required: true,
    },
});
</script>
