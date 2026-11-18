<?php

namespace App\Http\Requests;

use App\Http\CurrentAccount;
use App\Models\Account;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSoldCardViewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::authorize('update', app(CurrentAccount::class)->get())->allowed();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'view' => ['required', 'string', Rule::in(Account::SOLD_CARD_VIEWS)],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->except('_token')), array_keys($this->rules())) !== []) {
                $validator->errors()->add('form', 'Envie apenas a exibição escolhida do card.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'view.required' => 'Escolha o que o card de leads vendidos deve exibir.',
            'view.string' => 'Escolha uma exibição válida para o card.',
            'view.in' => 'Escolha uma exibição válida: total vendido, valor potencial ou total + potencial.',
        ];
    }
}
