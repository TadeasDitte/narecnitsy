<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { backfill, index as marketDataIndex } from '@/routes/market-data';
import type { Candle, MarketSummary } from '@/types';

const props = defineProps<{
    markets: MarketSummary[];
    intervals: string[];
    selectedSymbol: string | null;
    selectedInterval: string;
    candles: Candle[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Market data', href: marketDataIndex() }],
    },
});

const selectMarket = (symbol: string) => {
    router.get(
        marketDataIndex.url({
            query: { symbol, interval: props.selectedInterval },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const selectInterval = (event: Event) => {
    const interval = (event.target as HTMLSelectElement).value;

    router.get(
        marketDataIndex.url({
            query: { symbol: props.selectedSymbol, interval },
        }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formatTime = (ms: number) => new Date(ms).toLocaleString();

const backfilling = ref(false);

const triggerBackfill = () => {
    backfilling.value = true;
    router.post(
        backfill.url(),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                backfilling.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Market data" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Market data"
                description="What this app has ingested from Hyperliquid so far."
            />
            <Button
                variant="outline"
                :disabled="backfilling"
                @click="triggerBackfill"
            >
                {{ backfilling ? 'Queuing...' : 'Backfill all markets' }}
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="market in markets"
                :key="market.id"
                :class="[
                    'cursor-pointer transition-colors',
                    market.symbol === selectedSymbol
                        ? 'border-primary'
                        : 'hover:border-primary/50',
                ]"
                @click="selectMarket(market.symbol)"
            >
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        {{ market.symbol }}
                        <Badge v-if="market.max_leverage" variant="secondary">
                            {{ market.max_leverage }}x
                        </Badge>
                    </CardTitle>
                    <CardDescription>
                        {{ market.candle_count.toLocaleString() }} candles
                        stored
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ market.last_price ?? '—' }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        <template v-if="market.updated_at_diff">
                            Updated {{ market.updated_at_diff }}
                        </template>
                        <template v-else>No candles yet</template>
                    </p>
                </CardContent>
            </Card>

            <div
                v-if="!markets.length"
                class="text-muted-foreground col-span-full text-sm"
            >
                No markets tracked yet. Click "Backfill all markets" above, or
                run
                <code>php artisan hyperliquid:backfill --all</code>.
            </div>
        </div>

        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle
                        >Recent candles{{
                            selectedSymbol ? ` — ${selectedSymbol}` : ''
                        }}</CardTitle
                    >
                    <CardDescription
                        >Most recent 100, newest first</CardDescription
                    >
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
            <CardContent class="overflow-x-auto">
                <table v-if="candles.length" class="w-full text-sm">
                    <thead>
                        <tr class="text-muted-foreground border-b text-left">
                            <th class="pr-4 pb-2 font-medium">Open time</th>
                            <th class="pr-4 pb-2 font-medium">Open</th>
                            <th class="pr-4 pb-2 font-medium">High</th>
                            <th class="pr-4 pb-2 font-medium">Low</th>
                            <th class="pr-4 pb-2 font-medium">Close</th>
                            <th class="pr-4 pb-2 font-medium">Volume</th>
                            <th class="pb-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="candle in candles"
                            :key="candle.open_time"
                            class="border-b last:border-b-0"
                        >
                            <td class="py-2 pr-4 whitespace-nowrap">
                                {{ formatTime(candle.open_time) }}
                            </td>
                            <td class="py-2 pr-4 tabular-nums">
                                {{ candle.open }}
                            </td>
                            <td class="py-2 pr-4 tabular-nums">
                                {{ candle.high }}
                            </td>
                            <td class="py-2 pr-4 tabular-nums">
                                {{ candle.low }}
                            </td>
                            <td class="py-2 pr-4 tabular-nums">
                                {{ candle.close }}
                            </td>
                            <td class="py-2 pr-4 tabular-nums">
                                {{ candle.volume }}
                            </td>
                            <td class="py-2">
                                <Badge
                                    :variant="
                                        candle.is_closed
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                >
                                    {{ candle.is_closed ? 'closed' : 'live' }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-muted-foreground text-sm">
                    No candles for this market/interval yet.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
