<script setup lang="ts">
import { computed } from 'vue';
import { actionLabels, roleLabels } from '@/components/console/format';
import { Button } from '@/components/ui/button';
import type { ActionKey, BatchConsole, BatchDecision, Role } from '@/types/console';

const props = defineProps<{ batch: BatchConsole; role: Role }>();

const emit = defineEmits<{
    choose: [action: ActionKey];
    review: [decision: BatchDecision, accept: boolean];
}>();

const prospectLabels: Record<string, string> = {
    unknown: 'Unknown / checks pending',
    potentially_usable: 'Potentially usable after checks',
    rejected: 'Rejected for production',
};

const activeMaterial = computed(() => props.batch.activeMaterialDecision);
const activeTransport = computed(() => props.batch.activeTransportDecision);

const lastDeniedMaterial = computed(() =>
    !activeMaterial.value
        ? (props.batch.decisions.find((d) => d.lane === 'material' && d.status === 'denied') ?? null)
        : null,
);

const lastDeniedTransport = computed(() =>
    !activeTransport.value
        ? (props.batch.decisions.find((d) => d.lane === 'transport' && d.status === 'denied') ?? null)
        : null,
);

const canChooseMaterial = computed(
    () =>
        props.role === 'operator' &&
        activeMaterial.value === null &&
        !props.batch.retrospective,
);

const canChooseTransport = computed(
    () =>
        props.role === 'operator' &&
        activeTransport.value === null &&
        !props.batch.retrospective,
);

const canReviewMaterial = computed(
    () =>
        activeMaterial.value !== null &&
        activeMaterial.value.status === 'pending' &&
        activeMaterial.value.requiredRole === props.role,
);

const canReviewTransport = computed(
    () =>
        activeTransport.value !== null &&
        activeTransport.value.status === 'pending' &&
        activeTransport.value.requiredRole === props.role,
);
</script>

