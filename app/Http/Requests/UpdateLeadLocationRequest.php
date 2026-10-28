<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateLeadLocationRequest extends MutateLeadRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'maps_url' => ['required', ...$this->mapsUrlRules()],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'category' => ['bail', 'sometimes', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'maps_url.required' => 'Cole o link do Google Maps.',
        ];
    }
}
