<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipping_address' => 'required|string|max:500',
            'courier' => 'required|in:LBC,J&T,Local Rider',
            'payment_method' => 'required|in:COD,GCash,Xendit,PayPal',
            'shipping_fee' => 'required|numeric|min:0',
            
            'packaging_type' => 'nullable|string',
            'packaging_fee' => 'nullable|numeric|min:0',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.is_preorder' => 'required|boolean',
        ];
    }
}
