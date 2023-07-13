<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Price;
use App\Models\Account;
use App\Models\Product;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MatchPrice
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->validate([
            'sku' => ['required', 'string', 'uppercase'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $productSku = $request->input('sku');
        $accountId = $request->input('account_id');

        $product = Product::where('sku', $productSku)->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $livePrices = $this->getLivePrices();

        $matchingLivePrice = $this->findMatchingLivePrice($livePrices, $productSku, $accountId);

        if ($matchingLivePrice) {
            $price = $matchingLivePrice['price'];
        } else {
            $priceQuery = Price::where('product_id', $product->id);
            $price = $this->getPriceForProduct($priceQuery, $accountId);

            if ($price === null) {
                $price = $this->getLowestPublicPriceFromLivePrices($livePrices, $productSku);
                if ($price === null) {
                    $price = $this->getLowestPublicPriceFromDatabase($product->id);
                }
            }
        }

        $request->attributes->add([
            'product_sku' => $productSku,
            'price' => $price,
        ]);

        return $next($request);
    }

    private function getLivePrices()
    {
        $livePricesFile = public_path('live_prices.json');
        $livePricesJson = file_get_contents($livePricesFile);
        $livePrices = json_decode($livePricesJson, true);

        return $livePrices;
    }

    private function findMatchingLivePrice($livePrices, $productSku, $accountId)
    {
        foreach ($livePrices as $livePrice) {
            if ($livePrice['sku'] === $productSku && (!isset($livePrice['account']) || empty($livePrice['account']) || $this->isMatchingAccount($accountId, $livePrice['account']))) {
                return $livePrice;
            }
        }

        return null;
    }

    private function getPriceForProduct($priceQuery, $accountId)
    {
        if ($accountId) {
            $priceQuery->where(function ($query) use ($accountId) {
                $query->where('account_id', $accountId)
                    ->orWhereNull('account_id');
            });
        } else {
            $priceQuery->whereNull('account_id');
        }

        $prices = $priceQuery->get();

        if ($prices->isEmpty()) {
            return null;
        }

        return $prices->min('value');
    }

    private function isMatchingAccount($accountId, $accountReference)
    {
        $account = Account::where('external_reference', $accountReference)->first();

        if (!$account) {
            return false;
        }

        return $account->id == $accountId;
    }

    private function getLowestPublicPriceFromLivePrices($livePrices, $productSku)
    {
        $lowestPrice = null;

        foreach ($livePrices as $livePrice) {
            if ($livePrice['sku'] === $productSku && (!isset($livePrice['account']) || empty($livePrice['account']))) {
                $price = $livePrice['price'];

                if ($price < $lowestPrice) {
                    $lowestPrice = $price;
                } elseif ($lowestPrice === null) {
                    $lowestPrice = $price;
                }
            }
        }

        return $lowestPrice;
    }

    private function getLowestPublicPriceFromDatabase($productId)
    {
        $lowestPrice = Price::where('product_id', $productId)
            ->whereNull('account_id')
            ->min('value');

        return $lowestPrice;
    }
}