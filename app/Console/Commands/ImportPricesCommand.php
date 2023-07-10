<?php



namespace App\Console\Commands;

use App\Models\User;
use App\Models\Price;
use App\Models\Account;
use App\Models\Product;
use Illuminate\Console\Command;

class ImportCSVPricesCommand extends Command
{
    protected $signature = 'import:csv-prices';

    protected $description = 'Import prices from import.csv file';

    public function handle()
    {
        $filePath = public_path('import.csv');

        if (!file_exists($filePath)) {
            $this->error('Failed to open import.csv file: File not found');
            return;
        }

        $file = fopen($filePath, 'r');

        if (!$file) {
            $this->error('Failed to open import.csv file');
            return;
        }

        fgetcsv($file);

        while (($data = fgetcsv($file)) !== false) {
            $productCode = $data[0];
            $accountRef = $data[1];
            $userRef = $data[2];
            $quantity = $data[3];
            $value = $data[4];

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

            $user = null;
            if ($userRef) {
                $user = User::where('external_reference', $userRef)->first();
                if (!$user) {
                    $this->warn("User not found with reference: $userRef");
                }
            }

            $price = new Price();
            $price->product()->associate($product);
            $price->account()->associate($account);
            $price->user()->associate($user);
            $price->quantity = $quantity;
            $price->value = $value;
            $price->save();
        }

        fclose($file);

        $this->info('Prices imported successfully.');
    }
}