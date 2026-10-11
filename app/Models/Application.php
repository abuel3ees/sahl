<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Carbon\CarbonInterface;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'reference_number', 'service_id', 'gov_entity_id', 'applicant_id', 'status', 'form_data',
    'submitted_at', 'decided_at', 'decided_by', 'decision_note',
])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, HasUuids;

    /**
     * Al-Wahah's weekend. SLA clocks only count the other days.
     */
    public const WEEKEND = [CarbonInterface::FRIDAY, CarbonInterface::SATURDAY];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'form_data' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<GovEntity, $this>
     */
    public function govEntity(): BelongsTo
    {
        return $this->belongsTo(GovEntity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasMany<ApplicationStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ApplicationStatusChange::class)->chaperone('application')->orderBy('id');
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    /**
     * Working days since submission, up to the decision (or today while still waiting).
     */
    public function workingDaysWaiting(): int
    {
        $end = ($this->decided_at ?? now())->copy()->startOfDay();

        return $this->submitted_at->copy()->startOfDay()->diffInDaysFiltered(
            fn (CarbonInterface $day): bool => ! in_array($day->dayOfWeek, self::WEEKEND, true),
            $end,
        );
    }

    /**
     * Still waiting for a decision and past the service's SLA.
     */
    public function isOverdue(): bool
    {
        return $this->status->isAwaitingDecision() && $this->workingDaysWaiting() > $this->service->sla_working_days;
    }

    /**
     * The last day the ministry has under its SLA: `sla_working_days` working days after submission.
     */
    public function decisionDueBy(): CarbonInterface
    {
        $day = $this->submitted_at->copy()->startOfDay();
        $remaining = $this->service->sla_working_days;

        while ($remaining > 0) {
            $day = $day->addDay();

            if (! in_array($day->dayOfWeek, self::WEEKEND, true)) {
                $remaining--;
            }
        }

        return $day;
    }
}
