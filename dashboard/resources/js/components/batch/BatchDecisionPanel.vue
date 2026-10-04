<script setup lang="ts">
import { computed } from 'vue';
import { actionLabels, roleLabels } from '@/components/console/format';
import { Button } from '@/components/ui/button';
import type { ActionKey, BatchConsole, Role } from '@/types/console';

const props = defineProps<{ batch: BatchConsole; role: Role }>();

const emit = defineEmits<{
    choose: [action: ActionKey];
    review: [accept: boolean];
}>();

const options: { action: ActionKey; description: string; approver: Role }[] = [
    {
        action: 'RUN_AS_PLANNED',
        description: 'Charge with the planned material',
        approver: 'operator',
    },
    {
        action: 'BUFFER',
        description: 'Charge from released safety stock',
        approver: 'operator',
    },
    {
        action: 'EXPEDITE',
        description: 'Ask logistics for a faster slot',
        approver: 'logistics',
    },
    {
        action: 'REROUTE',
        description: 'Ask logistics for another route',
        approver: 'logistics',
    },
    {
        action: 'QUARANTINE',
        description: 'Hold the lot for QA review',
        approver: 'qa',
    },
];

const active = computed(() => props.batch.activeDecision);
const lastDenied = computed(() =>
    active.value === null
        ? (props.batch.decisions.find((d) => d.status === 'denied') ?? null)
        : null,
);
const canChoose = computed(
    () =>
        props.role === 'operator' &&
        active.value === null &&
        !props.batch.retrospective,
);
const canReview = computed(
    () =>
        active.value !== null &&
        active.value.status === 'pending' &&
        active.value.requiredRole === props.role,
);
</script>

<template>
    <section
        class="flex flex-col gap-4 rounded-xl border-2 border-sky-600/60 bg-card p-5"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="max-w-3xl">
                <div
                    class="text-xs font-semibold tracking-wide text-sky-700 uppercase dark:text-sky-400"
                >
                    Model recommendation
                </div>
                <h2
                    class="mt-1 text-3xl leading-tight font-bold tracking-tight"
                >
                    {{
                        batch.recommendation.action
                            ? actionLabels[batch.recommendation.action]
                            : 'No supported action'
                    }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ batch.recommendation.reason }}
                </p>
            </div>
            <span
                v-if="batch.recommendation.qaReviewRequired"
                class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
            >
                Human QA review required
            </span>
        </div>

        <!-- Request in progress or accepted -->
        <div
            v-if="active"
            class="flex flex-col gap-3 rounded-xl border-2 p-4"
            :class="
                active.status === 'accepted'
                    ? 'border-emerald-600/50'
                    : 'border-amber-500/60'
            "
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div class="text-lg font-semibold">
                    {{ active.submittedBy }} chose
                    <span class="text-sky-700 dark:text-sky-400">{{
                        actionLabels[active.action]
                    }}</span>
                    <span
                        v-if="active.modelAction !== active.action"
                        class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 align-middle text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                    >
                        {{
                            active.modelAction
                                ? 'Overrides model'
                                : 'No model action'
                        }}
                    </span>
                </div>
                <span
                    class="rounded px-2 py-0.5 text-xs font-semibold"
                    :class="
                        active.status === 'accepted'
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                    "
                >
                    {{
                        active.status === 'accepted'
                            ? 'Accepted'
                            : `Waiting for ${roleLabels[active.requiredRole]} review`
                    }}
                </span>
            </div>
            <p class="text-sm">
                <span class="text-muted-foreground">Operator's reason:</span>
                “{{ active.submittedReason }}”
            </p>
            <template v-if="active.status === 'accepted' && active.reviewedBy">
                <p class="text-sm">
                    <span class="text-muted-foreground"
                        >{{ active.reviewedBy }} ({{
                            roleLabels[active.requiredRole]
                        }}):</span
                    >
                    “{{ active.reviewReason }}”
                </p>
                <p class="text-sm font-semibold">
                    Next action: {{ active.nextAction }}
                </p>
            </template>
            <div v-if="canReview" class="flex flex-wrap gap-3">
                <Button
                    class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600"
                    @click="emit('review', true)"
                >
                    Accept
                </Button>
                <Button variant="outline" @click="emit('review', false)"
                    >Deny</Button
                >
            </div>
        </div>

        <!-- Operator chooses -->
        <template v-else>
            <div
                v-if="lastDenied"
                class="rounded-lg border border-red-500/50 p-3 text-sm"
            >
                <b
                    >{{ roleLabels[lastDenied.requiredRole] }} denied
                    {{ actionLabels[lastDenied.action] }}:</b
                >
                “{{ lastDenied.reviewReason }}” — {{ lastDenied.reviewedBy }}
            </div>
            <p v-if="batch.retrospective" class="text-sm text-muted-foreground">
                The charge time has passed; this assessment is retrospective and
                takes no new decision.
            </p>
            <p
                v-else-if="role !== 'operator'"
                class="text-sm text-muted-foreground"
            >
                Waiting for the operator to choose an action.
            </p>
            <div
                v-else
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
            >
                <div
                    v-for="option in options"
                    :key="option.action"
                    class="flex flex-col gap-1.5 rounded-xl border-2 p-3 text-sm"
                    :class="
                        option.action === batch.recommendation.action
                            ? 'border-sky-600 bg-sky-50 dark:bg-sky-950/40'
                            : ''
                    "
                >
                    <b class="text-base">
                        <span
                            v-if="option.action === batch.recommendation.action"
                            >★ </span
                        >{{ actionLabels[option.action] }}
                    </b>
                    <span class="text-muted-foreground">{{
                        option.description
                    }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{
                            option.approver === 'operator'
                                ? 'Takes effect now'
                                : `Needs ${roleLabels[option.approver]} review`
                        }}
                    </span>
                    <div class="mt-auto pt-2">
                        <Button
                            class="w-full"
                            :variant="
                                option.action === batch.recommendation.action
                                    ? 'default'
                                    : 'outline'
                            "
                            :disabled="!canChoose"
                            @click="emit('choose', option.action)"
                        >
                            Choose
                        </Button>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>
