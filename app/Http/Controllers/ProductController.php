<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


class ProductController extends Controller
{
    public function getProductPrice(Request $request)
    {
        $productSku = $request->attributes->get('product_sku');
        $price = $request->attributes->get('price');

        return response()->json([
            'product_sku' => $productSku,
            'price' => $price,
        ]);
    }
}