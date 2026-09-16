<script setup lang="ts">
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        points: { t: number; v: number }[];
        height?: number;
    }>(),
    {
        height: 240,
    },
);

const width = 800;
const padding = { top: 16, right: 16, bottom: 16, left: 16 };

const plotWidth = width - padding.left - padding.right;
const plotHeight = computed(() => props.height - padding.top - padding.bottom);

const values = computed(() => props.points.map((point) => point.v));
const minValue = computed(() =>
    values.value.length ? Math.min(...values.value) : 0,
);
const maxValue = computed(() =>
    values.value.length ? Math.max(...values.value) : 1,
);

const xScale = (index: number) => {
    const n = props.points.length - 1 || 1;

    return padding.left + (index / n) * plotWidth;
};

const yScale = (value: number) => {
    const range = maxValue.value - minValue.value || 1;

    return (
        padding.top + (1 - (value - minValue.value) / range) * plotHeight.value
    );
};

const pathD = computed(() =>
    props.points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'} ${xScale(index).toFixed(2)} ${yScale(point.v).toFixed(2)}`,
        )
        .join(' '),
);

const hoverIndex = ref<number | null>(null);

const onMove = (event: MouseEvent) => {
    if (!props.points.length) {
        return;
    }

    const svg = event.currentTarget as SVGSVGElement;
    const rect = svg.getBoundingClientRect();
    const relX = ((event.clientX - rect.left) / rect.width) * width;
    const n = props.points.length - 1 || 1;
    const rawIndex = Math.round(((relX - padding.left) / plotWidth) * n);

    hoverIndex.value = Math.min(Math.max(rawIndex, 0), props.points.length - 1);
};

const onLeave = () => {
    hoverIndex.value = null;
};

const hoveredPoint = computed(() =>
    hoverIndex.value !== null ? props.points[hoverIndex.value] : null,
);

const tooltipLeftPct = computed(() => {
    if (hoverIndex.value === null) {
        return 0;
    }

    return (xScale(hoverIndex.value) / width) * 100;
});

const formatTime = (ms: number) =>
    new Date(ms).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

const formatValue = (value: number) =>
    value.toLocaleString(undefined, { maximumFractionDigits: 4 });
</script>

<template>
    <div class="relative">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            preserveAspectRatio="none"
            class="w-full"
            :style="{ height: `${height}px` }"
            @mousemove="onMove"
            @mouseleave="onLeave"
        >
            <line
                :x1="padding.left"
                :x2="width - padding.right"
                :y1="height - padding.bottom"
                :y2="height - padding.bottom"
                class="stroke-border"
                stroke-width="1"
            />

            <path
                v-if="pathD"
                :d="pathD"
                fill="none"
                stroke="var(--chart-accent)"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <template v-if="hoverIndex !== null && hoveredPoint">
                <line
                    :x1="xScale(hoverIndex)"
                    :x2="xScale(hoverIndex)"
                    :y1="padding.top"
                    :y2="height - padding.bottom"
                    class="stroke-muted-foreground"
                    stroke-width="1"
                    stroke-dasharray="3 3"
                />
                <circle
                    :cx="xScale(hoverIndex)"
                    :cy="yScale(hoveredPoint.v)"
                    r="4"
                    fill="var(--chart-accent)"
                    class="stroke-card"
                    stroke-width="2"
                />
            </template>
        </svg>

        <div
            v-if="hoveredPoint"
            class="border-border bg-popover text-popover-foreground pointer-events-none absolute top-2 -translate-x-1/2 rounded-md border px-2 py-1 text-xs whitespace-nowrap shadow-md"
            :style="{ left: `${tooltipLeftPct}%` }"
        >
            <p class="font-semibold tabular-nums">
                {{ formatValue(hoveredPoint.v) }}
            </p>
            <p class="text-muted-foreground">
                {{ formatTime(hoveredPoint.t) }}
            </p>
        </div>

        <p
            v-if="!points.length"
            class="text-muted-foreground absolute inset-0 flex items-center justify-center text-sm"
        >
            No candles for this market/interval yet.
        </p>
    </div>
</template>
