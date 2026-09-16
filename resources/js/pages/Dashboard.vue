<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import MarketTrendCard from '@/components/MarketTrendCard.vue';
import PriceChart from '@/components/PriceChart.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { DashboardMarket, DashboardStats, PricePoint } from '@/types';

const props = defineProps<{
    stats: DashboardStats;
    markets: DashboardMarket[];
    intervals: string[];
    selectedSymbol: string | null;
    selectedInterval: string;
    priceHistory: PricePoint[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const formatCompact = (value: number) =>
    new Intl.NumberFormat(undefined, { notation: 'compact' }).format(value);

const selectSymbol = (symbol: string) => {
    router.get(
        dashboard.url({ query: { symbol, interval: props.selectedInterval } }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const selectInterval = (event: Event) => {
    const interval = (event.target as HTMLSelectElement).value;

    router.get(
        dashboard.url({
            query: { symbol: props.selectedSymbol, interval },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Dashboard"
            description="An at-a-glance look at what's been ingested."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader>
                    <CardDescription>Active markets</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">
                        {{ stats.active_markets }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Candles stored</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">
                        {{ formatCompact(stats.total_candles) }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Tracked intervals</CardDescription>
                    <CardTitle class="text-2xl tabular-nums">
                        {{ stats.tracked_intervals }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader>
                    <CardDescription>Last live update</CardDescription>
                    <CardTitle class="text-2xl">
                        {{ stats.last_update_diff ?? '—' }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <div>
            <h2 class="mb-3 text-sm font-medium">
                Markets (1h change, last 60 candles)
            </h2>
            <div
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <MarketTrendCard
                    v-for="market in markets"
                    :key="market.symbol"
                    :symbol="market.symbol"
                    :last-price="market.last_price"
                    :change-pct="market.change_pct"
                    :series="market.series"
                    class="cursor-pointer"
                    @click="selectSymbol(market.symbol)"
                />
                <p
                    v-if="!markets.length"
                    class="text-muted-foreground col-span-full text-sm"
                >
                    No markets tracked yet. Visit
                    <span class="font-medium">Market data</span> to backfill
                    them.
                </p>
            </div>
        </div>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle
                        >Price history{{
                            selectedSymbol ? ` — ${selectedSymbol}` : ''
                        }}</CardTitle
                    >
                    <CardDescription>Most recent 200 candles</CardDescription>
                </div>
                <select
                    :value="selectedInterval"
                    class="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                    @change="selectInterval"
                >
                    <option
                        v-for="interval in intervals"
                        :key="interval"
                        :value="interval"
                    >
                        {{ interval }}
                    </option>
                </select>
            </CardHeader>
            <CardContent>
                <PriceChart :points="priceHistory" />
            </CardContent>
        </Card>
    </div>
</template>
