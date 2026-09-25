<template>
    <div class="space-y-8 py-8 sm:py-12">
        <header class="mx-auto max-w-5xl space-y-3 rounded-ui-md border border-l-4 border-default border-l-accent bg-surface p-6 sm:p-8">
            <h1 class="font-display text-3xl font-bold text-default sm:text-4xl">{{ title }}</h1>
            <p v-if="lead" class="max-w-prose text-lg leading-relaxed text-muted">{{ lead }}</p>
            <p v-if="updatedAt" class="text-sm text-subtle">
                Dernière mise à jour : <time :datetime="updatedAt">{{ formattedUpdate }}</time>
            </p>
        </header>

        <div v-if="$slots.default" class="mx-auto max-w-5xl gap-8" :class="{ 'lg:grid lg:grid-cols-[15rem_minmax(0,1fr)]': sections.length }">
            <template v-if="sections.length">
                <aside class="hidden lg:block">
                    <nav aria-label="Sommaire" class="sticky top-[calc(var(--spacing-header)+1.5rem)] space-y-1 border-l border-default">
                        <a
                            v-for="section in sections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            :aria-current="section.id === current ? 'location' : undefined"
                            class="-ml-px block border-l-2 py-1.5 pl-4 text-sm transition-colors duration-fast"
                            :class="section.id === current ? 'border-accent font-medium text-default' : 'border-transparent text-muted hover:text-default'"
                        >{{ section.title }}</a>
                    </nav>
                </aside>
                <details class="mb-6 rounded-ui-md border border-default bg-surface lg:hidden">
                    <summary class="flex min-h-11 cursor-pointer items-center px-4 font-medium text-default">Sommaire</summary>
                    <ol class="space-y-1 border-t border-default px-4 py-3">
                        <li v-for="section in sections" :key="section.id">
                            <a :href="`#${section.id}`" class="flex min-h-9 items-center text-sm text-muted hover:text-default">{{ section.title }}</a>
                        </li>
                    </ol>
                </details>
            </template>

            <article class="editorial space-y-4" :class="{ 'max-w-3xl': !sections.length }">
                <slot />
            </article>
        </div>

        <div v-if="$slots.wide" class="mx-auto max-w-5xl">
            <slot name="wide" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useScrollSpy } from '../composables/useScrollSpy';

const props = defineProps({
    title: { type: String, required: true },
    lead: { type: String, default: '' },
    // ISO date (YYYY-MM-DD) of the last change of the text.
    updatedAt: { type: String, default: '' },
    // Each { id, title } matches a <section :id> of the page.
    sections: { type: Array, default: () => [] },
});

const current = useScrollSpy(() => props.sections.map((section) => section.id));

const formattedUpdate = computed(() => new Date(`${props.updatedAt}T12:00:00Z`)
    .toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }));
</script>
