<?php

namespace App\Models;

use Database\Factories\LeadCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property string $color
 */
#[Fillable(['name', 'color'])]
class LeadCategory extends Model
{
    /** @use HasFactory<LeadCategoryFactory> */
    use HasFactory;

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'category_id');
    }
}
