<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Display a listing of the reviews for a specific product.
     */
    /**
     * Display a listing of the reviews for a specific product.
     */
    public function index($productId)
    {
        $reviews = Review::with('user:id,name')
            ->where('product_id', $productId)
            ->where('status', 'Approved')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Store a newly created review in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:2',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'video' => 'nullable|mimes:mp4,mov,avi,wmv|max:20480', // 20MB limit
        ]);

        $user = Auth::user();
        $productId = $request->product_id;

        // Check if user has already reviewed this product
        $existingReview = Review::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product.'
            ], 400);
        }

        $hasPurchased = Order::where('user_id', $user->id)
            ->where('status', 'Delivered')
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->exists();

        if (!$hasPurchased) {
             return response()->json([
                'success' => false,
                'message' => 'You can only review products you have purchased and received.'
            ], 403);
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('reviews/images', 'public');
                $imagePaths[] = '/storage/' . $path;
            }
        }

        $videoPath = null;
        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('reviews/videos', 'public');
            $videoPath = '/storage/' . $path;
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'images' => $imagePaths,
            'video_url' => $videoPath,
            'status' => 'Approved', // Auto-approve for now, can be changed to 'Pending' if moderated
            'is_verified_purchase' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully!',
            'data' => $review
        ], 201);
    }

    /**
     * Admin: List all reviews.
     */
    public function adminIndex(Request $request)
    {
        $query = Review::with(['user:id,name,email', 'product:id,name,brand']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->latest()->paginate(20);

        return response()->json($reviews);
    }

    /**
     * Admin: Update review status.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:Approved,Hidden,Pending']);
        
        $review = Review::findOrFail($id);
        $review->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => "Review status updated to {$request->status}.",
            'data' => $review
        ]);
    }

    /**
     * Admin/User: Delete review.
     */
    public function destroy($id)
    {
        $review = Review::findOrFail($id);
        
        // Check if admin or owner
        if (Auth::user()->role->name !== 'ADMIN' && Auth::id() !== $review->user_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.'
        ]);
    }

    /**
     * Check if the authenticated user can review a product.
     */
    public function canReview($productId)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['can_review' => false]);

        // Check if already reviewed
        $hasReviewed = Review::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->exists();

        if ($hasReviewed) return response()->json(['can_review' => false, 'reason' => 'already_reviewed']);

        // Check if purchased and delivered
        $hasPurchased = Order::where('user_id', $user->id)
            ->where('status', 'Delivered')
            ->whereHas('items', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->exists();

        return response()->json([
            'can_review' => $hasPurchased,
            'reason' => !$hasPurchased ? 'not_purchased' : null
        ]);
    }
}
