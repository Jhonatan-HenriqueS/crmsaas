<?php

namespace App\Models;

use Database\Factories\LeadCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property string $color
 * @property string|null $system_key
 * @property-read bool $is_sale_completed
 */
#[Fillable(['name', 'color'])]
#[Hidden(['account_id', 'system_key'])]
class LeadCategory extends Model
{
    /** @use HasFactory<LeadCategoryFactory> */
    use HasFactory;

    public const SALE_COMPLETED = 'sale_completed';

    /** Initial color of the permanent sale category in new accounts. */
    public const SALE_COMPLETED_COLOR = '#00ff0a';

    /** @var list<string> */
    protected $appends = ['is_sale_completed'];

    protected static function booted(): void
    {
        static::updating(function (self $category): void {
            if ($category->isDirty('system_key')) {
                throw new \LogicException('A identidade de sistema da categoria é imutável.');
            }
        });
        static::deleting(fn (self $category): ?bool => $category->newQuery()->whereKey($category->id)->where('system_key', self::SALE_COMPLETED)->exists() ? false : null);
    }

    /** @return Attribute<bool, never> */
    protected function isSaleCompleted(): Attribute
    {
        return Attribute::get(fn (): bool => $this->system_key === self::SALE_COMPLETED);
    }

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
