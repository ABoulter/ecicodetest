<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Price;

class ProductController extends Controller
{
    public function getProductPrice(Request $request)
    {
        $productSku = $request->input('sku');
        $accountId = $request->input('account_id');

        $product = Product::where('sku', $productSku)->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }


        $livePrices = $this->getLivePrices();


        $matchingLivePrice = $this->findMatchingLivePrice($livePrices, $productSku, $accountId);

        if ($matchingLivePrice) {
            return response()->json([
                'product_sku' => $productSku,
                'price' => $matchingLivePrice['price'],
            ]);
        }

        $pricesQuery = Price::where('product_id', $product->id);

        if ($accountId) {
            $pricesQuery->where(function ($query) use ($accountId) {
                $query->where('account_id', $accountId)
                    ->orWhereNull('account_id');
            });
        } else {
            $pricesQuery->whereNull('account_id');
        }

        $prices = $pricesQuery->get();

        if ($matchingLivePrice->isEmpty() || $matchingLivePrice === null) {

            $lowestPrice = Price::where('product_id', $product->id)
                ->where(function ($query) use ($accountId) {
                    $query->where('account_id', $accountId)
                        ->orWhereNull('account_id');
                })
                ->min('value');

            return response()->json([
                'product_sku' => $productSku,
                'price' => $lowestPrice,
            ]);
        }


        $winningPrice = $prices->min('value');

        return response()->json([
            'product_sku' => $productSku,
            'price' => $winningPrice,
        ]);
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
            if ($livePrice['sku'] === $productSku) {
                if (!array_key_exists('account', $livePrice) || $livePrice['account'] === $accountId) {
                    return $livePrice;
                }
            }
        }

        return null;
    }
}