<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Delivery;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    private function calculateRegionalFee($addressStr, &$courier)
    {
        $addressStrLower = strtolower($addressStr);
        $isButuan = str_contains($addressStrLower, 'butuan');

        // 1. Strict Boundary Protocol
        if ($isButuan) {
            // Butuan ONLY accepts Local Rider
            $courier = 'Local Rider';
            return 50;
        } else {
            // Outside Butuan CANNOT use Local Rider
            if ($courier === 'Local Rider') {
                $courier = 'LBC'; // Default to LBC if somehow mis-assigned
            }
        }

        // 2. Regional Island Group Mapping (PH Standard 2026)
        $luzon = ['manila', 'abra', 'albay', 'apayao', 'aurora', 'bataan', 'batanes', 'batangas', 'benguet', 'bulacan', 'cagayan', 'camarines', 'catanduanes', 'cavite', 'ifugao', 'ilocos', 'isabela', 'kalinga', 'la union', 'laguna', 'marinduque', 'masbate', 'mountain province', 'nueva ecija', 'nueva vizcaya', 'mindoro', 'palawan', 'pampanga', 'pangasinan', 'quezon', 'quirino', 'rizal', 'romblon', 'sorsogon', 'tarlac', 'zambales'];
        $visayas = ['aklan', 'antique', 'biliran', 'bohol', 'capiz', 'cebu', 'samar', 'guimaras', 'iloilo', 'leyte', 'negros', 'siquijor'];
        $mindanao = ['agusan', 'basilan', 'bukidnon', 'camiguin', 'compostela', 'cotabato', 'davao', 'dinagat', 'lanao', 'maguindanao', 'misamis', 'sarangani', 'sultan', 'sulu', 'surigao', 'tawi-tawi', 'zamboanga'];

        foreach ($luzon as $p) {
            if (str_contains($addressStrLower, $p))
                return 240;
        }
        foreach ($visayas as $p) {
            if (str_contains($addressStrLower, $p))
                return 180;
        }
        foreach ($mindanao as $p) {
            if (str_contains($addressStrLower, $p))
                return 140;
        }

        return 160; // Default Standard
    }

    public function checkout(CheckoutRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // Automatic Courier & Fee Logic
            $assignedCourier = $validated['courier'];
            $calculatedFee = $this->calculateRegionalFee($validated['shipping_address'], $assignedCourier);

            // 1. Group items by their pre-order status to ensure separate order records
            $groupedItems = collect($validated['items'])->groupBy('is_preorder');

            $createdOrderIds = [];
            $finalTotalAmount = 0;
            $transactionId = 'TXN-' . strtoupper(Str::random(10));

            // To handle shipping fee fairly, we apply it to the first group
            reset($groupedItems);
            $primaryGroup = key($groupedItems);

            // Initialize Store Credit tracking
            $remainingCredit = $user->store_credit ?? 0;
            $totalCreditUsedInCheckout = 0;

            foreach ($groupedItems as $isPreorderGroup => $itemsInGroup) {
                $subtotal = 0;
                $processedItems = [];

                foreach ($itemsInGroup as $item) {
                    $pid = $item['product_id'] ?? $item['id'];
                    // Explicitly query via Model with shared lock for safety
                    $product = \App\Models\Product::where('id', $pid)->lockForUpdate()->first();

                    if (!$product) {
                        throw new \Exception("Asset #{$pid} not found in inventory syndicate.");
                    }

                    if (!$product->is_preorder && $product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for {$product->name}");
                    }

                    $priceToCharge = 0;
                    if ($item['is_preorder']) {
                        if (!$product->is_preorder) {
                            throw new \Exception("Product {$product->name} is not a valid pre-order item anymore.");
                        }
                        $priceToCharge = $product->downpayment_amount > 0 ? $product->downpayment_amount : $product->price * 0.2;
                    } else {
                        $priceToCharge = $product->price;
                        // Atomic query-level decrement for absolute reliability
                        \App\Models\Product::where('id', $product->id)->decrement('stock', $item['quantity']);
                    }

                    $lineTotal = $priceToCharge * $item['quantity'];
                    $subtotal += $lineTotal;

                    $processedItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'price' => $priceToCharge,
                    ];
                }

                // Apply shipping fee to the first group only to avoid overcharging
                $currentShippingFee = ($isPreorderGroup === $primaryGroup) ? $calculatedFee : 0;
                $currentPackagingFee = ($isPreorderGroup === $primaryGroup) ? ($validated['packaging_fee'] ?? 0) : 0;

                $currentTotal = $subtotal + $currentShippingFee + $currentPackagingFee;
                
                // Apply Store Credit to this sub-order
                $creditToApply = 0;
                if ($remainingCredit > 0) {
                    if ($remainingCredit >= $currentTotal) {
                        $creditToApply = $currentTotal;
                        $remainingCredit -= $currentTotal;
                    } else {
                        $creditToApply = $remainingCredit;
                        $remainingCredit = 0;
                    }
                }
                $totalCreditUsedInCheckout += $creditToApply;
                $cashPaidForOrder = $currentTotal - $creditToApply;

                $finalTotalAmount += $currentTotal;

                // 2. Create Order Record
                $order = Order::create([
                    'user_id' => $user->id,
                    'subtotal' => $subtotal,
                    'shipping_fee' => $currentShippingFee,
                    'packaging_type' => $validated['packaging_type'] ?? null,
                    'packaging_fee' => $currentPackagingFee,
                    'total_amount' => $currentTotal,
                    'store_credit_used' => $creditToApply,
                    'balance_due' => $cashPaidForOrder,
                    'status' => 'Pending',
                    'is_preorder' => (bool) $isPreorderGroup,
                    'shipping_address' => $validated['shipping_address'],
                    'courier' => $assignedCourier,
                ]);

                // 3. Insert Order Items
                foreach ($processedItems as $data) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $data['product_id'],
                        'quantity' => $data['quantity'],
                        'price' => $data['price'],
                    ]);
                }

                // 4. Record Payment (Amount = actual cash expected)
                $paymentStatus = 'Pending';
                if ($cashPaidForOrder <= 0) {
                    // Fully covered by store credit — no cash collection needed
                    $paymentStatus = 'Completed';
                } elseif ($validated['payment_method'] === 'COD') {
                    $paymentStatus = 'Pending'; // Collected on delivery
                }

                Payment::create([
                    'order_id' => $order->id,
                    'payment_method' => $validated['payment_method'],
                    'status' => $paymentStatus,
                    'amount' => $cashPaidForOrder,
                    'transaction_id' => $transactionId,
                ]);

                // 5. Initialize Delivery Record
                $isExternal = in_array($assignedCourier, ['LBC', 'J&T']);
                $isPreorder = (bool) $isPreorderGroup;
                $autoRiderId = null;
                $deliveryStatus = 'Pending Assignment';

                if ($isPreorder) {
                    $deliveryStatus = 'Awaiting Stock Arrival';
                    // We DO NOT assign a rider for pre-orders until stock arrives and balance is settled
                } elseif ($isExternal) {
                    $targetEmail = $assignedCourier === 'LBC' ? 'lbc@8600dc.com' : 'jnt@8600dc.com';
                    $courierAccount = \App\Models\User::where('email', $targetEmail)->first();
                    if ($courierAccount) {
                        $autoRiderId = $courierAccount->id;
                        $deliveryStatus = 'Assigned';
                    }
                }

                Delivery::create([
                    'order_id' => $order->id,
                    'user_id' => $autoRiderId,
                    'status' => $deliveryStatus,
                    'notes' => $isPreorder
                        ? "Pre-Order Lockout Protocol. Awaiting stock arrival and balance settlement."
                        : "Auto-assigned logistics protocol. Courier: {$assignedCourier}. Region: " . ($assignedCourier === 'Local Rider' ? 'Butuan Local' : 'National Transit')
                ]);

                $createdOrderIds[] = $order->id;
            }

            // 6. Update User Wallet
            if ($totalCreditUsedInCheckout > 0) {
                $user->decrement('store_credit', $totalCreditUsedInCheckout);
            }

            // 7. Loyalty Points based on Cash Spend
            $totalCashPaid = $finalTotalAmount - $totalCreditUsedInCheckout;
            $pointsGained = floor($totalCashPaid / 100);
            if ($pointsGained > 0) {
                $user->increment('loyalty_points', $pointsGained);

                $user->loyaltyPointsHistory()->create([
                    'points' => $pointsGained,
                    'type' => 'Earned',
                    'description' => "Order " . implode(', ', $createdOrderIds) . " Purchase",
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Checkout completed successfully!',
                'data' => [
                    'order_id' => $createdOrderIds[0],
                    'order_ids' => $createdOrderIds,
                    'total_amount' => $finalTotalAmount,
                    'transaction_id' => $transactionId
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}
