<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use App\Models\Order;
use App\Models\Product;
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
            'evidence_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
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

        $evidencePath = null;
        if ($request->hasFile('evidence_photo')) {
            $path = $request->file('evidence_photo')->store('returns/evidence', 'public');
            $evidencePath = '/storage/' . $path;
        }

        $returnRequest = ReturnRequest::create([
            'order_id'       => $request->order_id,
            'user_id'        => $user->id,
            'reason'         => $request->reason,
            'description'    => $request->description,
            'evidence_photo' => $evidencePath,
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

            // If resolution is Refund, update the payment status
            if ($request->resolution === 'Refund') {
                $returnRequest->order->payment()->update(['status' => 'Refunded']);
            }

            // Mark the order as Returned
            $returnRequest->order->update(['status' => 'Returned']);

            // Restore inventory for returned items
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

        $returnRequest->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Return Request #{$id} updated to '{$request->status}'.",
            'data'    => $returnRequest->fresh(['order', 'user', 'reviewer']),
        ]);
    }
}
