<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Http\JsonResponse;

class MarketController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Market::active()->get(['id', 'symbol', 'sz_decimals', 'max_leverage'])
        );
    }
}
