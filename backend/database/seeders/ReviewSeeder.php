<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\Product;
use App\Models\User;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();
        $clients = User::whereHas('role', function($q) {
            $q->where('name', 'CLIENT');
        })->get();

        if ($clients->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            // Add 1-3 reviews per product
            $reviewCount = rand(1, 3);
            $shuffledClients = $clients->shuffle();

            for ($i = 0; $i < $reviewCount; $i++) {
                if ($i >= $shuffledClients->count()) break;

                $client = $shuffledClients[$i];

                Review::create([
                    'user_id' => $client->id,
                    'product_id' => $product->id,
                    'rating' => rand(4, 5), // Premium models usually get good ratings
                    'comment' => $this->getRandomComment($product->name),
                    'is_verified_purchase' => true,
                    'created_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }

    private function getRandomComment($productName)
    {
        $comments = [
            "Absolutely stunning detail on this $productName! The paint finish is flawless.",
            "A must-have for any collector. The scale accuracy is impressive.",
            "Fast shipping and the packaging was super secure. The model arrived in perfect condition.",
            "Love the opening parts on this one. Very smooth mechanism.",
            "The $productName looks even better in person. Great addition to my display case.",
            "Quality is top-notch. You can really see the craftsmanship in the interior details.",
            "Worth every peso. Excellent value for a limited edition piece.",
            "Been waiting for this $productName for a while, and it did not disappoint!",
        ];

        return $comments[array_rand($comments)];
    }
}
