<template>
    <div>
        <button
            data-testid="sidebar-toggle"
            type="button"
            :aria-label="toggleLabel"
            :aria-expanded="String(taskStore.sidebarOpen)"
            :aria-controls="panelId"
            class="fixed bottom-6 right-6 z-overlay flex size-14 items-center justify-center rounded-full bg-accent text-on-accent shadow-elevation-2
                transition-colors duration-fast hover:bg-accent/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            @click="taskStore.toggleSidebar()"
        >
            <Icon :icon="ListChecks" size="lg" />
            <span
                v-if="taskStore.totalPendingCount > 0"
                data-testid="pending-badge"
                aria-hidden="true"
                class="absolute -right-1 -top-1 flex h-6 min-w-6 items-center justify-center rounded-full border-2 border-surface bg-danger px-1 text-xs font-semibold tabular-nums text-on-accent"
            >
                {{ taskStore.totalPendingCount }}
            </span>
        </button>

        <Transition
            enter-active-class="transition-opacity duration-base ease-enter"
            leave-active-class="transition-opacity duration-fast ease-exit"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="taskStore.sidebarOpen"
                data-testid="sidebar-backdrop"
                class="fixed inset-0 z-overlay bg-night-950/60 sm:hidden"
                @click="taskStore.closeSidebar()"
            />
        </Transition>

        <Transition
            enter-active-class="transition-transform duration-base ease-enter"
            leave-active-class="transition-transform duration-fast ease-exit"
            enter-from-class="translate-x-full"
            leave-to-class="translate-x-full"
        >
            <aside
                v-if="taskStore.sidebarOpen"
                :id="panelId"
                data-testid="sidebar-panel"
                :aria-labelledby="titleId"
                class="fixed right-0 top-0 z-overlay flex h-full w-full max-w-sm flex-col border-l border-default bg-surface text-default shadow-elevation-2"
                @keydown.esc="taskStore.closeSidebar()"
            >
                <div class="flex items-center justify-between border-b border-default px-4 py-3">
                    <h2 :id="titleId" class="text-lg font-semibold">Mes tâches</h2>
                    <IconButton :icon="X" label="Fermer le panneau des tâches" class="-mr-2" @click="taskStore.closeSidebar()" />
                </div>

                <div class="flex-1 space-y-2 overflow-y-auto px-3 py-3">
                    <EmptyState
                        v-if="displayedCharacters.length === 0"
                        :icon="ListChecks"
                        title="Aucune tâche"
                        message="Ouvrez la fiche d’un de vos personnages pour lui ajouter des tâches."
                    />

                    <section
                        v-for="char in displayedCharacters"
                        :key="keyOf(char)"
                        data-testid="character-section"
                    >
                        <button
                            data-testid="character-header"
                            type="button"
                            :aria-expanded="String(isExpanded(char))"
                            :aria-controls="listIdOf(char)"
                            class="flex min-h-11 w-full items-center gap-3 rounded-ui-md px-2 py-2 text-left transition-colors duration-fast hover:bg-surface-raised
                                focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            @click="toggleCharacter(char)"
                        >
                            <img
                                v-if="detailsOf(char).avatarUrl"
                                :src="detailsOf(char).avatarUrl"
                                alt=""
                                class="size-8 rounded-full border border-default"
                            >
                            <span v-else aria-hidden="true" class="flex size-8 items-center justify-center rounded-full border border-default bg-surface-raised text-xs text-muted">
                                {{ detailsOf(char).name.charAt(0) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ detailsOf(char).name }}</span>
                                <span class="block truncate text-xs text-subtle">{{ detailsOf(char).realm }}</span>
                            </span>
                            <span class="text-xs tabular-nums text-muted">
                                {{ taskStore.pendingCount(char.realm_slug, char.character_name) }}
                                <span class="sr-only">en attente</span>
                            </span>
                            <Icon :icon="ChevronRight" size="sm" class="text-subtle transition-transform duration-fast" :class="{ 'rotate-90': isExpanded(char) }" />
                        </button>

                        <div v-if="isExpanded(char)" :id="listIdOf(char)" class="ml-4 mt-1 space-y-1">
                            <ul class="space-y-1">
                                <li
                                    v-for="task in taskStore.characterTasks(char.realm_slug, char.character_name)"
                                    :key="task.id"
                                    data-testid="task-item"
                                    class="flex items-center gap-2 rounded-ui-sm px-2"
                                >
                                    <input
                                        :id="`task-${task.id}`"
                                        data-testid="task-checkbox"
                                        type="checkbox"
                                        :checked="task.is_completed"
                                        class="size-5 shrink-0 accent-accent"
                                        @change="taskStore.toggleTask(task.id)"
                                    >
                                    <label
                                        :for="`task-${task.id}`"
                                        class="min-w-0 flex-1 truncate py-2 text-sm"
                                        :class="task.is_completed ? 'text-subtle line-through' : 'text-default'"
                                    >{{ task.name }}</label>
                                    <Badge data-testid="task-frequency" tone="neutral">{{ FREQUENCIES[task.reset_type] }}</Badge>
                                    <IconButton
                                        data-testid="delete-task-btn"
                                        :icon="Trash2"
                                        icon-size="sm"
                                        variant="danger"
                                        :label="`Supprimer la tâche « ${task.name} »`"
                                        @click="taskStore.deleteTask(task.id)"
                                    />
                                </li>
                            </ul>

                            <div v-if="!isComposing(char)" class="px-2 py-1">
                                <Button data-testid="add-task-btn" variant="ghost" size="sm" @click="openForm(char)">
                                    <Icon :icon="Plus" size="sm" />
                                    Nouvelle tâche
                                </Button>
                            </div>

                            <form
                                v-else
                                data-testid="task-form"
                                class="space-y-3 rounded-ui-md border border-default bg-surface-raised p-3"
                                @submit.prevent="submitForm(char)"
                            >
                                <div class="space-y-1">
                                    <label :for="`${listIdOf(char)}-name`" class="block text-sm font-medium text-muted">Nom de la tâche</label>
                                    <input
                                        :id="`${listIdOf(char)}-name`"
                                        ref="nameInput"
                                        v-model="formName"
                                        data-testid="task-name-input"
                                        type="text"
                                        required
                                        class="h-11 w-full rounded-ui-sm border border-strong bg-surface px-3 text-sm text-default
                                            focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                    >
                                </div>
                                <div class="space-y-1">
                                    <label :for="`${listIdOf(char)}-frequency`" class="block text-sm font-medium text-muted">Fréquence</label>
                                    <select
                                        :id="`${listIdOf(char)}-frequency`"
                                        v-model="formResetType"
                                        data-testid="task-reset-select"
                                        class="h-11 w-full rounded-ui-sm border border-strong bg-surface px-3 text-sm text-default
                                            focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                    >
                                        <option v-for="(label, value) in FREQUENCIES" :key="value" :value="value">{{ label }}</option>
                                    </select>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button variant="ghost" size="sm" @click="closeForm()">Annuler</Button>
                                    <Button variant="primary" size="sm" type="submit">Ajouter</Button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </aside>
        </Transition>
    </div>
