<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApiTokenStoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiAccessController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()->tokens()
            ->latest()
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'created_at_diff' => $token->created_at?->diffForHumans(),
                'last_used_at_diff' => $token->last_used_at?->diffForHumans(),
            ])
            ->values();

        return Inertia::render('ApiAccess', [
            'tokens' => $tokens,
            'symbols' => config('hyperliquid.symbols'),
            'intervals' => config('hyperliquid.intervals'),
        ]);
    }

    public function store(ApiTokenStoreRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken($request->string('name')->toString());

        Inertia::flash('apiToken', $token->plainTextToken);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'API token created. Copy it now - it will not be shown again.']);

        return back();
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->findOrFail($token)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'API token revoked.']);

        return back();
    }
}
