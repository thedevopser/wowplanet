<template>
    <span class="inline-flex shrink-0 items-center gap-1 text-xs" :class="state.tone">
        <Icon :icon="state.icon" size="sm" />
        <span :class="{ 'sr-only': !state.visible }">{{ state.text }}</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { Check, Circle, Users } from 'lucide-vue-next';
import Icon from '../ui/Icon.vue';
import { useTranslate } from '../../composables/useTranslate';

const t = useTranslate();

const props = defineProps({
    done: { type: Boolean, required: true },
    // Done by another character of the account, from the cross-character data.
    elsewhere: { type: Boolean, default: false },
    owner: { type: String, default: '' },
});

const state = computed(() => {
    if (props.done) {
        return { icon: Check, tone: 'text-success', text: t('sheet.completion.done'), visible: false };
    }
    if (props.elsewhere) {
        return { icon: Users, tone: 'text-warning', text: props.owner ? t('sheet.completion.done_by', { owner: props.owner }) : t('sheet.completion.done_elsewhere'), visible: true };
    }

    return { icon: Circle, tone: 'text-subtle', text: t('sheet.completion.todo'), visible: false };
});
</script>
