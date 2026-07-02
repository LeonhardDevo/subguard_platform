<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'interval' => 'nullable|in:monthly,yearly,weekly',
            'trial_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:1000',
            'features' => 'nullable|array',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'sometimes|string|max:255';
            $rules['price'] = 'sometimes|numeric|min:0';
        }

        return $rules;
    }
}
