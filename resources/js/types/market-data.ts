export type MarketSummary = {
    id: number;
    symbol: string;
    max_leverage: number | null;
    last_price: string | null;
    candle_count: number;
    updated_at_diff: string | null;
};

export type Candle = {
    open_time: number;
    close_time: number;
    open: string;
    high: string;
    low: string;
    close: string;
    volume: string;
    is_closed: boolean;
};

export type ApiToken = {
    id: number;
    name: string;
    created_at_diff: string | null;
    last_used_at_diff: string | null;
};
