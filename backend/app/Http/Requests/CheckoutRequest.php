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
        $isGCash = $this->payment_method === 'GCash';

        return [
            'shipping_address' => 'required|string|max:500',
            'courier' => 'required|in:LBC,J&T,Local Rider',
            'delivery_type' => 'required|in:door,pickup',
            'payment_method' => 'required|in:COD,GCash,Xendit,PayPal,Store Credit',
            'shipping_fee' => 'required|numeric|min:0',
            
            'packaging_type' => 'nullable|string',
            'packaging_fee' => 'nullable|numeric|min:0',
            
            'nearest_branch' => 'nullable|string',
            'use_store_credit' => 'nullable',

            'gcash_proof' => [
                $isGCash && !$this->gcash_reference ? 'required' : 'nullable',
                'image',
                'max:5120'
            ],
            'gcash_reference' => [
                $isGCash && !$this->hasFile('gcash_proof') ? 'required' : 'nullable',
                'string',
                'min:9',
                'max:255'
            ],

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.is_preorder' => 'required|boolean',
        ];
    }

    protected function prepareForValidation()
    {
        // FormData sends items as a JSON string, decode it
        if (is_string($this->items)) {
            $this->merge(['items' => json_decode($this->items, true)]);
        }

        // Normalize use_store_credit from '1'/'0' string to boolean
        if ($this->has('use_store_credit')) {
            $this->merge(['use_store_credit' => filter_var($this->use_store_credit, FILTER_VALIDATE_BOOLEAN)]);
        }
    }
}
