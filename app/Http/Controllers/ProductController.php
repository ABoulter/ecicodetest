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

        // Fetch the live prices from the JSON file
        $livePrices = $this->getLivePrices();

        // Check if there is a match in the live prices JSON data
        $matchingLivePrice = $this->findMatchingLivePrice($livePrices, $productSku, $accountId);

        if ($matchingLivePrice) {
            return response()->json([
                'product_sku' => $productSku,
                'price' => $matchingLivePrice['price'],
            ]);
        }

        // Fetch the prices from the database
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

        if ($prices === null || $prices->isEmpty()) {

            $lowestPrice = Price::where('product_id', $product->id)
                ->where(function ($query) use ($accountId) {
                    $query->where('account_id', $accountId)
                        ->orWhereNull('account_id');
                })
                ->orderBy('value')
                ->value('value');


            $lowestPriceProduct = Product::where('id', $product->id)->first();

            if ($lowestPriceProduct) {
                return response()->json([
                    'product_sku' => $lowestPriceProduct->sku,
                    'price' => $lowestPrice,
                ]);
            } else {
                return response()->json(['error' => 'No matching price found'], 404);
            }
        }


        $winningPrice = $prices->min('price');

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