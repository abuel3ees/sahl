<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'gov_entity_id', 'code', 'slug', 'name_ar', 'name_en', 'description_ar', 'description_en',
    'fee_minor', 'currency', 'sla_working_days', 'validity_days', 'is_active',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    public const GOOD_CONDUCT = 'GOOD_CONDUCT';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_minor' => 'integer',
            'sla_working_days' => 'integer',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<GovEntity, $this>
     */
    public function govEntity(): BelongsTo
    {
        return $this->belongsTo(GovEntity::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
