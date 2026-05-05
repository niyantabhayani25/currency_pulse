<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Currency\UpdateCurrenciesRequest;
use App\Models\Currency;
use App\Models\UserCurrency;
use App\Services\FrankfurterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly FrankfurterService $frankfurter,
    ) {}

    /**
     * Return all currencies, the user's current selection, and live rates.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // USD is the base currency — exclude it from the selectable target list.
        $currencies  = Currency::where('code', '!=', 'USD')->orderBy('code')->get(['id', 'code', 'name', 'symbol']);
        $selectedIds = $user->currencies()->pluck('currency_id')->all();

        $rates = null;

        if (!empty($selectedIds)) {
            $codes = Currency::whereIn('id', $selectedIds)->pluck('code')->all();
            $rates = $this->frankfurter->getLatestRates($codes);
        }

        return response()->json([
            'currencies'   => $currencies,
            'selected_ids' => $selectedIds,
            'rates'        => $rates,
        ]);
    }

    /**
     * Sync the authenticated user's currency selection (1–5 currencies).
     */
    public function update(UpdateCurrenciesRequest $request): JsonResponse
    {
        $user        = $request->user();
        $currencyIds = $request->validated('currency_ids');

        DB::transaction(function () use ($user, $currencyIds) {
            UserCurrency::where('user_id', $user->id)->delete();

            $now  = now();
            $rows = array_map(
                fn (int $id) => [
                    'user_id'     => $user->id,
                    'currency_id' => $id,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                $currencyIds,
            );

            UserCurrency::insert($rows);
        });

        return response()->json(['selected_ids' => $currencyIds]);
    }
}
