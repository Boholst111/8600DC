<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\Delivery;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // ─── DASHBOARD ────────────────────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        $totalSalesToday = Payment::whereDate('created_at', $today)
            ->where('status', 'Completed')
            ->sum('amount');

        $totalSalesMonth = Payment::where('created_at', '>=', $thisMonth)
            ->where('status', 'Completed')
            ->sum('amount');

        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'Pending')->count();

        // Sales by day (last 7 days) for chart
        $salesChart = collect(range(6, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            return [
                'date' => $date->format('M d'),
                'amount' => (float) Payment::whereDate('created_at', $date)
                    ->where('status', 'Completed')
                    ->sum('amount'),
            ];
        });

        // Top selling products
        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(price * quantity) as revenue'))
            ->with('product:id,name,brand')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'product_name' => $item->product->name ?? 'N/A',
                'brand' => $item->product->brand ?? 'N/A',
                'total_sold' => $item->total_sold,
                'revenue' => (float) $item->revenue,
            ]);

        $totalUsers = User::count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_sales_today' => $totalSalesToday,
                'total_sales_month' => $totalSalesMonth,
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'total_users' => $totalUsers,
                'sales_chart' => $salesChart,
                'top_products' => $topProducts,
            ]
        ]);
    }

    // ─── ORDER MANAGEMENT ─────────────────────────────────────────────────────

    public function orders(Request $request): JsonResponse
    {
        $query = Order::with(['user:id,name,email', 'payment', 'delivery', 'items.product:id,name,brand,eta']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->orderByDesc('created_at')->paginate(15);

        return response()->json($orders);
    }

    public function updateOrderStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:Pending,Processing,Settlement Required,Ready for Shipment,Shipped,Delivered,Returned,Cancelled',
        ]);

        $order = Order::findOrFail($id);
        $order->status = $request->status;
        $order->save();

        // Handle Pre-Order Arrival Notification
        if ($request->status === 'Settlement Required') {
            $delivery = Delivery::where('order_id', $id)->first();
            if ($delivery) {
                $delivery->update([
                    'status' => 'Awaiting Payment Settlement',
                    'notes' => ($delivery->notes ?? '') . " | Update: Item arrived, waiting for client balance settlement."
                ]);
            }
        }

        // Sync payment if Delivered
        if ($request->status === 'Delivered') {
            Payment::where('order_id', $id)
                ->where('status', 'Pending')
                ->update(['status' => 'Completed']);
        }

        return response()->json(['status' => 'success', 'message' => "Order #{$id} updated to {$request->status} and client notified."]);
    }

    public function updateOrder(Request $request, $id): JsonResponse
    {
        $request->validate([
            'payment_status' => 'sometimes|string|in:Pending,Paid,Refunded,Failed',
            'status'         => 'sometimes|string|in:Pending,Processing,Settlement Required,Ready for Shipment,Shipped,Delivered,Returned,Cancelled',
            'courier'        => 'sometimes|nullable|string|max:50',
        ]);

        $order = Order::findOrFail($id);

        if ($request->filled('payment_status')) {
            // Persist payment status on the related payment record
            Payment::where('order_id', $id)->update(['status' => $request->payment_status]);
        }

        if ($request->filled('courier')) {
            $order->courier = $request->courier;
        }

        if ($request->filled('status')) {
            $order->status = $request->status;
        }

        $order->save();

        return response()->json(['status' => 'success', 'message' => "Order #{$id} updated.", 'data' => $order->fresh()]);
    }

    // ─── PRODUCT MANAGEMENT ───────────────────────────────────────────────────

    public function storeProduct(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'brand' => 'required|string|max:100',
            'scale' => 'nullable|string|max:20',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'is_limited_edition' => 'boolean',
            'is_preorder' => 'boolean',
            'eta' => 'nullable|string|max:100',
            'downpayment_amount' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validatedData['image_url'] = '/storage/' . $path;
        }

        $product = Product::create($validatedData);

        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $index => $file) {
                $path = $file->store('products/gallery', 'public');
                $product->images()->create([
                    'path' => '/storage/' . $path,
                    'sort_order' => $index,
                ]);
            }
        }

        return response()->json(['status' => 'success', 'data' => $product], 201);
    }

    public function updateProduct(Request $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'brand' => 'sometimes|required|string|max:100',
            'scale' => 'sometimes|nullable|string|max:20',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'description' => 'nullable|string',
            'is_limited_edition' => 'sometimes|boolean',
            'is_preorder' => 'sometimes|boolean',
            'eta' => 'nullable|string|max:100',
            'downpayment_amount' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'gallery_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validatedData['image_url'] = '/storage/' . $path;
        }

        $product->update($validatedData);

        if ($request->hasFile('gallery_images')) {
            // Option 1: Replace all (simplest for now)
            $product->images()->delete();
            foreach ($request->file('gallery_images') as $index => $file) {
                $path = $file->store('products/gallery', 'public');
                $product->images()->create([
                    'path' => '/storage/' . $path,
                    'sort_order' => $index,
                ]);
            }
        }

        return response()->json(['status' => 'success', 'data' => $product]);
    }

    public function destroyProduct($id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['status' => 'success', 'message' => 'Product deleted.']);
    }

    // ─── USER MANAGEMENT ──────────────────────────────────────────────────────

    public function users(): JsonResponse
    {
        $users = User::with('role')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($users);
    }

    public function updateUserRole(Request $request, $id): JsonResponse
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($id);
        $user->role_id = $request->role_id;
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'User role updated.']);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
        ]);

        return response()->json(['status' => 'success', 'data' => $user], 201);
    }

    public function toggleUserBlock($id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        // Prevent blocking self or other admins
        if ($user->role_id === 1) {
             return response()->json(['status' => 'error', 'message' => 'Root administrators cannot be restricted.'], 403);
        }

        $user->is_blocked = !$user->is_blocked;
        $user->save();

        $status = $user->is_blocked ? 'restricted' : 'authorized';
        return response()->json(['status' => 'success', 'message' => "Member access {$status}."]);
    }

    // ─── PDF INVOICE ──────────────────────────────────────────────────────────

    public function generateInvoice($orderId)
    {
        $order = Order::with([
            'user',
            'items.product',
            'payment',
            'delivery',
        ])->findOrFail($orderId);

        $pdf = Pdf::loadView('invoices.order', compact('order'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("8600DC-Invoice-Order-{$order->id}.pdf");
    }

    // ─── CSV EXPORT ───────────────────────────────────────────────────────────

    public function exportOrdersCsv()
    {
        $orders = Order::with(['user:id,name,email', 'payment'])->get();

        $csvRows = [];
        $csvRows[] = implode(',', ['Order ID', 'Customer', 'Email', 'Total', 'Payment Method', 'Status', 'Date']);

        foreach ($orders as $order) {
            $csvRows[] = implode(',', [
                $order->id,
                '"' . ($order->user->name ?? 'N/A') . '"',
                '"' . ($order->user->email ?? 'N/A') . '"',
                $order->total_amount,
                $order->payment->payment_method ?? 'N/A',
                $order->status,
                $order->created_at->format('Y-m-d'),
            ]);
        }

        $csvContent = implode("\n", $csvRows);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="8600DC-Orders-Export.csv"',
        ]);
    }
}
