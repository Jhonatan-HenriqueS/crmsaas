<?php

namespace App\Http\Requests;

use App\Http\CurrentAccount;
use App\Models\LeadCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class StoreLeadCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::authorize('update', app(CurrentAccount::class)->get())->allowed();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::trim($this->input('name'))]);
        }

        if (is_string($this->input('color'))) {
            $this->merge(['color' => strtolower(trim($this->input('color')))]);
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
            'color' => [
                'bail', 'required', 'string', 'regex:/\A#[0-9a-f]{6}\z/',
                $this->colorUniqueRule(),
            ],
        ];
    }

    protected function colorUniqueRule(): Unique
    {
        return Rule::unique(LeadCategory::class, 'color')
            ->where('account_id', app(CurrentAccount::class)->get()->id);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da categoria.',
            'name.string' => 'Informe um nome válido para a categoria.',
            'name.max' => 'O nome da categoria deve ter no máximo 120 caracteres.',
            'name.regex' => 'O nome da categoria contém caracteres inválidos.',
            'color.required' => 'Escolha uma cor para a categoria.',
            'color.string' => 'Informe uma cor HEX válida com seis dígitos, como #6d54b5.',
            'color.regex' => 'Informe uma cor HEX válida com seis dígitos, como #6d54b5.',
            'color.unique' => 'Esta cor já está sendo utilizada por outra categoria. Escolha uma cor diferente.',
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->except('_token')), ['name', 'color']) !== []) {
                $validator->errors()->add('form', 'Envie apenas o nome e a cor da categoria.');
            }
        }];
    }
}
