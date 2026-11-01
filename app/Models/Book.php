<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'isbn',
        'category',
        'publisher',
        'published_year',
        'copies_total',
        'copies_available',
        'description',
    ];

    protected $casts = [
        'published_year'   => 'integer',
        'copies_total'     => 'integer',
        'copies_available' => 'integer',
    ];

    /**
     * Relationship: A book has many borrowing records.
     */
    public function borrowings(): HasMany
    {
        return $this->hasMany(Borrowing::class);
    }

    /**
     * Helper: Check if copies are available for borrowing.
     */
    public function isAvailable(): bool
    {
        return (int) $this->copies_available > 0;
    }

    /**
     * Scope: Filter books that currently have available copies.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('copies_available', '>', 0);
    }

    /**
     * Scope: Search by title, author, ISBN, publisher, or category.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('author', 'like', "%{$term}%")
              ->orWhere('isbn', 'like', "%{$term}%")
              ->orWhere('publisher', 'like', "%{$term}%")
              ->orWhere('category', 'like', "%{$term}%");
        });
    }

    /**
     * Scope: Filter by category.
     */
    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        if (blank($category) || $category === 'all') {
            return $query;
        }

        return $query->where('category', $category);
    }
}

