<?php

namespace App\Console\Commands;

use App\Actions\EnsureSaleCompletedCategory;
use App\Models\Account;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('accounts:ensure-sale-category')]
#[Description('Prepara de forma idempotente a categoria permanente de contas existentes, sem alterar categorias comuns.')]
class EnsureSaleCompletedCategories extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnsureSaleCompletedCategory $ensureCategory): int
    {
        foreach (Account::query()->lazyById() as $account) {
            try {
                $ensureCategory->handle($account);
            } catch (ValidationException) {
                $this->error('Conta '.$account->id.': libere a cor verde de finalização em uma categoria comum e execute novamente. Nenhuma categoria desta conta foi alterada.');

                return self::FAILURE;
            }
        }
        $this->info('Categorias de finalização verificadas.');

        return self::SUCCESS;
    }
}
