<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, KeySquare, Trash2 } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/api-access/tokens';
import { index as apiAccessIndex } from '@/routes/api-access';
import type { ApiToken } from '@/types';

const props = defineProps<{
    tokens: ApiToken[];
    symbols: string[];
    intervals: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'API access', href: apiAccessIndex() }],
    },
});

const form = useForm({ name: '' });

const submit = () => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
};

const revokingId = ref<number | null>(null);

const revoke = (id: number) => {
    revokingId.value = id;
    router.delete(destroy.url(id), {
        preserveScroll: true,
        onFinish: () => {
            revokingId.value = null;
        },
    });
};

// The plaintext token is only ever available once, flashed on the redirect
// right after creation - see ApiAccessController::store().
const newToken = ref<string | null>(null);
const copied = ref(false);

const handleFlash = (event: Event) => {
    const flash = (event as CustomEvent).detail?.flash as
        | { apiToken?: string }
        | undefined;

    if (flash?.apiToken) {
        newToken.value = flash.apiToken;
        copied.value = false;
    }
};

let unsubscribeFlash: (() => void) | undefined;

onMounted(() => {
    unsubscribeFlash = router.on('flash', handleFlash);
});
onUnmounted(() => unsubscribeFlash?.());

const copyToken = async () => {
    if (!newToken.value) {
        return;
    }

    await navigator.clipboard.writeText(newToken.value);
    copied.value = true;
};

const exampleSymbol = props.symbols[0] ?? 'BTC';
const exampleInterval = props.intervals[0] ?? '1m';
const apiOrigin = window.location.origin;
</script>

