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
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $validatedData = $this->validateRequest($request);

        $productSku = $validatedData['sku'];
        $accountId = $validatedData['account_id'];

        $product = Product::where('sku', $productSku)->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $livePrices = $this->getLivePrices();
        $matchingLivePrice = $this->findMatchingLivePrice($livePrices, $productSku, $accountId);

        $price = $this->getPrice($matchingLivePrice, $accountId, $livePrices, $productSku, $product->id);

        $request->attributes->add([
            'product_sku' => $productSku,
            'price' => $price,
        ]);

        return $next($request);
    }
    private function validateRequest(Request $request): array
    {
        $validatedData = $request->validate([
            'sku' => ['required', 'string', 'uppercase'],
            'account_id' => ['nullable', 'integer'],
        ]);

        $validatedData['sku'] = trim(strip_tags($validatedData['sku']));
        $validatedData['account_id'] = trim(strip_tags($validatedData['account_id']));

        return $validatedData;
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
            if (
                $livePrice['sku'] === $productSku && (
                    empty($livePrice['account']) ||
                    $this->isMatchingAccount($accountId, $livePrice['account'])
                )
            ) {
                return $livePrice;
            }
        }

        return null;
    }

    private function getPrice($matchingLivePrice, $accountId, $livePrices, $productSku, $productId)
    {
        if ($matchingLivePrice && isset($matchingLivePrice['account'])) {
            return $price = $matchingLivePrice['price'];
        }

        $price = $this->findMatchingDatabasePrice($accountId, $productId);
        if (!$price) {
            $price = $this->getLowestPublicPrice($livePrices, $productSku);
            if (!$price) {
                $price = $this->getLowestPublicPriceFromDatabase($productId);
            }
        }

        return $price;
    }

    private function findMatchingDatabasePrice($accountId, $productId)
    {
        return Price::where('product_id', $productId)
            ->where('account_id', $accountId)
            ->value('value');
    }

    private function getLowestPublicPrice($livePrices, $productSku)
    {
        $publicPrices = [];

        foreach ($livePrices as $livePrice) {
            if (
                isset($livePrice['sku']) &&
                $livePrice['sku'] === $productSku &&
                empty($livePrice['account'])
            ) {
                $publicPrices[] = $livePrice['price'];
            }
        }

        return $publicPrices ? min($publicPrices) : null;
    }

    private function getLowestPublicPriceFromDatabase($productId)
    {
        return Price::where('product_id', $productId)
            ->whereNull('account_id')
            ->value('value');
    }

    private function isMatchingAccount($accountId, $accountReference)
    {
        $account = Account::where('external_reference', $accountReference)->first();

        return $account && $account->id == $accountId;
    }
}