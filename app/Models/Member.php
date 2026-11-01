<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_code',
        'name',
        'email',
        'phone',
        'address',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Relationship: A member has many borrowings.
     */
    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    /**
     * Active borrowings (unreturned).
     */
    public function activeBorrowings(): HasMany
    {
        return $this->borrowings()->whereNull('returned_at');
    }

    /**
     * Overdue borrowings.
     */
    public function overdueBorrowings(): HasMany
    {
        return $this->borrowings()
            ->whereNull('returned_at')
            ->whereDate('due_date', '<', today());
    }

    /**
     * Helper: Check if member is eligible to borrow.
     */
    public function isEligible(): bool
    {
        return $this->status === 'active' && $this->overdueBorrowings()->count() === 0;
    }

    /**
     * Scope: Active members.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Search members by name, code, email, or phone.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('member_code', 'like', "%{$term}%")
              ->orWhere('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}

