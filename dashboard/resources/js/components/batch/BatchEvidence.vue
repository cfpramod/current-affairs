<script setup lang="ts">
import TemperatureChart from '@/components/console/TemperatureChart.vue';
import {
    formatBaselTimeShort,
    formatKg,
    formatMinutes,
    formatProbability,
    incomingStatusLabel,
    productionStatusLabel,
    shipmentLabel,
} from '@/lib/batch-display';
import type { BatchConsole } from '@/types/console';

defineProps<{ batch: BatchConsole }>();

const temperatureLabels = {
    UNAVAILABLE: 'No readings',
    OBSERVED_WITHIN_BUDGET: 'Within budget',
    OBSERVED_BUDGET_EXCEEDED: 'Budget exceeded',
} as const;
</script>

<template>
    <section class="grid gap-4 lg:grid-cols-2">
        <div class="flex flex-col gap-3 rounded-xl border bg-card p-5">
            <div>
                <div
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Production readiness
                </div>
                <h3 class="mt-1 text-lg font-semibold">
                    {{ productionStatusLabel(batch.production.status) }}
                </h3>
            </div>
            <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 text-sm">
                <dt class="text-muted-foreground">Required</dt>
                <dd class="text-right tabular-nums">
                    {{ formatKg(batch.production.required_quantity_kg) }}
                </dd>
                <dt class="text-muted-foreground">Released &amp; reserved</dt>
                <dd class="text-right tabular-nums">
                    {{
                        formatKg(batch.production.released_reserved_quantity_kg)
                    }}
                </dd>
                <dt class="text-muted-foreground">Shortfall</dt>
                <dd
                    class="text-right tabular-nums"
                    :class="
                        batch.production.reservation_shortfall_kg > 0
                            ? 'font-semibold text-amber-700 dark:text-amber-400'
                            : ''
                    "
                >
                    {{ formatKg(batch.production.reservation_shortfall_kg) }}
                </dd>
                <dt class="text-muted-foreground">From incoming shipment</dt>
                <dd class="text-right tabular-nums">
                    {{ formatKg(batch.production.incoming_dependency_kg) }}
                </dd>
                <dt class="text-muted-foreground">Not covered</dt>
                <dd
                    class="text-right tabular-nums"
                    :class="
                        batch.production.uncovered_quantity_kg > 0
                            ? 'font-semibold text-red-700 dark:text-red-400'
                            : ''
                    "
                >
                    {{ formatKg(batch.production.uncovered_quantity_kg) }}
                </dd>
            </dl>
            <p class="text-xs text-muted-foreground">
                Pending-QA stock and stock reserved for other batches cannot
                cover this batch.
            </p>
        </div>

        <div class="flex flex-col gap-3 rounded-xl border bg-card p-5">
            <div>
                <div
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Incoming shipment
                </div>
                <h3
                    v-if="batch.incoming.length === 0"
                    class="mt-1 text-lg font-semibold"
                >
                    None needed
                </h3>
            </div>
            <div
                v-for="shipment in batch.incoming"
                :key="shipment.shipment_id"
                class="flex flex-col gap-1.5 text-sm"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <span class="font-semibold">{{
                        shipmentLabel(shipment.shipment_id)
                    }}</span>
                    <span
                        class="rounded bg-muted px-1.5 py-0.5 text-xs font-semibold"
                    >
                        {{ incomingStatusLabel(shipment.status) }}
                    </span>
                </div>
                <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5">
                    <dt class="text-muted-foreground">Needed from it</dt>
                    <dd class="text-right tabular-nums">
                        {{ formatKg(shipment.dependency_quantity_kg) }}
                    </dd>
                    <dt class="text-muted-foreground">Planned arrival</dt>
                    <dd
                        class="text-right"
                        :class="
                            shipment.scheduled_after_charge
                                ? 'font-semibold text-red-700 dark:text-red-400'
                                : ''
                        "
                    >
                        {{ formatBaselTimeShort(shipment.planned_arrival_at) }}
                    </dd>
                    <dt class="text-muted-foreground">On-time chance</dt>
                    <dd class="text-right tabular-nums">
                        {{
                            formatProbability(
                                shipment.on_time_arrival_probability,
                            )
                        }}
                    </dd>
                </dl>
                <p
                    v-if="shipment.scheduled_after_charge"
                    class="text-xs font-semibold text-red-700 dark:text-red-400"
                >
                    Planned to arrive after the charge time.
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ shipment.reason }}
                </p>
            </div>
        </div>

        <div
            class="order-first grid gap-4 rounded-xl border bg-card p-5 md:grid-cols-[18rem_1fr] lg:col-span-2"
        >
            <div class="flex flex-col gap-3">
                <div
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Product temperature · QA evidence
                </div>
                <p
                    v-if="batch.temperatures.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No supplying shipment has readings yet.
                </p>
                <div
                    v-for="evidence in batch.temperatures"
                    :key="evidence.shipment_id"
                    class="flex flex-col gap-1.5 text-sm"
                >
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="font-semibold">{{
                            shipmentLabel(evidence.shipment_id)
                        }}</span>
                        <span
                            class="rounded px-1.5 py-0.5 text-xs font-semibold"
                            :class="
                                evidence.status === 'OBSERVED_BUDGET_EXCEEDED'
                                    ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
                                    : evidence.status ===
                                        'OBSERVED_WITHIN_BUDGET'
                                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                      : 'bg-muted'
                            "
                        >
                            {{ temperatureLabels[evidence.status] }}
                        </span>
                    </div>
                    <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5">
                        <dt class="text-muted-foreground">
                            Outside {{ evidence.range_c[0] }}–{{
                                evidence.range_c[1]
                            }}
                            °C
                        </dt>
                        <dd class="text-right tabular-nums">
                            {{ formatMinutes(evidence.observed_excursion_min) }}
                            of {{ evidence.budget_min }} min
                        </dd>
                        <dt class="text-muted-foreground">Last reading</dt>
                        <dd class="text-right tabular-nums">
                            {{
                                evidence.last_product_c === null
                                    ? 'Unavailable'
                                    : `${evidence.last_product_c.toFixed(1)} °C`
                            }}
                        </dd>
                        <dt class="text-muted-foreground">Readings</dt>
                        <dd class="text-right tabular-nums">
                            {{ evidence.reading_count }}
                        </dd>
                    </dl>
                </div>
                <p class="text-xs text-muted-foreground">
                    The assessment never authorizes QA release; a person
                    decides.
                </p>
            </div>
            <div class="flex flex-col gap-3">
                <p
                    v-if="batch.temperatureSeries.length > 0"
                    class="text-sm text-muted-foreground"
                >
                    Product temperature ·
                    {{ batch.temperatures[0]?.range_c[0] ?? 2 }}–{{
                        batch.temperatures[0]?.range_c[1] ?? 8
                    }}
                    °C band · amber = unrefrigerated
                </p>
                <div
                    v-for="series in batch.temperatureSeries"
                    :key="series.shipmentId"
                    class="rounded-lg border p-2"
                >
                    <TemperatureChart
                        :points="series.points"
                        :exposures="series.exposures"
                        :band="[2, 8]"
                        :now="batch.asOf"
                        timezone="Europe/Zurich"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
