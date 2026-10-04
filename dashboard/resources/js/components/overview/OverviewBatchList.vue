<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    actionLabels,
    formatDateTime,
    roleLabels,
} from '@/components/console/format';
import { show } from '@/routes/batches';
import type { BatchListItem, Verdict } from '@/types/console';

defineProps<{
    title: string;
    empty: string;
    items: BatchListItem[];
    emphasis?: boolean;
}>();

const verdictDot: Record<Verdict, string> = {
    yes: 'bg-emerald-500',
    no: 'bg-red-500',
    at_risk: 'bg-amber-500',
    unknown: 'bg-muted-foreground/40',
};
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-xl border bg-card p-5"
        :class="
            emphasis && items.length > 0 ? 'border-2 border-sky-600/60' : ''
        "
    >
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <span
                class="text-2xl font-bold tabular-nums"
                :class="
                    emphasis && items.length > 0
                        ? 'text-sky-700 dark:text-sky-400'
                        : 'text-muted-foreground'
                "
            >
                {{ items.length }}
            </span>
        </div>
        <p v-if="items.length === 0" class="text-sm text-muted-foreground">
            {{ empty }}
        </p>
        <ul v-else class="flex flex-col divide-y">
            <li v-for="item in items" :key="item.batchId">
                <Link
                    :href="show(item.batchId)"
                    class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2 text-sm hover:text-sky-700 dark:hover:text-sky-400"
                >
                    <span class="flex flex-col gap-0.5">
                        <span>
                            <b>{{ item.label }}</b>
                            <span class="text-muted-foreground">
                                · {{ item.reactor }} · charge
                                {{
                                    formatDateTime(
                                        item.chargeAt,
                                        'Europe/Zurich',
                                    )
                                }}
                            </span>
                        </span>
                        <span
                            v-if="item.decision?.status === 'denied'"
                            class="text-xs text-red-700 dark:text-red-400"
                        >
                            {{ roleLabels[item.decision.requiredRole] }} denied
                            {{ actionLabels[item.decision.action] }}: “{{
                                item.decision.reviewReason
                            }}”
                        </span>
                        <span
                            v-else-if="item.decision?.status === 'pending'"
                            class="text-xs text-amber-700 dark:text-amber-400"
                        >
                            {{ actionLabels[item.decision.action] }} · waiting
                            for {{ roleLabels[item.decision.requiredRole] }}
                        </span>
                        <span v-else class="text-xs text-muted-foreground">
                            Model:
                            {{
                                item.recommendation.action
                                    ? actionLabels[item.recommendation.action]
                                    : 'no supported action'
                            }}
                        </span>
                    </span>
                    <span class="flex gap-3">
                        <span
                            v-for="answer in [
                                item.answers.arrival,
                                item.answers.quality,
                            ]"
                            :key="answer.question"
                            class="flex items-center gap-1.5"
                            :title="`${answer.question} ${answer.detail}`"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :class="verdictDot[answer.verdict]"
                            />
                            {{
                                answer.question.startsWith('Material')
                                    ? 'Ready'
                                    : 'Good'
                            }}
                        </span>
                    </span>
                </Link>
            </li>
        </ul>
    </section>
</template>
