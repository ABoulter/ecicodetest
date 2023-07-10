<?php

namespace App\Console\Commands;

use App\Models\Price;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateLivePricesCommand extends Command
{
    protected $signature = 'update:live-prices';

    protected $description = 'Update prices from live_prices.json file';

    public function handle()
    {
        $jsonFilePath = public_path('live_prices.json');

        if (!file_exists($jsonFilePath)) {
            $this->warn('live_prices.json file not found');
            return;
        }

        $jsonContent = file_get_contents($jsonFilePath);
        $livePrices = json_decode($jsonContent, true);

        if (!is_array($livePrices)) {
            $this->warn('Invalid JSON format in live_prices.json');
            return;
        }

        foreach ($livePrices as $livePrice) {
            $productCode = $livePrice['sku'];
            $accountRef = $livePrice['account'] ?? null;

            $product = Product::where('sku', $productCode)->first();

            if (!$product) {
                $this->warn("Product not found with code: $productCode");
                continue;
            }

            $account = null;
            if ($accountRef) {
                $account = Account::where('external_reference', $accountRef)->first();
                if (!$account) {
                    $this->warn("Account not found with reference: $accountRef");
                }
            }

            $price = Price::where('product_id', $product->id)
                ->where(function ($query) use ($account) {
                    $query->where('account_id', $account->id)
                        ->orWhereNull('account_id');
                })
                ->first();

            if ($price) {
                $price->value = $livePrice['price'];
                $price->save();
            }
        }

        $this->info('Prices updated successfully.');
    }
}