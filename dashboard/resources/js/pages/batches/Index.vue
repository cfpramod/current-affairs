<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    actionLabels,
    formatDateTime,
    roleLabels,
} from '@/components/console/format';
import { index, show } from '@/routes/batches';
import type { BatchListItem, Verdict } from '@/types/console';

defineProps<{ batches: BatchListItem[]; unavailable: boolean }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Batches', href: index() }] },
});

const page = usePage();
const role = computed(() => page.props.auth.user.role);

const verdictDot: Record<Verdict, string> = {
    yes: 'bg-emerald-500',
    no: 'bg-red-500',
    at_risk: 'bg-amber-500',
    unknown: 'bg-muted-foreground/40',
};

function needsMe(item: BatchListItem): boolean {
    if (role.value === 'operator') {
        return (
            !item.retrospective &&
            (item.decision === null || item.decision.status === 'denied')
        );
    }

    return (
        item.decision?.status === 'pending' &&
        item.decision.requiredRole === role.value
    );
}

function decisionLabel(item: BatchListItem): string {
    const decision = item.decision;

    if (decision === null) {
        return item.retrospective ? 'Retrospective' : 'Awaiting operator';
    }

    return {
        pending: `${actionLabels[decision.action]} · waiting for ${roleLabels[decision.requiredRole]}`,
        accepted: `${actionLabels[decision.action]} · accepted`,
        denied: `${actionLabels[decision.action]} · denied`,
    }[decision.status];
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6">
        <Head title="Batches" />

        <div>
            <h1 class="text-2xl font-bold tracking-tight">Batches</h1>
            <p class="text-sm text-muted-foreground">
                Production batches with a stored assessment, soonest charge
                first.
            </p>
        </div>

        <div
            v-if="unavailable"
            class="rounded-xl border border-amber-500/60 p-5 text-sm"
        >
            The data engine is unavailable. Start it with
            <code>pixi run integration-api</code>.
        </div>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">Charge</th>
                        <th class="px-4 py-2 font-medium">Batch</th>
                        <th class="px-4 py-2 font-medium">Ready? · Good?</th>
                        <th class="px-4 py-2 font-medium">Model</th>
                        <th class="px-4 py-2 font-medium">Decision</th>
                        <th class="px-4 py-2" />
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="item in batches"
                        :key="item.batchId"
                        class="align-top"
                        :class="
                            needsMe(item)
                                ? 'bg-sky-50/60 dark:bg-sky-950/20'
                                : ''
                        "
                    >
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ formatDateTime(item.chargeAt, 'Europe/Zurich') }}
                            <div class="text-xs text-muted-foreground">
                                {{ item.reactor }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-semibold">{{ item.label }}</span>
                            <div class="text-xs text-muted-foreground">
                                {{ item.datasetId }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex gap-3">
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
                                        answer.verdict === 'at_risk'
                                            ? 'risk'
                                            : answer.verdict
                                    }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{
                                item.recommendation.action
                                    ? actionLabels[item.recommendation.action]
                                    : 'No supported action'
                            }}
                            <div
                                v-if="item.recommendation.qaReviewRequired"
                                class="text-xs text-amber-700 dark:text-amber-400"
                            >
                                QA review required
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            {{ decisionLabel(item) }}
                            <div
                                v-if="needsMe(item)"
                                class="text-xs font-semibold text-sky-700 dark:text-sky-400"
                            >
                                {{
                                    role === 'operator'
                                        ? 'Needs your decision'
                                        : 'Needs your review'
                                }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="show(item.batchId)"
                                class="text-sm font-medium text-sky-700 hover:underline dark:text-sky-400"
                            >
                                Open
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
