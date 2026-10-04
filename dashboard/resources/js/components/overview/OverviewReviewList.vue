<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { actionLabels, formatDateTime } from '@/components/console/format';
import { show } from '@/routes/batches';
import type { BatchReviewItem } from '@/types/console';

defineProps<{
    title: string;
    empty: string;
    items: BatchReviewItem[];
    emphasis?: boolean;
}>();
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
            <li v-for="item in items" :key="item.id">
                <Link
                    :href="show(item.batchId)"
                    class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2 text-sm hover:text-sky-700 dark:hover:text-sky-400"
                >
                    <span class="flex flex-col gap-0.5">
                        <span>
                            <b>{{ item.label }}</b> ·
                            {{ actionLabels[item.action] }} requested by
                            {{ item.submittedBy }}
                            <span
                                v-if="item.modelAction !== item.action"
                                class="text-xs text-amber-700 dark:text-amber-400"
                            >
                                ({{
                                    item.modelAction
                                        ? 'overrides model'
                                        : 'no model action'
                                }})
                            </span>
                        </span>
                        <span
                            v-if="item.status === 'pending'"
                            class="text-muted-foreground"
                            >“{{ item.submittedReason }}”</span
                        >
                        <span
                            v-else
                            class="text-xs"
                            :class="
                                item.status === 'accepted'
                                    ? 'text-emerald-700 dark:text-emerald-400'
                                    : 'text-red-700 dark:text-red-400'
                            "
                        >
                            {{
                                item.status === 'accepted'
                                    ? 'Accepted'
                                    : 'Denied'
                            }}
                            · “{{ item.reviewReason }}”
                            <template v-if="item.nextAction">
                                · next: {{ item.nextAction }}</template
                            >
                        </span>
                    </span>
                    <span class="text-muted-foreground">
                        <template v-if="item.chargeAt"
                            >charge
                            {{ formatDateTime(item.chargeAt, 'Europe/Zurich') }}
                        </template>
                    </span>
                </Link>
            </li>
        </ul>
    </section>
</template>