</template>

<script>
import { ChevronRight, ListChecks, Plus, Trash2, X } from 'lucide-vue-next';

const DAILY = 'daily';

const FREQUENCIES = Object.freeze({
    [DAILY]: 'Quotidienne',
    weekly: 'Hebdomadaire',
    monthly: 'Mensuelle',
});

const CHARACTER_PATH = /^\/character\/([^/]+)\/([^/]+)$/;

const capitalize = (value) => value.charAt(0).toUpperCase() + value.slice(1);
</script>

<script setup>
import { computed, nextTick, onMounted, ref, useId, useTemplateRef, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTaskStore } from '../../stores/tasks';
import { useCharacterStore } from '../../stores/character';
import Badge from '../ui/Badge.vue';
import Button from '../ui/Button.vue';
import EmptyState from '../ui/EmptyState.vue';
import Icon from '../ui/Icon.vue';
import IconButton from '../ui/IconButton.vue';

const taskStore = useTaskStore();
const characterStore = useCharacterStore();
const page = usePage();

const panelId = useId();
const titleId = useId();

onMounted(() => {
    if (characterStore.isAuthenticated && !characterStore.userCharacters.length) {
        characterStore.fetchUserCharacters();
    }
});

const keyOf = (char) => `${char.realm_slug}|${char.character_name}`;
const listIdOf = (char) => `${panelId}-${char.realm_slug}-${char.character_name}`;

const toggleLabel = computed(() => (taskStore.totalPendingCount > 0
    ? `Mes tâches, ${taskStore.totalPendingCount} en attente`
    : 'Mes tâches'));

// Only the owner of a sheet may give it tasks: another player's sheet is never offered.
const ownedSheetCharacter = computed(() => {
    if (page.props.isOwner !== true) {
        return null;
    }

    const match = page.url.split('?')[0].match(CHARACTER_PATH);
    if (!match) {
        return null;
    }

    return {
        realm_slug: decodeURIComponent(match[1]).toLowerCase(),
        character_name: decodeURIComponent(match[2]).toLowerCase(),
    };
});

const displayedCharacters = computed(() => {
    const list = [...taskStore.charactersWithTasks];
    const sheet = ownedSheetCharacter.value;

    if (sheet && !list.some((char) => keyOf(char) === keyOf(sheet))) {
        list.unshift(sheet);
    }

    return list;
});

function detailsOf(char) {
    const account = characterStore.userCharacters.find(
        (candidate) => candidate.realmSlug === char.realm_slug && candidate.name?.toLowerCase() === char.character_name,
    );
    if (account) {
        return { name: account.name, realm: account.realm || char.realm_slug, avatarUrl: account.avatarUrl || '' };
    }

    const sheet = page.props.character;
    if (sheet && ownedSheetCharacter.value && keyOf(ownedSheetCharacter.value) === keyOf(char)) {
        return { name: sheet.name, realm: sheet.realm || char.realm_slug, avatarUrl: sheet.avatarUrl || '' };
    }

    return { name: capitalize(char.character_name), realm: char.realm_slug, avatarUrl: '' };
}

const expandedCharacters = ref(new Set());
const composingFor = ref(null);
const formName = ref('');
const formResetType = ref(DAILY);
const nameInput = useTemplateRef('nameInput');

const isExpanded = (char) => expandedCharacters.value.has(keyOf(char));
const isComposing = (char) => composingFor.value === keyOf(char);

function toggleCharacter(char) {
    const key = keyOf(char);
    if (expandedCharacters.value.has(key)) {
        expandedCharacters.value.delete(key);
    } else {
        expandedCharacters.value.add(key);
    }
}

function openForm(char) {
    composingFor.value = keyOf(char);
    formName.value = '';
    formResetType.value = DAILY;
}

function closeForm() {
    composingFor.value = null;
    formName.value = '';
}

async function submitForm(char) {
    const name = formName.value.trim();
    if (!name) {
        return;
    }

    await taskStore.createTask(char.realm_slug, char.character_name, name, formResetType.value);
    closeForm();
}

watch(() => taskStore.composeRequest, async (request) => {
    if (!request) {
        return;
    }

    expandedCharacters.value.add(keyOf(request));
    openForm(request);
    await nextTick();
    nameInput.value?.[0]?.focus();
});
</script>
