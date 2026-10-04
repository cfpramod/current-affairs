<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BatchDecisionController from '@/actions/App/Http/Controllers/BatchDecisionController';
import BatchDecisionPanel from '@/components/batch/BatchDecisionPanel.vue';
import BatchEvidence from '@/components/batch/BatchEvidence.vue';
import AnswerCard from '@/components/console/AnswerCard.vue';
import {
    actionLabels,
    formatDateTime,
    formatTime,
    roleLabels,
} from '@/components/console/format';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index } from '@/routes/batches';
import type { ActionKey, BatchConsole } from '@/types/console';

const props = defineProps<{
    batch: BatchConsole | null;
    unavailable: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Batches', href: index() }] },
});

const page = usePage();
const role = computed(() => page.props.auth.user.role);
const timezone = 'Europe/Zurich';

const submitOpen = ref(false);
const submitForm = useForm({
    assessment_id: '',
    action: 'RUN_AS_PLANNED' as ActionKey,
    reason: '',
});

const reviewOpen = ref(false);
const reviewForm = useForm({
    accept: true,
    reason: '',
    next_action: '',
    responsibility_confirmed: false,
});

const submitError = computed(() => {
    const errors: Record<string, string | undefined> = submitForm.errors;

    return (
        errors.decision ??
        errors.reason ??
        errors.action ??
        errors.assessment_id
    );
});

const reviewError = computed(() => {
    const errors: Record<string, string | undefined> = reviewForm.errors;

    return errors.review ?? errors.reason ?? errors.next_action;
});

function choose(action: ActionKey): void {
    submitForm.reset();
    submitForm.clearErrors();
    submitForm.action = action;
    submitOpen.value = true;
}

function submit(): void {
    if (!props.batch) {
        return;
    }

    submitForm.assessment_id = props.batch.assessmentId;
    submitForm.submit(BatchDecisionController.store(props.batch.batchId), {
        preserveScroll: true,
        onSuccess: () => {
            submitOpen.value = false;
        },
    });
}

function openReview(accept: boolean): void {
    reviewForm.reset();
    reviewForm.clearErrors();
    reviewForm.accept = accept;
    reviewOpen.value = true;
}

function review(): void {
    const decision = props.batch?.activeDecision;

    if (!decision) {
        return;
    }

    reviewForm.submit(BatchDecisionController.review(decision.id), {
        preserveScroll: true,
        onSuccess: () => {
            reviewOpen.value = false;
        },
    });
}

