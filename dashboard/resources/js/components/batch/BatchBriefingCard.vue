<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import type { BatchBriefing } from '@/types/console';

defineProps<{ briefing: BatchBriefing }>();
</script>

<template>
    <section class="flex flex-col gap-2 rounded-xl border bg-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">{{ briefing.headline }}</h2>
            <span
                class="flex items-center gap-1 rounded px-2 py-0.5 text-xs font-semibold"
                :class="
                    briefing.source !== 'template'
                        ? 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300'
                        : 'bg-muted text-muted-foreground'
                "
                :title="
                    briefing.source !== 'template'
                        ? 'Worded by an LLM from the stored evidence only; it does not choose the action.'
                        : 'Built from the stored evidence by a template.'
                "
            >
                <Sparkles
                    v-if="briefing.source !== 'template'"
                    class="size-3"
                />
                {{
                    briefing.source !== 'template'
                        ? `AI summary · ${briefing.model}`
                        : 'Template summary'
                }}
            </span>
        </div>
        <p class="text-sm leading-relaxed">{{ briefing.summary }}</p>
        <p
            v-if="briefing.qaNote"
            class="text-sm text-amber-700 dark:text-amber-400"
        >
            {{ briefing.qaNote }}
        </p>
    </section>
</template>
