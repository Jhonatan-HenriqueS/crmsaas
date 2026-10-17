<?php

namespace App\Http\Requests;

use App\Http\CurrentAccount;
use App\Models\Lead;
use App\Models\LeadCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::authorize('create', [Lead::class, app(CurrentAccount::class)->get()])->allowed();
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $value = Str::trim($this->input($field));
                $this->merge([$field => $field === 'description' && $value === '' ? null : $value]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:120', 'regex:/\A[^\p{C}]+\z/u'],
            'description' => ['bail', 'nullable', 'string', 'max:5000', 'regex:/\A(?:[^\p{C}]|[\r\n\t])*\z/u'],
            'category_id' => [
                'bail', 'required',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_int($value) && ! is_string($value)) {
                        $fail('Selecione uma categoria válida da sua conta.');
                    }
                },
                'integer',
                Rule::exists(LeadCategory::class, 'id')->where('account_id', app(CurrentAccount::class)->get()->id),
            ],
            'first_contact_date' => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'next_contact_date' => ['bail', 'nullable', 'string', 'date_format:Y-m-d'],
            'potential_amount' => ['bail', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'charged_amount' => ['bail', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->except('_token')), array_keys($this->rules())) !== []) {
                $validator->errors()->add('form', 'Envie apenas os dados permitidos do lead.');
            }

            if (! $validator->errors()->hasAny(['first_contact_date', 'next_contact_date'])
                && is_string($this->input('first_contact_date'))
                && is_string($this->input('next_contact_date'))
                && strcmp($this->input('next_contact_date'), $this->input('first_contact_date')) < 0) {
                $validator->errors()->add('next_contact_date', 'A data do próximo contato não pode ser anterior à data do primeiro contato.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do lead.',
            'name.string' => 'Informe um nome válido para o lead.',
            'name.max' => 'O nome do lead deve ter no máximo 120 caracteres.',
            'name.regex' => 'O nome do lead contém caracteres inválidos.',
            'description.string' => 'Informe uma descrição válida para o lead.',
            'description.max' => 'A descrição deve ter no máximo 5000 caracteres.',
            'description.regex' => 'A descrição contém caracteres inválidos.',
            'category_id.required' => 'Selecione uma categoria para o lead.',
            'category_id.integer' => 'Selecione uma categoria válida da sua conta.',
            'category_id.exists' => 'Selecione uma categoria válida da sua conta.',
            'first_contact_date.required' => 'Informe a data do primeiro contato.',
            'first_contact_date.string' => 'Informe a data do primeiro contato no formato AAAA-MM-DD.',
            'first_contact_date.date_format' => 'Informe uma data válida para o primeiro contato no formato AAAA-MM-DD.',
            'first_contact_date.after_or_equal' => 'A data do primeiro contato deve ser igual ou posterior a 0001-01-01.',
            'next_contact_date.string' => 'Informe a data do próximo contato no formato AAAA-MM-DD.',
            'next_contact_date.date_format' => 'Informe uma data válida para o próximo contato no formato AAAA-MM-DD.',
            'potential_amount.numeric' => 'Informe um valor potencial numérico válido.',
            'potential_amount.decimal' => 'O valor potencial deve ter no máximo duas casas decimais.',
            'potential_amount.min' => 'O valor potencial não pode ser negativo.',
            'potential_amount.max' => 'O valor potencial não pode exceder 9999999999.99.',
            'charged_amount.numeric' => 'Informe um valor cobrado numérico válido.',
            'charged_amount.decimal' => 'O valor cobrado deve ter no máximo duas casas decimais.',
            'charged_amount.min' => 'O valor cobrado não pode ser negativo.',
            'charged_amount.max' => 'O valor cobrado não pode exceder 9999999999.99.',
        ];
    }
}
