<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\LoyaltyPoint;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\OrderItem;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReturnController extends Controller
{
    // ─── CLIENT: File a Return Request ────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'    => 'required|exists:orders,id',
            'reason'      => 'required|string|in:Defective,Wrong Item,Not As Described,Damaged in Transit,Changed Mind,Other',
            'description' => 'nullable|string|max:1000',
            'evidence_files'   => 'nullable|array|max:20',
            'evidence_files.*' => 'file|mimes:jpeg,png,jpg,webp,mp4,mov,avi|max:20480', // 20MB per file max
            'items'       => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer',
            'items.*.qty'           => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        // Verify order belongs to this client
        $order = Order::where('id', $request->order_id)
                      ->where('user_id', $user->id)
                      ->where('status', 'Delivered')
                      ->firstOrFail();

        // Prevent duplicate pending returns for the same order
        $existing = ReturnRequest::where('order_id', $request->order_id)
            ->whereNotIn('status', ['Resolved', 'Rejected'])
            ->exists();

        if ($existing) {
            return response()->json([
                'status'  => 'error',
                'message' => 'A return request for this order is already under review.',
            ], 409);
        }

        $evidenceFiles = [];
        if ($request->hasFile('evidence_files')) {
            foreach ($request->file('evidence_files') as $file) {
                $path = $file->store('returns/evidence', 'public');
                $evidenceFiles[] = '/storage/' . $path;
            }
        }

        $returnRequest = ReturnRequest::create([
            'order_id'       => $order->id,
            'user_id'        => $user->id,
            'reason'         => $request->reason,
            'description'    => $request->description,
            'evidence_files' => !empty($evidenceFiles) ? $evidenceFiles : null,
            'items'          => $request->items,
            'status'         => 'Pending',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Return request filed. Our team will review it within 24-48 hours.',
            'data'    => $returnRequest->load('order.items.product'),
        ], 201);
    }

    // ─── CLIENT: Get My Return Requests ───────────────────────────────────────

    public function myReturns(): JsonResponse
    {
        $returns = ReturnRequest::with(['order.items.product', 'reviewer:id,name'])
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $returns]);
    }

    // ─── ADMIN: List All Return Requests ──────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = ReturnRequest::with([
            'order.items.product',
            'user:id,name,email',
            'reviewer:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $returns = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($returns);
    }

    // ─── ADMIN: Show Single Return Request ────────────────────────────────────

    public function show($id): JsonResponse
    {
        $returnRequest = ReturnRequest::with([
            'order.items.product',
            'order.user:id,name,email',
            'reviewer:id,name',
        ])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $returnRequest]);
    }

    // ─── ADMIN: Process / Resolve a Return Request ────────────────────────────

    public function process(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status'        => 'required|in:Under Review,Approved,Rejected,Item Received,Resolved',
            'resolution'    => 'nullable|in:Refund,Exchange,Store Credit,Reject',
            'refund_amount' => 'nullable|numeric|min:0',
            'admin_notes'   => 'nullable|string|max:1000',
        ]);

        $returnRequest = ReturnRequest::findOrFail($id);
        $admin = Auth::user();

        $returnRequest->status = $request->status;

        if ($request->filled('resolution')) {
            $returnRequest->resolution = $request->resolution;
        }

        if ($request->filled('refund_amount')) {
            $returnRequest->refund_amount = $request->refund_amount;
        }

        if ($request->filled('admin_notes')) {
            $returnRequest->admin_notes = $request->admin_notes;
        }

        // Track who reviewed it and when
        if (in_array($request->status, ['Approved', 'Rejected', 'Under Review']) && !$returnRequest->reviewed_by) {
            $returnRequest->reviewed_by = $admin->id;
            $returnRequest->reviewed_at = Carbon::now();
        }

        // Mark resolved
        if ($request->status === 'Resolved') {
            $returnRequest->resolved_at = Carbon::now();

            // Process Resolution Money & Credit
            if ($request->resolution === 'Refund') {
                // For actual refund, admin processes through payment gateway manually.
                $returnRequest->order->payment()->update(['status' => 'Refunded']);
            } elseif ($request->resolution === 'Store Credit') {
                $returnRequest->order->payment()->update(['status' => 'Refunded (Credit)']);
                
                if ($request->filled('refund_amount') && $request->refund_amount > 0) {
                    $returnRequest->user->increment('store_credit', $request->refund_amount);
                }
            } elseif ($request->resolution === 'Exchange') {
                // Create a replacement order for 0 pesos
                $originalOrder = $returnRequest->order;
                
                $newOrder = Order::create([
                    'user_id' => $returnRequest->user_id,
                    'subtotal' => 0,
                    'shipping_fee' => 0, // Admin covers exchange shipping
                    'packaging_type' => $originalOrder->packaging_type,
                    'packaging_fee' => 0,
                    'total_amount' => 0,
                    'status' => 'Pending',
                    'is_preorder' => false,
                    'shipping_address' => $originalOrder->shipping_address,
                    'courier' => $originalOrder->courier,
                ]);

                Payment::create([
                    'order_id' => $newOrder->id,
                    'payment_method' => 'Warranty Exchange',
                    'status' => 'Completed',
                    'amount' => 0,
                    'transaction_id' => 'EXC-' . strtoupper(Str::random(10)),
                ]);

                // Copy over the items and deduct stock
                if ($returnRequest->items) {
                    foreach ($returnRequest->items as $item) {
                        $orderItem = $originalOrder->items()->with('product')->find($item['order_item_id'] ?? null);
                        
                        if ($orderItem && $orderItem->product) {
                            if ($orderItem->product->stock >= $item['qty']) {
                                $orderItem->product->decrement('stock', $item['qty']);
                            }
                            
                            OrderItem::create([
                                'order_id' => $newOrder->id,
                                'product_id' => $orderItem->product_id,
                                'quantity' => $item['qty'],
                                'price' => 0, // 0 because it's a replacement
                            ]);
                        }
                    }
                }

                // Trigger logistics automatically
                $isExternal = in_array($newOrder->courier, ['LBC', 'J&T']);
                $autoRiderId = null;
                $deliveryStatus = 'Pending Assignment';

                if ($isExternal) {
                    $targetEmail = $newOrder->courier === 'LBC' ? 'lbc@8600dc.com' : 'jnt@8600dc.com';
                    $courierAccount = \App\Models\User::where('email', $targetEmail)->first();
                    if ($courierAccount) {
                        $autoRiderId = $courierAccount->id;
                        $deliveryStatus = 'Assigned';
                    }
                }

                Delivery::create([
                    'order_id' => $newOrder->id,
                    'user_id' => $autoRiderId,
                    'status' => $deliveryStatus,
                    'notes' => "URGENT TWO-WAY EXCHANGE (RMA #{$returnRequest->id}): Collect original damaged items from the client BEFORE handing over this replacement package."
                ]);
            }

            // Mark the order as Returned
            $returnRequest->order->update(['status' => 'Returned']);

            // Restore inventory for returned items ONLY IF it is not an Exchange 
            // (If it's an exchange, we assume the damaged ones are kept out of active stock, 
            // and we already effectively deducted the new stock by not incrementing here and pushing a new order.)
            if (in_array($request->resolution, ['Refund', 'Store Credit'])) {
                if ($returnRequest->items) {
                    foreach ($returnRequest->items as $item) {
                        $orderItem = $returnRequest->order->items()
                            ->with('product')
                            ->find($item['order_item_id'] ?? null);

                        if ($orderItem && $orderItem->product) {
                            $orderItem->product->increment('stock', $item['qty'] ?? 1);
                        }
                    }
                }
            }
        }

        $returnRequest->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Return Request #{$id} updated to '{$request->status}'.",
            'data'    => $returnRequest->fresh(['order', 'user', 'reviewer']),
        ]);
    }
}
