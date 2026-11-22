<template>
    <div v-if="isOwner && state" class="flex flex-wrap items-center gap-3 rounded-ui-md border px-4 py-3 text-sm" :class="state.frame">
        <template v-if="state.key === LOADING">
            <div class="flex items-center gap-3 text-muted">
                <Spinner size="sm" :label="$t('sheet.cross.computing')" />
                <span aria-hidden="true">{{ $t('sheet.cross.computing') }}</span>
            </div>
        </template>
        <template v-else>
            <Icon :icon="state.icon" :class="state.tone" />
            <p class="min-w-0 flex-1 text-default">
                <span class="font-semibold">{{ $t(state.title) }}.</span>
                <span class="text-muted"> {{ $t('sheet.cross.explanation') }}</span>
            </p>
            <Button size="sm" @click="store.computeCrossCharacter()">
                <Icon :icon="RotateCw" size="sm" />
                {{ $t(state.action) }}
            </Button>
        </template>
    </div>
</template>

<script>
import { CircleAlert, Info, RotateCw } from 'lucide-vue-next';

const LOADING = 'loading';

const STATES = Object.freeze({
    not_available: { key: 'not_available', icon: Info, tone: 'text-info', frame: 'border-info/40 bg-info/10', title: 'sheet.cross.not_available', action: 'sheet.cross.compute' },
    [LOADING]: { key: LOADING, frame: 'border-default bg-surface' },
    error: { key: 'error', icon: CircleAlert, tone: 'text-danger', frame: 'border-danger/40 bg-danger/10', title: 'sheet.cross.failed', action: 'sheet.cross.retry' },
});
</script>

<script setup>
import { computed } from 'vue';
import { useCharacterStore } from '../../stores/character';
import Button from '../ui/Button.vue';
import Icon from '../ui/Icon.vue';
import Spinner from '../ui/Spinner.vue';

defineProps({
    isOwner: { type: Boolean, required: true },
});

const store = useCharacterStore();

const state = computed(() => STATES[store.crossCharacterStatus] ?? null);
</script>
