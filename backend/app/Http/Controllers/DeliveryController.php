<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeliveryController extends Controller
{
    /**
     * Admin: assign a delivery rider to an order
     */
    public function assignRider(Request $request, $deliveryId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $delivery = Delivery::findOrFail($deliveryId);

        // Ensure assigned user has DELIVERY role
        $rider = User::with('role')->findOrFail($request->user_id);
        if (!$rider->role || $rider->role->name !== 'DELIVERY') {
            return response()->json([
                'status' => 'error',
                'message' => 'User is not a delivery personnel.',
            ], 422);
        }

        $delivery->user_id = $request->user_id;
        $delivery->status = 'Assigned';
        $delivery->save();

        return response()->json([
            'status' => 'success',
            'message' => "Rider {$rider->name} assigned to delivery #{$deliveryId}",
            'data' => $delivery,
        ]);
    }

    /**
     * Rider: get own assigned deliveries
     */
    public function myDeliveries(Request $request): JsonResponse
    {
        $rider = $request->user();

        $query = Delivery::with(['order.user:id,name,email', 'order.items.product:id,name,price']);

        if ($rider->email === 'lbc@8600dc.com') {
            $query->whereHas('order', function($q) {
                $q->where('courier', 'LBC');
            });
        } elseif ($rider->email === 'jnt@8600dc.com') {
            $query->whereHas('order', function($q) {
                $q->where('courier', 'J&T');
            });
        } else {
            // Local Rider
            $query->where('user_id', $rider->id);
        }

        $deliveries = $query->orderByDesc('updated_at')->get();

        return response()->json([
            'status' => 'success',
            'data' => $deliveries,
        ]);
    }

    /**
     * Rider: update delivery status
     */
    public function updateStatus(Request $request, $deliveryId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:Pending Assignment,Assigned,Ready for Shipment,Out for Delivery,Delivered',
            'notes' => 'nullable|string|max:500',
        ]);

        $delivery = Delivery::findOrFail($deliveryId);

        $isAuthorized = ($delivery->user_id === $request->user()->id) || ($request->user()->role->name === 'ADMIN');
        
        // Allow centralized courier accounts (LBC/J&T) to update their respective deliveries
        if (!$isAuthorized) {
            $userEmail = $request->user()->email;
            $orderCourier = $delivery->order?->courier;
            if ($userEmail === 'lbc@8600dc.com' && $orderCourier === 'LBC') $isAuthorized = true;
            if ($userEmail === 'jnt@8600dc.com' && $orderCourier === 'J&T') $isAuthorized = true;
        }

        if (!$isAuthorized) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $delivery->status = $request->status;
        if ($request->filled('notes')) {
            $delivery->notes = $request->notes;
        }
        $delivery->save();

        // ── Sync order status based on delivery progression ──
        $order = $delivery->order;
        if ($order) {
            if ($request->status === 'Out for Delivery') {
                $order->update(['status' => 'Shipped']);
            } elseif ($request->status === 'Delivered') {
                $order->update(['status' => 'Delivered']);
                // Mark payment complete on COD delivery
                \App\Models\Payment::where('order_id', $order->id)
                    ->where('status', 'Pending')
                    ->update(['status' => 'Completed']);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Delivery #{$deliveryId} updated to {$request->status}",
            'data' => $delivery,
        ]);
    }

    /**
     * Admin: view all deliveries
     */
    public function allDeliveries(): JsonResponse
    {
        $deliveries = Delivery::with([
            'order.user:id,name,email',
            'rider:id,name,email',
        ])->orderByDesc('created_at')->paginate(20);

        return response()->json(['status' => 'success', 'data' => $deliveries]);
    }

    /**
     * Admin: get available delivery riders
     */
    public function availableRiders(): JsonResponse
    {
        $riders = User::whereHas('role', fn($q) => $q->where('name', 'DELIVERY'))
            ->select('id', 'name', 'email')
            ->get();

        return response()->json(['status' => 'success', 'data' => $riders]);
    }
}
