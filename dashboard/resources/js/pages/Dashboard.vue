<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { roleLabels } from '@/components/console/format';
import OverviewBatchList from '@/components/overview/OverviewBatchList.vue';
import OverviewReviewList from '@/components/overview/OverviewReviewList.vue';
import { dashboard } from '@/routes';
import type { RoleOverview } from '@/types/console';

defineProps<{ overview: RoleOverview }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Overview', href: dashboard() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5 p-4 md:p-6">
        <Head title="Overview" />

        <div>
            <h1 class="text-2xl font-bold tracking-tight">
                {{ user.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ roleLabels[overview.role] }} overview
            </p>
        </div>

        <div
            v-if="overview.engineUnavailable"
            class="rounded-xl border border-amber-500/60 p-4 text-sm"
        >
            The data engine is unavailable, so batch evidence can't be shown.
            Start it with <code>bash scripts/demo.sh</code>.
        </div>

        <template v-if="overview.role === 'operator'">
            <OverviewBatchList
                title="Needs your decision"
                empty="Every upcoming batch has a decision."
                :items="overview.needsDecision"
                emphasis
            />
            <div class="grid gap-5 lg:grid-cols-2">
                <OverviewBatchList
                    title="Waiting on others"
                    empty="No requests waiting for QA or logistics."
                    :items="overview.waitingOnOthers"
                />
                <OverviewBatchList
                    title="Next charges"
                    empty="No upcoming batches."
                    :items="overview.nextCharges"
                />
            </div>
        </template>

        <template v-else>
            <OverviewReviewList
                :title="`Waiting for your review`"
                empty="Nothing waiting for you."
                :items="overview.reviewQueue"
                emphasis
            />
            <div class="grid gap-5 lg:grid-cols-2">
                <OverviewBatchList
                    :title="
                        overview.role === 'qa'
                            ? 'Temperature watchlist'
                            : 'Incoming at risk'
                    "
                    :empty="
                        overview.role === 'qa'
                            ? 'No upcoming batch has a temperature concern.'
                            : 'No upcoming batch depends on a late shipment.'
                    "
                    :items="overview.watchlist"
                />
                <OverviewReviewList
                    title="Recently reviewed by you"
                    empty="You haven't reviewed a request yet."
                    :items="overview.recentlyReviewed"
                />
            </div>
        </template>
    </div>
</template>
