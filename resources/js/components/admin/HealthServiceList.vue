<template>
    <ul>
        <li
            v-for="probe in services"
            :key="probe.service"
            :data-service="probe.service"
            class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-default py-2.5 last:border-0"
        >
            <span
                aria-hidden="true"
                class="size-2.5 self-center rounded-full"
                :class="probe.status === 'ok' ? 'bg-success' : 'bg-danger motion-safe:animate-pulse'"
            ></span>
            <span class="text-sm text-default">{{ serviceLabel(probe.service) }}</span>
            <AdminStatusBadge kind="health" :status="probe.status" />
            <span v-if="probe.detail" data-role="detail" class="basis-full break-all font-mono text-xs text-danger">
                {{ probe.detail }}
            </span>
        </li>
    </ul>
</template>

<script setup>
import AdminStatusBadge from './AdminStatusBadge.vue';

const REDIS_PREFIX = 'redis:';

defineProps({
    services: { type: Array, required: true },
});

function serviceLabel(service) {
    return service.startsWith(REDIS_PREFIX)
        ? `Redis — ${service.slice(REDIS_PREFIX.length)}`
        : 'PostgreSQL';
}
</script>
