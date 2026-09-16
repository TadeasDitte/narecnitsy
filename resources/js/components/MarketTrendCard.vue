<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    symbol: string;
    lastPrice: string | null;
    changePct: number | null;
    series: number[];
}>();

const direction = computed(() => {
    if (props.changePct === null) {
        return null;
    }

    return props.changePct >= 0 ? 'up' : 'down';
});

const width = 120;
const height = 32;

const pathD = computed(() => {
    if (props.series.length < 2) {
        return '';
    }

    const min = Math.min(...props.series);
    const max = Math.max(...props.series);
    const range = max - min || 1;

    return props.series
        .map((value, index) => {
            const x = (index / (props.series.length - 1)) * width;
            const y = height - ((value - min) / range) * height;

            return `${index === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .join(' ');
});
</script>

<template>
    <div class="border-border rounded-lg border p-4">
        <div class="flex items-center justify-between">
            <p class="font-medium">{{ symbol }}</p>
            <span
                v-if="changePct !== null"
                class="inline-flex items-center gap-1 text-xs font-medium"
                :style="{
                    color:
                        direction === 'up'
                            ? 'var(--status-good)'
                            : 'var(--status-critical)',
                }"
            >
                <ArrowUp v-if="direction === 'up'" class="h-3 w-3" />
                <ArrowDown v-else class="h-3 w-3" />
                {{ Math.abs(changePct).toFixed(2) }}%
            </span>
        </div>
        <p class="mt-1 text-xl font-semibold tabular-nums">
            {{ lastPrice ?? '—' }}
        </p>
        <svg
            v-if="pathD"
            :viewBox="`0 0 ${width} ${height}`"
            class="mt-2 h-8 w-full"
            preserveAspectRatio="none"
        >
            <path
                :d="pathD"
                fill="none"
                stroke="var(--chart-accent)"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
        </svg>
        <p v-else class="text-muted-foreground mt-2 text-xs">
            Not enough data yet
        </p>
    </div>
</template>
