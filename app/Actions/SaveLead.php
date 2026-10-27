<?php

namespace App\Actions;

use App\Models\Account;
use App\Models\Lead;
use App\Models\LeadCategory;
use Illuminate\Validation\ValidationException;

class SaveLead
{
    /** @param array<string, mixed> $data */
    public function handle(Account $account, array $data, ?Lead $lead = null, bool $confirmCompletion = false, bool $confirmReopening = false): Lead
    {
        return $account->getConnection()->transaction(function () use ($account, $data, $lead, $confirmCompletion, $confirmReopening): Lead {
            $record = $lead === null ? $account->leads()->make() : $account->leads()->lockForUpdate()->findOrFail($lead->id);
            $categoryId = $data['category_id'] ?? null;
            if (! is_int($categoryId) && ! is_string($categoryId)) {
                throw ValidationException::withMessages(['category_id' => 'Selecione uma categoria válida da sua conta.']);
            }
            $category = $account->leadCategories()->lockForUpdate()->find($categoryId);
            if ($category === null) {
                throw ValidationException::withMessages(['category_id' => 'Selecione uma categoria válida da sua conta.']);
            }

            $wasCompleted = $record->exists && $record->category()->where('system_key', LeadCategory::SALE_COMPLETED)->exists();
            if ($category->is_sale_completed && ! $wasCompleted && ! $confirmCompletion) {
                throw ValidationException::withMessages(['confirm_completion' => 'Confirme que a venda foi concluída, entregue e paga.']);
            }
            if ($wasCompleted && ! $category->is_sale_completed && ! $confirmReopening) {
                throw ValidationException::withMessages(['confirm_reopening' => 'Confirme a reabertura deste lead antes de alterar sua etapa.']);
            }

            $record->fill($data);
            if (! $record->exists || $record->isDirty()) {
                abort_unless($record->saveOrFail(), 409);
            }

            return $record;
        }, attempts: 3);
    }
}