<template>
    <Head title="API access" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="API access"
            description="Mint tokens for bots to query the market data this app has ingested."
        />

        <Card v-if="newToken">
            <CardHeader>
                <CardTitle>Copy your new token now</CardTitle>
                <CardDescription>
                    This is the only time it will be shown in full.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex items-center gap-2">
                    <code
                        class="bg-muted flex-1 overflow-x-auto rounded-md px-3 py-2 text-sm"
                        >{{ newToken }}</code
                    >
                    <Button variant="outline" size="sm" @click="copyToken">
                        <Check v-if="copied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Bot tokens</CardTitle>
                <CardDescription>
                    Each token authenticates as your account via
                    <code>Authorization: Bearer &lt;token&gt;</code>.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <form @submit.prevent="submit" class="flex items-end gap-3">
                    <div class="grid flex-1 gap-2">
                        <Label for="token-name">Token name</Label>
                        <Input
                            id="token-name"
                            v-model="form.name"
                            placeholder="e.g. momentum-bot-1"
                            autofocus
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <Button type="submit" :disabled="form.processing">
                        Generate token
                    </Button>
                </form>

                <div
                    v-if="tokens.length"
                    class="border-border overflow-hidden rounded-lg border"
                >
                    <div
                        v-for="token in tokens"
                        :key="token.id"
                        class="flex items-center justify-between border-b p-4 last:border-b-0"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                            >
                                <KeySquare
                                    class="text-muted-foreground h-5 w-5"
                                />
                            </div>
                            <div class="space-y-1">
                                <p class="font-medium tracking-tight">
                                    {{ token.name }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    Created {{ token.created_at_diff }}
                                    <template v-if="token.last_used_at_diff">
                                        <span
                                            class="text-muted-foreground/50 mx-1"
                                            >/</span
                                        >
                                        Last used
                                        {{ token.last_used_at_diff }}
                                    </template>
                                    <template v-else>
                                        <span
                                            class="text-muted-foreground/50 mx-1"
                                            >/</span
                                        >
                                        Never used
                                    </template>
                                </p>
                            </div>
                        </div>

                        <Dialog>
                            <DialogTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    <span class="sr-only">Revoke</span>
                                </Button>
                            </DialogTrigger>

                            <DialogContent>
                                <DialogTitle>Revoke token</DialogTitle>
                                <DialogDescription>
                                    Are you sure you want to revoke "{{
                                        token.name
                                    }}"? Any bot using it will immediately lose
                                    access.
                                </DialogDescription>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary"
                                            >Cancel</Button
                                        >
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        :disabled="revokingId === token.id"
                                        @click="revoke(token.id)"
                                    >
                                        {{
                                            revokingId === token.id
                                                ? 'Revoking...'
                                                : 'Revoke token'
                                        }}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <div v-else class="text-muted-foreground text-sm">
                    No tokens yet. Generate one above to let a bot query this
                    app's API.
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Authentication</CardTitle>
                <CardDescription
                    >Every request below needs a bot token from
                    above.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <p class="text-muted-foreground">
                    Send it as a bearer token. There's no separate API key
                    header - the token itself is the credential, and it
                    authenticates as your account.
                </p>
                <pre
                    class="bg-muted overflow-x-auto rounded-md p-3"
                ><code>Authorization: Bearer &lt;token&gt;</code></pre>
                <p class="text-muted-foreground">
                    Base URL:
                    <code class="text-foreground">{{ apiOrigin }}</code>
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>GET /api/markets</CardTitle>
                <CardDescription
                    >Every actively tracked market.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <div>
                    <p class="mb-2 font-medium">Response fields</p>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="text-muted-foreground border-b text-left"
                                >
                                    <th class="px-3 py-2 font-medium">Field</th>
                                    <th class="px-3 py-2 font-medium">Type</th>
                                    <th class="px-3 py-2 font-medium">
                                        Description
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>id</code>
                                    </td>
                                    <td class="px-3 py-2">integer</td>
                                    <td class="px-3 py-2">
                                        Use this as <code>market_id</code> if
                                        you need to cross-reference elsewhere.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>symbol</code>
                                    </td>
                                    <td class="px-3 py-2">string</td>
                                    <td class="px-3 py-2">
                                        Coin ticker, e.g. <code>BTC</code>. Pass
                                        this to <code>/api/candles</code>.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>sz_decimals</code>
                                    </td>
                                    <td class="px-3 py-2">integer | null</td>
                                    <td class="px-3 py-2">
                                        Size decimals Hyperliquid uses for this
                                        asset.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>max_leverage</code>
                                    </td>
                                    <td class="px-3 py-2">integer | null</td>
                                    <td class="px-3 py-2">
                                        Max leverage Hyperliquid allows for this
                                        asset.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <p class="mb-2 font-medium">Example</p>
                    <pre
                        class="bg-muted overflow-x-auto rounded-md p-3"
                    ><code>curl -H "Authorization: Bearer &lt;token&gt;" \
  {{ apiOrigin }}/api/markets</code></pre>
                    <pre
                        class="bg-muted mt-2 overflow-x-auto rounded-md p-3"
                    ><code>[
  {
    "id": 2,
    "symbol": "BTC",
    "sz_decimals": 5,
    "max_leverage": 40
  },
  {
    "id": 1,
    "symbol": "SOL",
    "sz_decimals": 2,
    "max_leverage": 10
  }
]</code></pre>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>GET /api/candles</CardTitle>
                <CardDescription>OHLCV history for one market.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <div>
                    <p class="mb-2 font-medium">Query parameters</p>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="text-muted-foreground border-b text-left"
                                >
                                    <th class="px-3 py-2 font-medium">Param</th>
                                    <th class="px-3 py-2 font-medium">
                                        Required
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Description
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>symbol</code>
                                    </td>
                                    <td class="px-3 py-2">Yes</td>
                                    <td class="px-3 py-2">
                                        A tracked market's symbol, e.g.
                                        <code>{{ exampleSymbol }}</code
                                        >. 404s if unknown.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>interval</code>
                                    </td>
                                    <td class="px-3 py-2">Yes</td>
                                    <td class="px-3 py-2">
                                        One of:
                                        <code
                                            v-for="(
                                                interval, index
                                            ) in intervals"
                                            :key="interval"
                                            >{{ interval
                                            }}{{
                                                index < intervals.length - 1
                                                    ? ', '
                                                    : ''
                                            }}</code
                                        >.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>from</code>
                                    </td>
                                    <td class="px-3 py-2">No</td>
                                    <td class="px-3 py-2">
                                        Epoch milliseconds. Defaults to the
                                        beginning of time (all stored rows).
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>to</code>
                                    </td>
                                    <td class="px-3 py-2">No</td>
                                    <td class="px-3 py-2">
                                        Epoch milliseconds. Defaults to now.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted-foreground mt-2">
                        Rows are ordered oldest -> newest by
                        <code>open_time</code>, capped at 5000 rows per request
                        - page through a long range with repeated
                        <code>from</code>/<code>to</code> calls.
                    </p>
                </div>

                <div>
                    <p class="mb-2 font-medium">Response fields</p>
                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="text-muted-foreground border-b text-left"
                                >
                                    <th class="px-3 py-2 font-medium">Field</th>
                                    <th class="px-3 py-2 font-medium">Type</th>
                                    <th class="px-3 py-2 font-medium">
                                        Description
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>open_time</code> /
                                        <code>close_time</code>
                                    </td>
                                    <td class="px-3 py-2">integer</td>
                                    <td class="px-3 py-2">
                                        Epoch milliseconds.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>open</code> / <code>high</code> /
                                        <code>low</code> / <code>close</code> /
                                        <code>volume</code>
                                    </td>
                                    <td class="px-3 py-2">string</td>
                                    <td class="px-3 py-2">
                                        Decimal strings (8 places) - deserialize
                                        as a decimal type, not a float, to avoid
                                        rounding drift.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>num_trades</code>
                                    </td>
                                    <td class="px-3 py-2">integer | null</td>
                                    <td class="px-3 py-2">
                                        Trade count Hyperliquid reported for the
                                        candle, when available.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">
                                        <code>is_closed</code>
                                    </td>
                                    <td class="px-3 py-2">boolean</td>
                                    <td class="px-3 py-2">
                                        <code>false</code> means the candle is
                                        still forming - its OHLCV values will
                                        keep changing on later requests until
                                        <code>close_time</code> passes.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <p class="mb-2 font-medium">Example</p>
                    <pre
                        class="bg-muted overflow-x-auto rounded-md p-3"
                    ><code>curl -H "Authorization: Bearer &lt;token&gt;" \
  "{{ apiOrigin }}/api/candles?symbol={{ exampleSymbol }}&interval={{ exampleInterval }}&from=1758000000000"</code></pre>
                    <pre
                        class="bg-muted mt-2 overflow-x-auto rounded-md p-3"
                    ><code>[
  {
    "id": 57311,
    "market_id": 2,
    "interval": "1h",
    "open_time": 1789549200000,
    "close_time": 1789552799999,
    "open": "76145.00000000",
    "high": "76457.00000000",
    "low": "76018.00000000",
    "close": "76457.00000000",
    "volume": "1.42513000",
    "num_trades": 816,
    "is_closed": true,
    "created_at": "2026-09-16T10:55:19.000000Z",
    "updated_at": "2026-09-16T10:55:19.000000Z"
  }
]</code></pre>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Errors</CardTitle>
                <CardDescription
                    >Standard Laravel JSON error shapes - nothing
                    endpoint-specific.</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <div>
                    <p class="font-medium">401 - missing or revoked token</p>
                    <pre
                        class="bg-muted mt-2 overflow-x-auto rounded-md p-3"
                    ><code>{ "message": "Unauthenticated." }</code></pre>
                </div>
                <div>
                    <p class="font-medium">404 - unknown <code>symbol</code></p>
                    <pre
                        class="bg-muted mt-2 overflow-x-auto rounded-md p-3"
                    ><code>{ "message": "No query results for model [App\\Models\\Market]." }</code></pre>
                </div>
                <div>
                    <p class="font-medium">
                        422 - missing/invalid query parameters
                    </p>
                    <pre
                        class="bg-muted mt-2 overflow-x-auto rounded-md p-3"
                    ><code>{
  "message": "The interval field is required.",
  "errors": {
    "interval": ["The interval field is required."]
  }
}</code></pre>
                </div>
                <p class="text-muted-foreground">
                    There's no rate limiting on these endpoints today - be a
                    good neighbor to yourself and don't hammer
                    <code>/api/candles</code> in a tight loop; poll on an
                    interval that matches how often the underlying data actually
                    changes.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
