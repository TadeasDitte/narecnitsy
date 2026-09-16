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
                <CardTitle>Endpoints</CardTitle>
                <CardDescription>
                    Read-only, authenticated with a bot token from above.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <div>
                    <p class="font-medium">GET /api/markets</p>
                    <p class="text-muted-foreground">
                        Every actively tracked market:
                        <code>id</code>, <code>symbol</code>,
                        <code>sz_decimals</code>, <code>max_leverage</code>.
                    </p>
                    <pre
                        class="bg-muted mt-2 overflow-x-auto rounded-md p-3"
                    ><code>curl -H "Authorization: Bearer &lt;token&gt;" \
  {{ apiOrigin }}/api/markets</code></pre>
                </div>

                <div>
                    <p class="font-medium">GET /api/candles</p>
                    <p class="text-muted-foreground">
                        OHLCV history for one market. Query params:
                        <code>symbol</code> (required, e.g.
                        <code>{{ exampleSymbol }}</code
                        >), <code>interval</code> (required, one of
                        {{ intervals.join(', ') }}), optional
                        <code>from</code>/<code>to</code> (epoch milliseconds).
                        Capped at 5000 rows per request.
                    </p>
                    <pre
                        class="bg-muted mt-2 overflow-x-auto rounded-md p-3"
                    ><code>curl -H "Authorization: Bearer &lt;token&gt;" \
  "{{ apiOrigin }}/api/candles?symbol={{ exampleSymbol }}&interval={{ exampleInterval }}"</code></pre>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
