<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\LeadCategory;
use Illuminate\Validation\ValidationException;

class EnsureSaleCompletedCategory
{
    public function handle(Account $account): LeadCategory
    {
        return $account->getConnection()->transaction(function () use ($account): LeadCategory {
            $account->newQuery()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $existing = $account->leadCategories()->where('system_key', LeadCategory::SALE_COMPLETED)->first();
            if ($existing !== null) {
                return $existing;
            }

            if ($account->leadCategories()->where('color', LeadCategory::SALE_COMPLETED_COLOR)->exists()) {
                throw ValidationException::withMessages([
                    'color' => 'O verde de finalização já está em uso. Altere a cor da categoria comum antes de preparar esta conta.',
                ]);
            }

            $category = $account->leadCategories()->make([
                'name' => 'Finalizado',
                'color' => LeadCategory::SALE_COMPLETED_COLOR,
            ]);
            $category->system_key = LeadCategory::SALE_COMPLETED;
            abort_unless($category->saveOrFail(), 409);

            return $category;
        }, attempts: 3);
    }
}
