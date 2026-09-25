<template>
    <Card v-if="tracking.jobId.value" as="section" :aria-labelledby="headingId" class="space-y-4 p-5 sm:p-6">
        <h2 :id="headingId" class="font-display text-2xl font-semibold text-default">Suivi</h2>
        <ImportRunPanel
            :status="tracking.status.value"
            :stage-label="tracking.stageLabel.value"
            :percent="tracking.percent.value"
            :elapsed-seconds="tracking.elapsedSeconds.value"
            :eta-seconds="tracking.etaSeconds.value"
            :budget="tracking.budget.value"
            :waiting="tracking.waiting.value"
            :steps="tracking.steps.value"
            :lines="tracking.lines.value"
            :interrupted-for="tracking.interruptedFor.value"
            :abandoned-in="tracking.abandonedIn.value"
            :steering="tracking.steering.value"
            @pause="tracking.pause"
            @resume="tracking.resume"
            @cancel="tracking.cancel"
        />
        <p v-if="tracking.error.value" role="alert" class="text-sm text-danger">{{ tracking.error.value }}</p>
    </Card>
</template>

<script setup>
import { useId } from 'vue';
import Card from '../ui/Card.vue';
import ImportRunPanel from './ImportRunPanel.vue';

// The state returned by useImportProgress, shared by the three pages that launch imports.
defineProps({
    tracking: { type: Object, required: true },
});

const headingId = useId();
</script>
