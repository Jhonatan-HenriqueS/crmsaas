<?php

namespace App\Models;

use App\Actions\EnsureSaleCompletedCategory;
use Carbon\CarbonImmutable;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $type
 * @property string|null $name
 * @property string|null $goal_amount
 * @property CarbonImmutable|null $goal_deadline
 */
#[Fillable(['type', 'name', 'goal_amount', 'goal_deadline'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    public const TYPES = ['empresa', 'agencia'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'goal_amount' => 'decimal:2',
            'goal_deadline' => 'immutable_date:Y-m-d',
        ];
    }

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            if (! parent::save($options)) {
                return false;
            }

            app(EnsureSaleCompletedCategory::class)->handle($this);

            return true;
        });
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<LeadCategory, $this> */
    public function leadCategories(): HasMany
    {
        return $this->hasMany(LeadCategory::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')->withPivot('role')->withTimestamps();
    }
}
