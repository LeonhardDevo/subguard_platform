<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone' => 'nullable|string|max:50',
            'external_id' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive,blocked',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|string|max:255';
            $rules['email'] = 'sometimes|string|email|max:255';
        }

        return $rules;
    }
}
