<template>
    <Badge :tone="described.tone" :data-status="status" class="whitespace-nowrap">{{ described.label }}</Badge>
</template>

<script setup>
import { computed } from 'vue';
import Badge from '../ui/Badge.vue';
import { healthStatus, importStatus } from '../../utils/adminStatus';

const props = defineProps({
    kind: { type: String, required: true, validator: (value) => ['health', 'import'].includes(value) },
    status: { type: String, required: true },
});

const described = computed(() => (props.kind === 'health' ? healthStatus(props.status) : importStatus(props.status)));
</script>
