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
        $this->category();

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
        return $this->isMethod('DELETE') ? [] : parent::rules();
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
            if ($this->except('_token') !== []) {
                $validator->errors()->add('form', 'A exclusão não aceita campos adicionais.');
            }
        }];
    }
}
