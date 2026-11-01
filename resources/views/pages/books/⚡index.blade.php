<?php

use App\Models\Book;
use App\Models\Borrowing;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingBookId = null;

    #[Locked]
    public ?int $deletingBookId = null;

    public bool $showModal = false;
    public bool $showDeleteModal = false;

    // Filters and Display
    public string $search = '';
    public string $selectedCategory = 'all';
    public string $availabilityFilter = 'all';
    public string $viewMode = 'table'; // 'table' or 'grid'

    // Form Fields
    public string $title = '';
    public string $author = '';
    public string $isbn = '';
    public string $category = '';
    public string $publisher = '';
    public string $published_year = '';
    public int $copies_total = 1;
    public int $copies_available = 1;
    public string $description = '';

    public function mount(): void
    {
        if (request()->boolean('create') || request()->has('create')) {
            $this->openCreateModal();
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedAvailabilityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCopiesTotal($value): void
    {
        if ($this->editingBookId === null) {
            $this->copies_available = (int) $value;
        }
    }

    protected function rules(): array
    {
        return [
            'title'            => 'required|string|max:255',
            'author'           => 'required|string|max:255',
            'isbn'             => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('books', 'isbn')->ignore($this->editingBookId),
            ],
            'category'         => 'nullable|string|max:100',
            'publisher'        => 'nullable|string|max:150',
            'published_year'   => 'nullable|integer|min:1000|max:' . (date('Y') + 1),
            'copies_total'     => 'required|integer|min:1|max:100000',
            'copies_available' => 'required|integer|min:0|lte:copies_total',
            'description'      => 'nullable|string|max:2000',
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editingBookId = null;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $book = Book::query()->findOrFail($id);

        $this->editingBookId = $book->id;
        $this->title = $book->title;
        $this->author = $book->author;
        $this->isbn = $book->isbn ?? '';
        $this->category = $book->category ?? '';
        $this->publisher = $book->publisher ?? '';
        $this->published_year = $book->published_year ? (string) $book->published_year : '';
        $this->copies_total = (int) $book->copies_total;
        $this->copies_available = (int) $book->copies_available;
        $this->description = $book->description ?? '';

        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingBookId = null;
        $this->title = '';
        $this->author = '';
        $this->isbn = '';
        $this->category = '';
        $this->publisher = '';
        $this->published_year = '';
        $this->copies_total = 1;
        $this->copies_available = 1;
        $this->description = '';
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate();
        $isEditing = $this->editingBookId !== null;

        $book = $isEditing
            ? Book::query()->findOrFail($this->editingBookId)
            : new Book();

        $book->title = trim($validated['title']);
        $book->author = trim($validated['author']);
        $book->isbn = filled($validated['isbn'] ?? null) ? trim($validated['isbn']) : null;
        $book->category = filled($validated['category'] ?? null) ? trim($validated['category']) : null;
        $book->publisher = filled($validated['publisher'] ?? null) ? trim($validated['publisher']) : null;
        $book->published_year = filled($validated['published_year'] ?? null) ? (int) $validated['published_year'] : null;
        $book->copies_total = (int) $validated['copies_total'];
        $book->copies_available = (int) $validated['copies_available'];
        $book->description = filled($validated['description'] ?? null) ? trim($validated['description']) : null;

        $book->save();

        $this->showModal = false;
        $this->resetForm();

        session()->flash(
            'status',
            $isEditing ? 'Book record updated successfully.' : 'New book added to library catalog.'
        );
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingBookId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteBook(): void
    {
        if (!$this->deletingBookId) {
            return;
        }

        $book = Book::query()->findOrFail($this->deletingBookId);

        if ($book->borrowings()->exists()) {
            session()->flash('error', 'Cannot delete this book because it has past or active circulation history.');
            $this->showDeleteModal = false;
            $this->deletingBookId = null;
            return;
        }

        $book->delete();

        session()->flash('status', 'Book removed from catalog successfully.');
        $this->showDeleteModal = false;
        $this->deletingBookId = null;
    }

    #[Computed]
    public function categories()
    {
        return Book::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values();
    }

    #[Computed]
    public function books()
    {
        $query = Book::query()->search($this->search);

        if ($this->selectedCategory !== 'all') {
            $query->category($this->selectedCategory);
        }

        if ($this->availabilityFilter === 'in_stock') {
            $query->where('copies_available', '>', 0);
        } elseif ($this->availabilityFilter === 'out_of_stock') {
            $query->where('copies_available', '<=', 0);
        }

        return $query->orderBy('title')->paginate(10);
    }
};
?>

<div class="p-4 md:p-8 max-w-7xl mx-auto space-y-6">

    <!-- =================================================================== -->
    <!-- 1. HEADER & ACTIONS                                                 -->
    <!-- =================================================================== -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-zinc-800">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                    Inventory &bull; {{ $this->books->total() }} Total
                </span>
            </div>
            <h1 class="mt-1 text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Book Management
            </h1>
            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                Manage titles, shelf availability, authorship, and book inventory.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-xl shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add New Book
            </button>
        </div>
    </div>

    <!-- Feedback Banners -->
    @if (session()->has('status'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300 text-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- =================================================================== -->
    <!-- 2. FILTER & TOOLBAR                                                 -->
    <!-- =================================================================== -->
    <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        
        <!-- Search Bar -->
        <div class="relative flex-1 max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-zinc-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search title, author, ISBN, category..."
                   class="w-full pl-10 pr-9 py-2 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            @if ($search)
                <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Filter Selects & View Toggle -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Category Filter -->
            <select wire:model.live="selectedCategory"
                    class="py-2 px-3 text-xs font-medium bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-700 dark:text-zinc-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                <option value="all">All Categories</option>
                @foreach ($this->categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>

            <!-- Availability Filter -->
            <select wire:model.live="availabilityFilter"
                    class="py-2 px-3 text-xs font-medium bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-700 dark:text-zinc-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                <option value="all">All Stock Levels</option>
                <option value="in_stock">In Stock Only</option>
                <option value="out_of_stock">Out of Stock</option>
            </select>

            <!-- View Switcher -->
            <div class="inline-flex rounded-xl p-1 bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700">
                <button type="button"
                        wire:click="$set('viewMode', 'table')"
                        class="p-1.5 rounded-lg text-xs font-semibold transition-all @if($viewMode === 'table') bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-xs @else text-slate-500 dark:text-zinc-400 hover:text-slate-900 @endif"
                        title="Table View">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                </button>
                <button type="button"
                        wire:click="$set('viewMode', 'grid')"
                        class="p-1.5 rounded-lg text-xs font-semibold transition-all @if($viewMode === 'grid') bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-xs @else text-slate-500 dark:text-zinc-400 hover:text-slate-900 @endif"
                        title="Grid View">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                </button>
            </div>
        </div>

    </div>

    <!-- =================================================================== -->
    <!-- 3. BOOK LISTING (TABLE OR GRID)                                     -->
    <!-- =================================================================== -->
    @if ($viewMode === 'table')
        <!-- TABLE VIEW -->
        <div class="rounded-2xl border border-slate-200/90 bg-white shadow-sm overflow-hidden dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/70 dark:bg-zinc-800/60 border-b border-slate-100 dark:border-zinc-800 text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-semibold text-[11px]">
                            <th class="py-3.5 px-5">Book Title & Author</th>
                            <th class="py-3.5 px-4">ISBN / Category</th>
                            <th class="py-3.5 px-4">Publisher & Year</th>
                            <th class="py-3.5 px-4">Shelf Inventory</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/80">
                        @forelse ($this->books as $book)
                            <tr wire:key="book-row-{{ $book->id }}" class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                
                                <!-- Title & Author -->
                                <td class="py-4 px-5">
                                    <div class="font-bold text-sm text-slate-900 dark:text-white">
                                        {{ $book->title }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5 flex items-center gap-1.5">
                                        <span>by {{ $book->author }}</span>
                                    </div>
                                    @if ($book->description)
                                        <div class="text-[11px] text-slate-400 dark:text-zinc-500 line-clamp-1 mt-1 max-w-sm">
                                            {{ $book->description }}
                                        </div>
                                    @endif
                                </td>

                                <!-- ISBN & Category -->
                                <td class="py-4 px-4">
                                    @if ($book->category)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 mb-1">
                                            {{ $book->category }}
                                        </span>
                                    @endif
                                    <div class="font-mono text-[11px] text-slate-500 dark:text-zinc-400">
                                        {{ $book->isbn ?: 'No ISBN' }}
                                    </div>
                                </td>

                                <!-- Publisher & Year -->
                                <td class="py-4 px-4 text-slate-600 dark:text-zinc-300">
                                    <div>{{ $book->publisher ?: '—' }}</div>
                                    <div class="text-[11px] text-slate-400 dark:text-zinc-500 tabular-nums">
                                        {{ $book->published_year ?: 'Year N/A' }}
                                    </div>
                                </td>

                                <!-- Shelf Inventory Meter -->
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-bold text-sm text-slate-800 dark:text-zinc-100 tabular-nums">
                                            {{ $book->copies_available }}
                                        </span>
                                        <span class="text-slate-400 dark:text-zinc-500">/ {{ $book->copies_total }}</span>
                                    </div>
                                    <!-- Mini progress bar -->
                                    <div class="w-24 h-1.5 bg-slate-100 dark:bg-zinc-800 rounded-full mt-1.5 overflow-hidden">
                                        @php
                                            $percent = $book->copies_total > 0 ? min(100, round(($book->copies_available / $book->copies_total) * 100)) : 0;
                                            $barColor = $percent > 40 ? 'bg-emerald-500' : ($percent > 0 ? 'bg-amber-500' : 'bg-rose-500');
                                        @endphp
                                       <div
                                            class="h-full {{ $barColor }} rounded-full"
                                            @style(['width: ' . $percent . '%'])>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-4 px-4">
                                    @if ($book->copies_available > 2)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 ring-1 ring-emerald-600/20">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Available
                                        </span>
                                    @elseif ($book->copies_available > 0)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 ring-1 ring-amber-600/20">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            Low Stock ({{ $book->copies_available }})
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 ring-1 ring-rose-600/20">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            Out of Stock
                                        </span>
                                    @endif
                                </td>

                                <!-- Actions -->
                                <td class="py-4 px-5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        @if ($book->copies_available > 0)
                                            <a href="/borrowings?book_id={{ $book->id }}" 
                                               class="p-1.5 rounded-lg text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/60 transition-colors"
                                               title="Issue loan for this book">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                            </a>
                                        @endif

                                        <button type="button"
                                                wire:click="edit({{ $book->id }})"
                                                class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors"
                                                title="Edit book metadata">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <button type="button"
                                                wire:click="confirmDelete({{ $book->id }})"
                                                class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                                title="Delete book">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-16 text-center text-slate-400 dark:text-zinc-500">
                                    <div class="max-w-xs mx-auto">
                                        <svg class="w-10 h-10 mx-auto text-slate-300 dark:text-zinc-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                        <p class="font-bold text-slate-700 dark:text-zinc-300">No books found</p>
                                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Try adjusting your search query or add a new book to the catalog.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($this->books->hasPages())
                <div class="p-4 border-t border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-850">
                    {{ $this->books->links() }}
                </div>
            @endif
        </div>
    @else
        <!-- GRID VIEW -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @forelse ($this->books as $book)
                <div wire:key="book-card-{{ $book->id }}" class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm hover:shadow-md transition-shadow dark:border-zinc-800 dark:bg-zinc-900 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <span class="h-10 w-10 shrink-0 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold">
                                📖
                            </span>
                            @if ($book->copies_available > 2)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 ring-1 ring-emerald-600/20">
                                    {{ $book->copies_available }} In Stock
                                </span>
                            @elseif ($book->copies_available > 0)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 ring-1 ring-amber-600/20">
                                    {{ $book->copies_available }} Low Stock
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 ring-1 ring-rose-600/20">
                                    Out of Stock
                                </span>
                            @endif
                        </div>

                        <h3 class="mt-3.5 text-base font-bold text-slate-900 dark:text-white line-clamp-2">
                            {{ $book->title }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">
                            by {{ $book->author }}
                        </p>

                        @if ($book->category)
                            <div class="mt-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $book->category }}
                                </span>
                            </div>
                        @endif

                        @if ($book->description)
                            <p class="text-[11px] text-slate-400 dark:text-zinc-500 line-clamp-2 mt-2.5">
                                {{ $book->description }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-5 pt-3.5 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                        <span class="text-[11px] font-mono text-slate-400 dark:text-zinc-500">
                            {{ $book->isbn ?: 'No ISBN' }}
                        </span>

                        <div class="flex items-center gap-1">
                            <button type="button"
                                    wire:click="edit({{ $book->id }})"
                                    class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button type="button"
                                    wire:click="confirmDelete({{ $book->id }})"
                                    class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-16 text-center text-slate-400 dark:text-zinc-500">
                    No books match your criteria.
                </div>
            @endforelse
        </div>

        @if ($this->books->hasPages())
            <div class="mt-4">
                {{ $this->books->links() }}
            </div>
        @endif
    @endif

    <!-- =================================================================== -->
    <!-- 4. ADD / EDIT BOOK MODAL                                            -->
    <!-- =================================================================== -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="relative w-full max-w-2xl bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                            {{ $editingBookId ? 'Edit Book Details' : 'Add New Book to Catalog' }}
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                            Enter the book classification, publication details, and inventory stock.
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form wire:submit="save" class="p-6 space-y-4">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        
                        <!-- Title -->
                        <div class="md:col-span-2">
                            <label for="book_title" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Book Title <span class="text-rose-500">*</span>
                            </label>
                            <input id="book_title" type="text" wire:model="title"
                                   placeholder="e.g. Clean Architecture: A Craftsman's Guide"
                                   class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('title') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('title')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Author -->
                        <div>
                            <label for="book_author" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Author <span class="text-rose-500">*</span>
                            </label>
                            <input id="book_author" type="text" wire:model="author"
                                   placeholder="e.g. Robert C. Martin"
                                   class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('author') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('author')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- ISBN -->
                        <div>
                            <label for="book_isbn" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                ISBN
                            </label>
                            <input id="book_isbn" type="text" wire:model="isbn"
                                   placeholder="e.g. 9780134494166"
                                   class="w-full px-3.5 py-2.5 text-sm font-mono bg-slate-50 dark:bg-zinc-800 border @error('isbn') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('isbn')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Category -->
                        <div>
                            <label for="book_category" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Category / Genre
                            </label>
                            <input id="book_category" type="text" wire:model="category"
                                   placeholder="e.g. Software Engineering"
                                   class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('category') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('category')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Publisher -->
                        <div>
                            <label for="book_publisher" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Publisher
                            </label>
                            <input id="book_publisher" type="text" wire:model="publisher"
                                   placeholder="e.g. Prentice Hall"
                                   class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('publisher') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('publisher')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Published Year -->
                        <div>
                            <label for="book_year" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Published Year
                            </label>
                            <input id="book_year" type="number" wire:model="published_year"
                                   placeholder="e.g. 2017"
                                   class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('published_year') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('published_year')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Total Copies -->
                        <div>
                            <label for="book_copies_total" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Total Copies <span class="text-rose-500">*</span>
                            </label>
                            <input id="book_copies_total" type="number" min="1" wire:model.live="copies_total"
                                   class="w-full px-3.5 py-2.5 text-sm font-mono bg-slate-50 dark:bg-zinc-800 border @error('copies_total') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('copies_total')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Available Copies -->
                        <div>
                            <label for="book_copies_avail" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Available Copies <span class="text-rose-500">*</span>
                            </label>
                            <input id="book_copies_avail" type="number" min="0" wire:model="copies_available"
                                   class="w-full px-3.5 py-2.5 text-sm font-mono bg-slate-50 dark:bg-zinc-800 border @error('copies_available') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('copies_available')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <label for="book_desc" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Description / Synopsis
                            </label>
                            <textarea id="book_desc" wire:model="description" rows="3"
                                      placeholder="Brief overview of the book's subject or contents..."
                                      class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('description') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                            @error('description')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-end gap-3">
                        <button type="button"
                                wire:click="closeModal"
                                class="px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-xl shadow-sm transition-all focus:ring-2 focus:ring-indigo-500/20">
                            {{ $editingBookId ? 'Save Changes' : 'Create Book' }}
                        </button>
                    </div>

                </form>

            </div>
        </div>
    @endif

    <!-- =================================================================== -->
    <!-- 5. DELETE CONFIRMATION MODAL                                        -->
    <!-- =================================================================== -->
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200 dark:border-zinc-800 p-6 shadow-2xl space-y-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Delete Book Record?</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">This action cannot be undone.</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 dark:text-zinc-300">
                    Are you sure you want to permanently remove this book from the catalog? This is only permitted if the book has no active or past circulation ledger history.
                </p>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button"
                            wire:click="$set('showDeleteModal', false)"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="deleteBook"
                            class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs transition-colors">
                        Yes, Delete Book
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
