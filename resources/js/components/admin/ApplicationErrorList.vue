<template>
    <p v-if="entries.length === 0" data-role="no-error" class="text-sm text-success">
        Aucune erreur enregistrée.
    </p>

    <ul v-else>
        <li v-for="entry in entries" :key="entry.id" :data-error="entry.id" class="space-y-1 border-b border-default py-2.5 last:border-0">
            <div class="flex flex-wrap items-baseline gap-x-3">
                <time :datetime="entry.occurred_at" class="font-mono text-xs text-muted whitespace-nowrap">
                    {{ formatDate(entry.occurred_at) }}
                </time>
                <span class="text-xs font-semibold text-danger">{{ entry.level }}</span>
                <span data-role="message" class="min-w-0 wrap-anywhere text-sm text-default">{{ entry.message }}</span>
            </div>
            <p v-if="entry.exception_class" data-role="origin" class="font-mono text-xs text-muted break-all">
                {{ entry.exception_class }}<template v-if="entry.location"> — {{ entry.location }}</template>
            </p>
        </li>
    </ul>
</template>

<script setup>
defineProps({
    entries: { type: Array, required: true },
});

function formatDate(isoDate) {
    return new Date(isoDate).toLocaleString('fr-FR', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}
</script>
