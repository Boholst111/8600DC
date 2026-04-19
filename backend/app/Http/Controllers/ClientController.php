<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Address;
use App\Models\LoyaltyPoint;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class ClientController extends Controller
{
    /**
     * Get authenticated user's orders with items and delivery info
     */
    public function myOrders(Request $request): JsonResponse
    {
        $orders = Order::with(['items.product', 'delivery', 'payment'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json(['status' => 'success', 'data' => $orders]);
    }

    /**
     * Get specific order details
     */
    public function orderDetails(Request $request, $id): JsonResponse
    {
        $order = Order::with(['items.product', 'delivery', 'payment'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $order]);
    }

    /**
     * Download Invoice PDF
     */
    public function downloadInvoice(Request $request, $id)
    {
        $order = Order::with(['items.product', 'user', 'payment'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $pdf = Pdf::loadView('invoices.order_invoice', compact('order'));
        return $pdf->download("8600DC-Invoice-{$order->id}.pdf");
    }

    /**
     * Loyalty Points History
     */
    public function loyaltyHistory(Request $request): JsonResponse
    {
        $history = LoyaltyPoint::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['status' => 'success', 'data' => $history]);
    }

    /**
     * Address Management
     */
    public function getAddresses(Request $request): JsonResponse
    {
        $addresses = Address::where('user_id', $request->user()->id)->get();
        return response()->json(['status' => 'success', 'data' => $addresses]);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address_line1' => 'required|string',
            'address_line2' => 'nullable|string',
            'city' => 'required|string',
            'province' => 'required|string',
            'zip_code' => 'required|string',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($validated);

        return response()->json(['status' => 'success', 'data' => $address]);
    }

    public function updateAddress(Request $request, $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        
        $validated = $request->validate([
            'full_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address->update($request->all());

        return response()->json(['status' => 'success', 'data' => $address]);
    }

    public function deleteAddress(Request $request, $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $address->delete();
        return response()->json(['status' => 'success', 'message' => 'Address deleted']);
    }

    /**
     * Settle Pre-Order Balance
     */
    public function settleBalance(Request $request, $id): JsonResponse
    {
        $order = Order::with('items.product')
            ->where('user_id', $request->user()->id)
            ->where('is_preorder', true)
            ->findOrFail($id);

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        // Create the balance payment record
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => $validated['payment_method'],
            'status' => 'Completed',
            'amount' => $validated['amount'],
            'transaction_id' => 'SETTLE-' . strtoupper(bin2hex(random_bytes(4))),
        ]);

        // Update order status and total amount
        $order->status = 'Ready for Shipment'; // Instantly flag for admin fulfillment
        $order->total_amount += $validated['amount'];
        $order->save();

        // Activate the delivery record now that balance is settled
        $delivery = \App\Models\Delivery::where('order_id', $order->id)->first();
        if ($delivery) {
            $isExternal = in_array($order->courier, ['LBC', 'J&T']);
            $newStatus = 'Pending Assignment';
            $assignedRiderId = null;

            if ($isExternal) {
                $targetEmail = $order->courier === 'LBC' ? 'lbc@8600dc.com' : 'jnt@8600dc.com';
                $courierAccount = \App\Models\User::where('email', $targetEmail)->first();
                if ($courierAccount) {
                    $assignedRiderId = $courierAccount->id;
                    $newStatus = 'Assigned';
                }
            }

            $delivery->update([
                'status' => $newStatus,
                'user_id' => $assignedRiderId,
                'notes' => ($delivery->notes ?? '') . " | Balance Settled. Moving to active dispatch stream."
            ]);
        }

        return response()->json([
            'status' => 'success', 
            'message' => 'Balance settled successfully. Your order is now ready for dispatch!',
            'data' => $order->load('delivery')
        ]);
    }
}
