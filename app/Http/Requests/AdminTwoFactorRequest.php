<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ];
    }
}
