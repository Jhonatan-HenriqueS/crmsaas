<?php

namespace App\Http\Requests;

use App\Http\CurrentAccount;
use App\Models\LeadCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class MutateLeadCategoryRequest extends StoreLeadCategoryRequest
{
    private ?LeadCategory $resolvedCategory = null;

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        abort_if($this->isMethod('DELETE') && $this->category()->is_sale_completed, 403, 'A categoria de finalização não pode ser excluída.');

        return true;
    }

    public function category(): LeadCategory
    {
        $id = $this->route('category');
        abort_unless(is_string($id), 404);

        return $this->resolvedCategory ??= app(CurrentAccount::class)->get()
            ->leadCategories()->whereKey($id)->firstOrFail();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return $this->isMethod('DELETE')
            ? ['confirmation' => ['bail', 'required', 'string', 'max:4096']]
            : parent::rules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'confirmation.required' => 'Revise a categoria e a quantidade de leads antes de confirmar a exclusão.',
            'confirmation.string' => 'A confirmação da exclusão é inválida.',
            'confirmation.max' => 'A confirmação da exclusão é inválida.',
        ];
    }

    protected function colorUniqueRule(): Unique
    {
        return parent::colorUniqueRule()->ignore($this->category());
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        if (! $this->isMethod('DELETE')) {
            return parent::after();
        }

        return [function (Validator $validator): void {
            if (array_diff(array_keys($this->except('_token')), ['confirmation']) !== []) {
                $validator->errors()->add('form', 'A exclusão não aceita campos adicionais.');
            }
        }];
    }
}