<template>
    <section
        class="flex flex-col gap-5 rounded-xl border-2 border-sky-600/60 bg-card p-5"
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
            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="batch.materialHeld"
                    class="rounded bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-950 dark:text-rose-300"
                >
                    Material hold active
                </span>
                <span
                    v-if="batch.recommendation.qaReviewRequired"
                    class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                >
                    Human QA review required
                </span>
            </div>
        </div>

        <!-- Independent Lanes Side by Side -->
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Lane 1: Material Disposition -->
            <div class="flex flex-col gap-3 rounded-xl border p-4 bg-muted/20">
                <div class="flex items-center justify-between border-b pb-2">
                    <span class="text-sm font-bold tracking-wide uppercase text-muted-foreground">
                        Lane 1: Material Disposition
                    </span>
                    <span
                        v-if="batch.materialHeld"
                        class="rounded border border-rose-300 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300"
                    >
                        Held / Quarantine
                    </span>
                </div>

                <!-- Active material decision -->
                <div
                    v-if="activeMaterial"
                    class="flex flex-col gap-2 rounded-lg border p-3"
                    :class="
                        activeMaterial.status === 'accepted'
                            ? 'border-emerald-600/50 bg-emerald-50/30 dark:bg-emerald-950/20'
                            : 'border-amber-500/60 bg-amber-50/30 dark:bg-amber-950/20'
                    "
                >
                    <div class="flex flex-wrap items-baseline justify-between gap-1">
                        <span class="font-semibold text-base">
                            {{ activeMaterial.submittedBy }}:
                            <span class="text-sky-700 dark:text-sky-400">
                                {{ actionLabels[activeMaterial.action] }}
                            </span>
                        </span>
                        <span
                            class="rounded px-2 py-0.5 text-xs font-semibold"
                            :class="
                                activeMaterial.status === 'accepted'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                            "
                        >
                            {{
                                activeMaterial.status === 'accepted'
                                    ? 'Accepted'
                                    : `Waiting for ${roleLabels[activeMaterial.requiredRole]}`
                            }}
                        </span>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        “{{ activeMaterial.submittedReason }}”
                    </p>

                    <div
                        v-if="activeMaterial.qaProspect"
                        class="rounded border border-sky-300 bg-sky-50 p-2 text-xs text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200"
                    >
                        <b>QA prospect:</b>
                        {{ prospectLabels[activeMaterial.qaProspect] ?? activeMaterial.qaProspect }}
                        <span class="block text-[11px] text-muted-foreground mt-0.5">
                            Preliminary check only; material remains quarantined from production.
                        </span>
                    </div>

                    <template v-if="activeMaterial.status === 'accepted' && activeMaterial.reviewedBy">
                        <p class="text-xs">
                            <span class="text-muted-foreground">{{ activeMaterial.reviewedBy }} ({{ roleLabels[activeMaterial.requiredRole] }}):</span>
                            “{{ activeMaterial.reviewReason }}”
                        </p>
                        <p v-if="activeMaterial.nextAction" class="text-xs font-semibold">
                            Next action: {{ activeMaterial.nextAction }}
                        </p>
                    </template>

                    <div v-if="canReviewMaterial" class="flex gap-2 pt-2">
                        <Button
                            size="sm"
                            class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500"
                            @click="emit('review', activeMaterial, true)"
                        >
                            Accept
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="emit('review', activeMaterial, false)"
                        >
                            Deny
                        </Button>
                    </div>
                </div>

                <!-- No active material decision -->
                <div v-else class="flex flex-col gap-2">
                    <div
                        v-if="lastDeniedMaterial"
                        class="rounded-lg border border-red-500/50 p-2 text-xs"
                    >
                        <b>{{ roleLabels[lastDeniedMaterial.requiredRole] }} denied {{ actionLabels[lastDeniedMaterial.action] }}:</b>
                        “{{ lastDeniedMaterial.reviewReason }}” — {{ lastDeniedMaterial.reviewedBy }}
                    </div>

                    <p v-if="batch.retrospective" class="text-xs text-muted-foreground">
                        Retrospective: charge time has passed.
                    </p>
                    <p v-else-if="role !== 'operator'" class="text-xs text-muted-foreground">
                        Waiting for operator material selection.
                    </p>
                    <div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <!-- QUARANTINE -->
                        <div class="flex flex-col rounded-lg border p-2 text-xs gap-1 bg-card">
                            <b class="text-sm">{{ actionLabels.QUARANTINE }}</b>
                            <span class="text-muted-foreground">Hold lot for QA checks</span>
                            <span class="text-[11px] text-amber-700 dark:text-amber-400">Needs QA review</span>
                            <div class="mt-auto pt-2">
                                <Button
                                    size="sm"
                                    class="w-full"
                                    variant="outline"
                                    :disabled="!canChooseMaterial"
                                    @click="emit('choose', 'QUARANTINE')"
                                >
                                    Quarantine
                                </Button>
                            </div>
                        </div>

                        <!-- BUFFER -->
                        <div class="flex flex-col rounded-lg border p-2 text-xs gap-1 bg-card">
                            <b class="text-sm">{{ actionLabels.BUFFER }}</b>
                            <span class="text-muted-foreground">Charge from safety stock</span>
                            <span
                                v-if="!batch.bufferAllowed"
                                class="text-[11px] text-amber-700 dark:text-amber-400 font-medium"
                            >
                                {{ batch.bufferBlockedReason }}
                            </span>
                            <span v-else class="text-[11px] text-muted-foreground">Takes effect now</span>
                            <div class="mt-auto pt-2">
                                <Button
                                    size="sm"
                                    class="w-full"
                                    variant="outline"
                                    :disabled="!canChooseMaterial || !batch.bufferAllowed"
                                    @click="emit('choose', 'BUFFER')"
                                >
                                    Buffer stock
                                </Button>
                            </div>
                        </div>

                        <!-- RUN AS PLANNED -->
                        <div class="flex flex-col rounded-lg border p-2 text-xs gap-1 bg-card">
                            <b class="text-sm">{{ actionLabels.RUN_AS_PLANNED }}</b>
                            <span class="text-muted-foreground">Charge planned lot</span>
                            <span
                                v-if="!batch.runAllowed"
                                class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold"
                            >
                                {{ batch.runBlockedReason }}
                            </span>
                            <span v-else class="text-[11px] text-muted-foreground">Takes effect now</span>
                            <div class="mt-auto pt-2">
                                <Button
                                    size="sm"
                                    class="w-full"
                                    variant="outline"
                                    :disabled="!canChooseMaterial || !batch.runAllowed"
                                    @click="emit('choose', 'RUN_AS_PLANNED')"
                                >
                                    Run planned
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lane 2: Transport Logistics -->
            <div class="flex flex-col gap-3 rounded-xl border p-4 bg-muted/20">
                <div class="flex items-center justify-between border-b pb-2">
                    <span class="text-sm font-bold tracking-wide uppercase text-muted-foreground">
                        Lane 2: Transport Logistics
                    </span>
                    <span
                        v-if="batch.materialHeld && !batch.expediteAllowed"
                        class="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300"
                    >
                        Transport gated by QA
                    </span>
                    <span
                        v-else-if="batch.materialHeld && batch.expediteAllowed"
                        class="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        QA prospect verified
                    </span>
                </div>

                <!-- Active transport decision -->
                <div
                    v-if="activeTransport"
                    class="flex flex-col gap-2 rounded-lg border p-3"
                    :class="
                        activeTransport.status === 'accepted'
                            ? 'border-emerald-600/50 bg-emerald-50/30 dark:bg-emerald-950/20'
                            : 'border-amber-500/60 bg-amber-50/30 dark:bg-amber-950/20'
                    "
                >
                    <div class="flex flex-wrap items-baseline justify-between gap-1">
                        <span class="font-semibold text-base">
                            {{ activeTransport.submittedBy }}:
                            <span class="text-sky-700 dark:text-sky-400">
                                {{ actionLabels[activeTransport.action] }}
                            </span>
                        </span>
                        <span
                            class="rounded px-2 py-0.5 text-xs font-semibold"
                            :class="
                                activeTransport.status === 'accepted'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                            "
                        >
                            {{
                                activeTransport.status === 'accepted'
                                    ? 'Accepted'
                                    : `Waiting for ${roleLabels[activeTransport.requiredRole]}`
                            }}
                        </span>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        “{{ activeTransport.submittedReason }}”
                    </p>

                    <div
                        v-if="activeTransport.status === 'accepted'"
                        class="rounded border border-amber-300 bg-amber-50/70 p-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        <b>Note:</b> Transport approval accelerates movement to controlled storage; material hold remains active.
                    </div>

                    <template v-if="activeTransport.status === 'accepted' && activeTransport.reviewedBy">
                        <p class="text-xs">
                            <span class="text-muted-foreground">{{ activeTransport.reviewedBy }} ({{ roleLabels[activeTransport.requiredRole] }}):</span>
                            “{{ activeTransport.reviewReason }}”
                        </p>
                        <p v-if="activeTransport.nextAction" class="text-xs font-semibold">
                            Next action: {{ activeTransport.nextAction }}
                        </p>
                    </template>

                    <div v-if="canReviewTransport" class="flex gap-2 pt-2">
                        <Button
                            size="sm"
                            class="bg-sky-600 text-white hover:bg-sky-700 dark:bg-sky-500"
                            @click="emit('review', activeTransport, true)"
                        >
                            Accept
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="emit('review', activeTransport, false)"
                        >
                            Deny
                        </Button>
                    </div>
                </div>

                <!-- No active transport decision -->
                <div v-else class="flex flex-col gap-2">
                    <div
                        v-if="lastDeniedTransport"
                        class="rounded-lg border border-red-500/50 p-2 text-xs"
                    >
                        <b>{{ roleLabels[lastDeniedTransport.requiredRole] }} denied {{ actionLabels[lastDeniedTransport.action] }}:</b>
                        “{{ lastDeniedTransport.reviewReason }}” — {{ lastDeniedTransport.reviewedBy }}
                    </div>

                    <p v-if="batch.retrospective" class="text-xs text-muted-foreground">
                        Retrospective: charge time has passed.
                    </p>
                    <p v-else-if="role !== 'operator'" class="text-xs text-muted-foreground">
                        Waiting for operator transport selection.
                    </p>
                    <div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <!-- EXPEDITE -->
                        <div
                            class="flex flex-col rounded-lg border p-2 text-xs gap-1 bg-card"
                            :class="!batch.expediteAllowed ? 'opacity-80' : ''"
                        >
                            <b class="text-sm">{{ actionLabels.EXPEDITE }}</b>
                            <span class="text-muted-foreground">Faster transport slot</span>
                            <span
                                v-if="!batch.expediteAllowed"
                                class="text-[11px] text-amber-700 dark:text-amber-400 font-medium"
                            >
                                {{ batch.expediteBlockedReason }}
                            </span>
                            <span
                                v-else-if="batch.materialHeld"
                                class="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium"
                            >
                                Allowed: QA confirmed potentially usable
                            </span>
                            <span v-else class="text-[11px] text-muted-foreground">Needs Logistics review</span>

                            <div class="mt-auto pt-2">
                                <Button
                                    size="sm"
                                    class="w-full"
                                    variant="outline"
                                    :disabled="!canChooseTransport || !batch.expediteAllowed"
                                    @click="emit('choose', 'EXPEDITE')"
                                >
                                    Expedite
                                </Button>
                            </div>
                        </div>

                        <!-- REROUTE -->
                        <div
                            class="flex flex-col rounded-lg border p-2 text-xs gap-1 bg-card"
                            :class="!batch.rerouteAllowed ? 'opacity-80' : ''"
                        >
                            <b class="text-sm">{{ actionLabels.REROUTE }}</b>
                            <span class="text-muted-foreground">Alternative route</span>
                            <span
                                v-if="!batch.rerouteAllowed"
                                class="text-[11px] text-amber-700 dark:text-amber-400 font-medium"
                            >
                                {{ batch.rerouteBlockedReason }}
                            </span>
                            <span
                                v-else-if="batch.materialHeld"
                                class="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium"
                            >
                                Allowed: QA confirmed potentially usable
                            </span>
                            <span v-else class="text-[11px] text-muted-foreground">Needs Logistics review</span>
                            <div class="mt-auto pt-2">
                                <Button
                                    size="sm"
                                    class="w-full"
                                    variant="outline"
                                    :disabled="!canChooseTransport || !batch.rerouteAllowed"
                                    @click="emit('choose', 'REROUTE')"
                                >
                                    Reroute
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
