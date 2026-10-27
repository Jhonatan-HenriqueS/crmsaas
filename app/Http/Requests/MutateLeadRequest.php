<?php

namespace App\Http\Requests;

use App\Http\CurrentAccount;
use App\Models\Lead;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;

class MutateLeadRequest extends StoreLeadRequest
{
    private ?Lead $resolvedLead = null;

    public function authorize(): bool
    {
        return Gate::authorize($this->isMethod('DELETE') ? 'delete' : 'update', [
            $this->lead(), app(CurrentAccount::class)->get(),
        ])->allowed();
    }

    public function lead(): Lead
    {
        $id = $this->route('lead');
        abort_unless(is_string($id), 404);

        return $this->resolvedLead ??= app(CurrentAccount::class)->get()
            ->leads()->whereKey($id)->firstOrFail();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...($this->isMethod('DELETE') ? [] : parent::rules()),
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'category' => ['bail', 'sometimes', 'required', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ];
    }
}
