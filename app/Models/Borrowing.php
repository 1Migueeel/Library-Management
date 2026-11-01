<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Borrowing extends Model
{
    use HasFactory;

    public const DAILY_FINE_RATE = 10.00; // $10.00 fine per day overdue

    protected $fillable = [
        'book_id',
        'member_id',
        'borrowed_at',
        'due_date',
        'returned_at',
        'notes',
    ];

    protected $casts = [
        'borrowed_at' => 'date',
        'due_date'    => 'date',
        'returned_at' => 'date',
    ];

    /**
     * Relationship: The borrowed book.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Relationship: The borrower/member.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Helper: Check if loan is currently overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->returned_at !== null) {
            return $this->returned_at->greaterThan($this->due_date);
        }

        return Carbon::today()->greaterThan($this->due_date);
    }

    /**
     * Helper: Calculate number of days overdue.
     */
    public function daysOverdue(): int
    {
        $comparisonDate = $this->returned_at ?? Carbon::today();

        if ($comparisonDate->greaterThan($this->due_date)) {
            return (int) $this->due_date->diffInDays($comparisonDate);
        }

        return 0;
    }

    /**
     * Helper: Calculate accrued fine amount.
     */
    public function calculateFine(float $ratePerDay = self::DAILY_FINE_RATE): float
    {
        return round($this->daysOverdue() * $ratePerDay, 2);
    }

    /**
     * Helper: Get current computed status string ('returned', 'overdue', 'active').
     */
    public function getComputedStatusAttribute(): string
    {
        if ($this->returned_at !== null) {
            return 'returned';
        }

        return $this->isOverdue() ? 'overdue' : 'active';
    }

    /**
     * Scope: Active loans (not yet returned).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    /**
     * Scope: Overdue active loans.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNull('returned_at')
            ->whereDate('due_date', '<', today());
    }

    /**
     * Scope: Completed/Returned loans.
     */
    public function scopeReturned(Builder $query): Builder
    {
        return $query->whereNotNull('returned_at');
    }

    /**
     * Scope: Search across book title, ISBN, member name, and member code.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->whereHas('book', function (Builder $bookQuery) use ($term) {
                $bookQuery->where('title', 'like', "%{$term}%")
                    ->orWhere('isbn', 'like', "%{$term}%");
            })->orWhereHas('member', function (Builder $memberQuery) use ($term) {
                $memberQuery->where('name', 'like', "%{$term}%")
                    ->orWhere('member_code', 'like', "%{$term}%");
            });
        });
    }
}

