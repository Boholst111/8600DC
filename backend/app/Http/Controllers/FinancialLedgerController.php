<?php

namespace App\Http\Controllers;

use App\Models\FinancialLedger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FinancialLedgerController extends Controller
{
    /**
     * Display a listing of custom financial entries.
     */
    public function index(): JsonResponse
    {
        $ledgers = FinancialLedger::with('recorder:id,name')
            ->orderByDesc('transaction_date')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $ledgers
        ]);
    }

    /**
     * Store a new custom financial entry (Manual Input).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type'             => 'required|in:Revenue,Expense,Adjustment,Tax Settlement',
            'category'         => 'required|string|max:100',
            'amount'           => 'required|numeric',
            'description'      => 'nullable|string|max:500',
            'reference_id'     => 'nullable|string|max:50',
            'transaction_date' => 'nullable|date',
        ]);

        $ledger = FinancialLedger::create([
            'type'             => $request->type,
            'category'         => $request->category,
            'amount'           => $request->amount,
            'description'      => $request->description,
            'reference_id'     => $request->reference_id,
            'transaction_date' => $request->transaction_date ? Carbon::parse($request->transaction_date) : now(),
            'recorded_by'      => Auth::id(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Financial record logged successfully.',
            'data'    => $ledger->load('recorder:id,name')
        ], 201);
    }
}
