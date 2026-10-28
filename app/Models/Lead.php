<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $account_id
 * @property int $category_id
 * @property string $name
 * @property string|null $description
 * @property CarbonImmutable $first_contact_date
 * @property CarbonImmutable|null $next_contact_date
 * @property string|null $potential_amount
 * @property string|null $charged_amount
 * @property string|null $maps_url
 * @property string|null $latitude
 * @property string|null $longitude
 */
#[Fillable(['category_id', 'name', 'description', 'first_contact_date', 'next_contact_date', 'potential_amount', 'charged_amount', 'maps_url', 'latitude', 'longitude'])]
#[Hidden(['account_id'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'description' => null,
        'next_contact_date' => null,
        'potential_amount' => null,
        'charged_amount' => null,
        'maps_url' => null,
        'latitude' => null,
        'longitude' => null,
    ];

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<LeadCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(LeadCategory::class, 'category_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'first_contact_date' => 'immutable_date:Y-m-d',
            'next_contact_date' => 'immutable_date:Y-m-d',
            'potential_amount' => 'decimal:2',
            'charged_amount' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /** @return Attribute<never, mixed> */
    protected function firstContactDate(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): string => $this->asDate($value)->format('Y-m-d'));
    }

    /** @return Attribute<never, mixed> */
    protected function nextContactDate(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): ?string => $value === null ? null : $this->asDate($value)->format('Y-m-d'));
    }
}