function resetDemo(): void {
    if (props.batch) {
        router.visit(BatchDecisionController.destroy(props.batch.batchId), {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6">
        <Head :title="batch ? batch.label : 'Batch'" />

        <div
            v-if="unavailable || !batch"
            class="rounded-xl border border-amber-500/60 p-5 text-sm"
        >
            The data engine is unavailable, so this batch's stored assessment
            can't be shown. Start it with <code>pixi run integration-api</code>.
        </div>

        <template v-else>
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Link
                        :href="index()"
                        class="text-muted-foreground hover:text-foreground"
                    >
                        ← Batches
                    </Link>
                    <span class="font-semibold">{{ batch.label }}</span>
                    <span class="text-muted-foreground"
                        >· {{ batch.datasetId }}</span
                    >
                    <span
                        class="rounded border-2 border-dashed border-foreground/60 px-2 py-0.5 text-xs font-semibold"
                    >
                        SIMULATED OPERATIONS
                    </span>
                </div>
                <span
                    class="rounded-lg border px-2.5 py-1 text-sm text-muted-foreground"
                >
                    Signed in as
                    <b class="text-foreground">{{ roleLabels[role] }}</b>
                </span>
            </header>

            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h1 class="text-2xl font-bold tracking-tight">
                    {{ batch.label }} charges
                    {{ batch.reactor ?? 'the reactor' }} at
                    {{ formatTime(batch.chargeAt, timezone) }} Basel
                </h1>
                <div class="text-sm text-muted-foreground">
                    assessment as of {{ formatDateTime(batch.asOf, timezone) }}
                </div>
            </div>

            <p
                v-if="batch.retrospective"
                class="rounded-lg bg-muted px-4 py-2 text-sm"
            >
                Retrospective: the charge time was before this assessment's
                cutoff, so it can't establish readiness at the time.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <AnswerCard :answer="batch.answers.arrival" />
                <AnswerCard :answer="batch.answers.quality" />
            </div>

            <BatchDecisionPanel
                :batch="batch"
                :role="role"
                @choose="choose"
                @review="openReview"
            />

            <BatchEvidence :batch="batch" />

            <section class="flex flex-col gap-3 rounded-xl border bg-card p-5">
                <h3 class="text-lg font-semibold">Decision history</h3>
                <p
                    v-if="batch.decisions.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No decisions recorded for this batch yet.
                </p>
                <ul v-else class="flex flex-col divide-y">
                    <li
                        v-for="decision in batch.decisions"
                        :key="decision.id"
                        class="flex flex-col gap-1 py-2 text-sm"
                    >
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <span class="text-muted-foreground">{{
                                formatDateTime(decision.submittedAt, timezone)
                            }}</span>
                            <b>{{ actionLabels[decision.action] }}</b>
                            <span class="text-muted-foreground"
                                >by {{ decision.submittedBy }}</span
                            >
                            <span
                                class="rounded px-1.5 py-0.5 text-xs font-semibold"
                                :class="{
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300':
                                        decision.status === 'accepted',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300':
                                        decision.status === 'pending',
                                    'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300':
                                        decision.status === 'denied',
                                }"
                            >
                                {{ decision.status.toUpperCase() }}
                            </span>
                        </div>
                        <span class="text-muted-foreground"
                            >“{{ decision.submittedReason }}”</span
                        >
                        <span
                            v-if="decision.reviewedBy"
                            class="text-muted-foreground"
                        >
                            {{ decision.reviewedBy }} ({{
                                roleLabels[decision.requiredRole]
                            }}): “{{ decision.reviewReason }}”
                            <template v-if="decision.nextAction">
                                · next: {{ decision.nextAction }}</template
                            >
                        </span>
                        <span class="font-mono text-xs text-muted-foreground">
                            assessment {{ decision.assessmentId.slice(0, 12) }}…
                        </span>
                    </li>
                </ul>
                <button
                    v-if="batch.decisions.length > 0"
                    type="button"
                    class="self-end text-xs text-muted-foreground underline-offset-2 hover:underline"
                    @click="resetDemo"
                >
                    Reset demo decisions
                </button>
            </section>

            <details class="rounded-xl border bg-card p-5 text-sm">
                <summary class="cursor-pointer font-semibold">
                    Audit details
                </summary>
                <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                    <dt class="text-muted-foreground">Assessment</dt>
                    <dd class="font-mono break-all">
                        {{ batch.assessmentId }}
                    </dd>
                    <dt class="text-muted-foreground">Model</dt>
                    <dd>{{ batch.assessment.model_version }}</dd>
                    <dt class="text-muted-foreground">Cutoff</dt>
                    <dd>{{ formatDateTime(batch.asOf, timezone) }}</dd>
                </dl>
                <ul class="mt-3 list-disc pl-5 text-muted-foreground">
                    <li
                        v-for="limitation in batch.limitations"
                        :key="limitation"
                    >
                        {{ limitation }}
                    </li>
                </ul>
            </details>

            <Dialog v-model:open="submitOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle
                            >Choose
                            {{ actionLabels[submitForm.action] }}</DialogTitle
                        >
                        <DialogDescription>
                            Recorded against assessment
                            {{ batch.assessmentId.slice(0, 12) }}…
                            <template v-if="submitForm.action === 'QUARANTINE'">
                                QA reviews this request.</template
                            >
                            <template
                                v-else-if="
                                    ['EXPEDITE', 'REROUTE'].includes(
                                        submitForm.action,
                                    )
                                "
                            >
                                Logistics reviews this request.</template
                            >
                        </DialogDescription>
                    </DialogHeader>
                    <label class="flex flex-col gap-1.5 text-sm">
                        <span class="font-medium">Reason (required)</span>
                        <textarea
                            v-model="submitForm.reason"
                            v-focus
                            rows="3"
                            class="rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Why this action?"
                        />
                    </label>
                    <p
                        v-if="submitError"
                        class="text-sm text-red-600 dark:text-red-400"
                        role="alert"
                    >
                        {{ submitError }}
                    </p>
                    <DialogFooter>
                        <Button variant="outline" @click="submitOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            :disabled="
                                submitForm.reason.trim() === '' ||
                                submitForm.processing
                            "
                            @click="submit"
                        >
                            Submit {{ actionLabels[submitForm.action] }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="reviewOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {{ reviewForm.accept ? 'Accept' : 'Deny' }}
                            {{
                                batch.activeDecision
                                    ? actionLabels[batch.activeDecision.action]
                                    : ''
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                reviewForm.accept
                                    ? 'State what your team will do next and confirm responsibility.'
                                    : 'The request returns to the operator with your reason.'
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <label class="flex flex-col gap-1.5 text-sm">
                        <span class="font-medium">Reason (required)</span>
                        <textarea
                            v-model="reviewForm.reason"
                            v-focus
                            rows="3"
                            class="rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                    </label>
                    <template v-if="reviewForm.accept">
                        <label class="flex flex-col gap-1.5 text-sm">
                            <span class="font-medium"
                                >Next action (required)</span
                            >
                            <input
                                v-model="reviewForm.next_action"
                                class="rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                placeholder="e.g. Block lot 002, sample on arrival"
                            />
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="reviewForm.responsibility_confirmed"
                                type="checkbox"
                            />
                            My team will take this action.
                        </label>
                    </template>
                    <p
                        v-if="reviewError"
                        class="text-sm text-red-600 dark:text-red-400"
                        role="alert"
                    >
                        {{ reviewError }}
                    </p>
                    <DialogFooter>
                        <Button variant="outline" @click="reviewOpen = false"
                            >Cancel</Button
                        >
                        <Button
                            :disabled="
                                reviewForm.reason.trim() === '' ||
                                (reviewForm.accept &&
                                    (reviewForm.next_action.trim() === '' ||
                                        !reviewForm.responsibility_confirmed)) ||
                                reviewForm.processing
                            "
                            @click="review"
                        >
                            {{
                                reviewForm.accept
                                    ? 'Accept request'
                                    : 'Deny request'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </template>
    </div>
</template>
