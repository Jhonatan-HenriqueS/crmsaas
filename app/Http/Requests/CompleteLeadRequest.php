<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class CompleteLeadRequest extends MutateLeadRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirm_completion' => ['required', 'accepted'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'category' => ['bail', 'sometimes', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ];
    }
}
