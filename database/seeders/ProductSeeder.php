<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/products.json');
        $products = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach (array_chunk($products, 100) as $chunk) {
            foreach ($chunk as $product) {
                Product::query()->updateOrCreate(
                    ['sku' => $product['sku']],
                    [
                        'description' => $product['description'],
                        'uom' => $product['uom'],
                        'price' => $product['price'],
                    ],
                );
            }
        }
    }
}
