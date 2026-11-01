<?php

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $book_id = '';

    // Issue Loan Form
    public string $member_id = '';
    public string $borrowed_at = '';
    public string $due_date = '';
    public string $notes = '';

    // Modals
    public bool $showIssueModal = false;
    public bool $showReturnModal = false;

    // Return Settlement Form
    #[Locked]
    public ?int $returningBorrowingId = null;
    public ?Borrowing $returningRecord = null;
    public string $returnNotes = '';

    // Search and Status Tabs
    public string $search = '';
    public string $statusTab = 'all'; // 'all', 'active', 'overdue', 'returned'

    public function mount(): void
    {
        $this->borrowed_at = Carbon::today()->toDateString();
        $this->due_date = Carbon::today()->addDays(7)->toDateString();

        if (filled($this->book_id)) {
            $this->showIssueModal = true;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusTab(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'book_id'     => 'required|integer|exists:books,id',
            'member_id'   => 'required|integer|exists:members,id',
            'borrowed_at' => 'required|date',
            'due_date'    => 'required|date|after_or_equal:borrowed_at',
            'notes'       => 'nullable|string|max:1000',
        ];
    }

    public function openIssueModal(?int $bookId = null): void
    {
        if ($bookId) {
            $this->book_id = (string) $bookId;
        }

        $this->borrowed_at = Carbon::today()->toDateString();
        $this->due_date = Carbon::today()->addDays(7)->toDateString();
        $this->resetValidation();
        $this->showIssueModal = true;
    }

    public function closeIssueModal(): void
    {
        $this->showIssueModal = false;
        $this->book_id = '';
        $this->member_id = '';
        $this->notes = '';
        $this->resetValidation();
    }

    public function issueLoan(): void
    {
        $validated = $this->validate();

        try {
            DB::transaction(function () use ($validated) {
                // Lock book and verify stock
                $book = Book::query()->lockForUpdate()->findOrFail((int) $validated['book_id']);
                if ($book->copies_available < 1) {
                    throw ValidationException::withMessages([
                        'book_id' => 'No copies of this book are currently available on shelves.',
                    ]);
                }

                // Lock member and verify eligibility
                $member = Member::query()->lockForUpdate()->findOrFail((int) $validated['member_id']);
                if ($member->status !== 'active') {
                    throw ValidationException::withMessages([
                        'member_id' => 'This member account is currently inactive or suspended.',
                    ]);
                }

                // Check for existing overdue delinquency
                if ($member->overdueBorrowings()->exists()) {
                    throw ValidationException::withMessages([
                        'member_id' => 'Borrowing blocked: Member has overdue books that must be returned first.',
                    ]);
                }

                // Decrement inventory
                $book->copies_available = max(0, $book->copies_available - 1);
                $book->save();

                // Create borrowing record
                Borrowing::create([
                    'book_id'     => $book->id,
                    'member_id'   => $member->id,
                    'borrowed_at' => $validated['borrowed_at'],
                    'due_date'    => $validated['due_date'],
                    'returned_at' => null,
                    'notes'       => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
                ]);
            });

            $this->closeIssueModal();
            session()->flash('status', 'Book loan issued successfully (Standard 7-day loan window).');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Error issuing loan: ' . $e->getMessage());
        }
    }

    public function confirmReturn(int $id): void
    {
        $this->returningBorrowingId = $id;
        $this->returningRecord = Borrowing::with(['book', 'member'])->findOrFail($id);
        $this->returnNotes = $this->returningRecord->notes ?? '';
        $this->showReturnModal = true;
    }

    public function closeReturnModal(): void
    {
        $this->showReturnModal = false;
        $this->returningBorrowingId = null;
        $this->returningRecord = null;
        $this->returnNotes = '';
    }

    public function processReturn(): void
    {
        if (!$this->returningBorrowingId) {
            return;
        }

        $fineMessage = '';

        DB::transaction(function () use (&$fineMessage) {
            $borrowing = Borrowing::query()->lockForUpdate()->findOrFail($this->returningBorrowingId);

            if ($borrowing->returned_at !== null) {
                return;
            }

            $book = Book::query()->lockForUpdate()->findOrFail($borrowing->book_id);

            // Record return date
            $borrowing->returned_at = Carbon::today()->toDateString();
            if (filled($this->returnNotes)) {
                $borrowing->notes = trim($this->returnNotes);
            }
            $borrowing->save();

            // Restore book copy inventory
            $book->copies_available = min((int) $book->copies_total, (int) $book->copies_available + 1);
            $book->save();

            // Calculate fine summary for flash
            if ($borrowing->isOverdue()) {
                $fine = $borrowing->calculateFine();
                $days = $borrowing->daysOverdue();
                $fineMessage = " Returned with {$days} days overdue. Accrued fine: $" . number_format($fine, 2) . ".";
            }
        });

        $this->closeReturnModal();
        session()->flash('status', 'Book returned and inventory replenished successfully.' . $fineMessage);
    }

    #[Computed]
    public function books()
    {
        return Book::query()->where('copies_available', '>', 0)->orderBy('title')->get();
    }

    #[Computed]
    public function members()
    {
        return Member::query()->where('status', 'active')->orderBy('name')->get();
    }

    #[Computed]
    public function counts()
    {
        return [
            'all'      => Borrowing::count(),
            'active'   => Borrowing::active()->count(),
            'overdue'  => Borrowing::overdue()->count(),
            'returned' => Borrowing::returned()->count(),
        ];
    }

    #[Computed]
    public function borrowings()
    {
        $query = Borrowing::with(['book', 'member'])->search($this->search);

        if ($this->statusTab === 'active') {
            $query->active();
        } elseif ($this->statusTab === 'overdue') {
            $query->overdue();
        } elseif ($this->statusTab === 'returned') {
            $query->returned();
        }

        return $query->latest('id')->paginate(10);
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
                    Borrowing History &bull; {{ $this->counts['active'] }} Active Loans
                </span>
                @if ($this->counts['overdue'] > 0)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        {{ $this->counts['overdue'] }} Overdue
                    </span>
                @endif
            </div>
            <h1 class="mt-1 text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Borrowing History
            </h1>
            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                Issue student loans, settle returned books, and track $10.00/day overdue penalty fees.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button"
                    wire:click="openIssueModal()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-xl shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Issue New Loan
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
    <!-- 2. STATUS TABS & SEARCH TOOLBAR                                     -->
    <!-- =================================================================== -->
    <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        
        <!-- Status Tabs Pill -->
        <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-100 dark:bg-zinc-800 rounded-xl border border-slate-200/80 dark:border-zinc-700">
            <button type="button"
                    wire:click="$set('statusTab', 'all')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all @if($statusTab === 'all') bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-xs @else text-slate-600 dark:text-zinc-400 hover:text-slate-900 @endif">
                All Records ({{ $this->counts['all'] }})
            </button>
            <button type="button"
                    wire:click="$set('statusTab', 'active')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all @if($statusTab === 'active') bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-xs @else text-slate-600 dark:text-zinc-400 hover:text-slate-900 @endif">
                Active Loans ({{ $this->counts['active'] }})
            </button>
            <button type="button"
                    wire:click="$set('statusTab', 'overdue')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all @if($statusTab === 'overdue') bg-rose-600 text-white shadow-xs @else text-rose-600 dark:text-rose-400 hover:bg-rose-50 @endif">
                Overdue Delinquencies ({{ $this->counts['overdue'] }})
            </button>
            <button type="button"
                    wire:click="$set('statusTab', 'returned')"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all @if($statusTab === 'returned') bg-white dark:bg-zinc-700 text-slate-900 dark:text-white shadow-xs @else text-slate-600 dark:text-zinc-400 hover:text-slate-900 @endif">
                Returned ({{ $this->counts['returned'] }})
            </button>
        </div>

        <!-- Search Bar -->
        <div class="relative flex-1 max-w-sm">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 dark:text-zinc-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search book title, member name, code..."
                   class="w-full pl-10 pr-9 py-2 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            @if ($search)
                <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

    </div>

    <!-- =================================================================== -->
    <!-- 3. CIRCULATION LEDGER TABLE                                         -->
    <!-- =================================================================== -->
    <div class="rounded-2xl border border-slate-200/90 bg-white shadow-sm overflow-hidden dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/70 dark:bg-zinc-800/60 border-b border-slate-100 dark:border-zinc-800 text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-semibold text-[11px]">
                        <th class="py-3.5 px-5">Book Information</th>
                        <th class="py-3.5 px-4">Borrower Details</th>
                        <th class="py-3.5 px-4">Loan Date</th>
                        <th class="py-3.5 px-4">Due Date</th>
                        <th class="py-3.5 px-4">Circulation Status</th>
                        <th class="py-3.5 px-5 text-right">Desk Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/80">
                    @forelse ($this->borrowings as $record)
                        <tr wire:key="borrow-row-{{ $record->id }}" class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                            
                            <!-- Book Info -->
                            <td class="py-4 px-5">
                                <div class="font-bold text-sm text-slate-900 dark:text-white">
                                    {{ $record->book?->title ?? 'Deleted Book' }}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-zinc-400 font-mono mt-0.5">
                                    ISBN: {{ $record->book?->isbn ?: 'N/A' }}
                                </div>
                            </td>

                            <!-- Member Info -->
                            <td class="py-4 px-4">
                                <div class="font-bold text-slate-800 dark:text-zinc-200">
                                    {{ $record->member?->name ?? 'Deleted Member' }}
                                </div>
                                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 border border-slate-200/60 dark:border-zinc-700">
                                    {{ $record->member?->member_code ?? 'N/A' }}
                                </span>
                            </td>

                            <!-- Loan Date -->
                            <td class="py-4 px-4 text-slate-600 dark:text-zinc-300 tabular-nums">
                                {{ $record->borrowed_at?->format('M d, Y') ?? '—' }}
                            </td>

                            <!-- Due Date -->
                            <td class="py-4 px-4 tabular-nums">
                                <div class="font-medium text-slate-800 dark:text-zinc-200">
                                    {{ $record->due_date?->format('M d, Y') ?? '—' }}
                                </div>
                                @if (!$record->returned_at)
                                    @if ($record->isOverdue())
                                        <div class="text-[10px] font-bold text-rose-600 dark:text-rose-400 mt-0.5">
                                            {{ $record->daysOverdue() }} {{ Str::plural('day', $record->daysOverdue()) }} overdue
                                        </div>
                                    @else
                                        @php
                                            $daysLeft = (int) today()->diffInDays($record->due_date, false);
                                        @endphp
                                        <div class="text-[10px] text-slate-400 dark:text-zinc-500 mt-0.5">
                                            {{ $daysLeft >= 0 ? "{$daysLeft} days remaining" : 'Due today' }}
                                        </div>
                                    @endif
                                @endif
                            </td>

                            <!-- Status & Fine Pill -->
                            <td class="py-4 px-4">
                                @if ($record->returned_at)
                                    <div>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 ring-1 ring-emerald-600/20">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Returned
                                        </span>
                                        <div class="text-[10px] text-slate-400 dark:text-zinc-500 mt-1 tabular-nums">
                                            on {{ $record->returned_at->format('M d, Y') }}
                                        </div>
                                    </div>
                                @elseif ($record->isOverdue())
                                    <div>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 ring-1 ring-rose-600/20">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            Overdue Loan
                                        </span>
                                        <div class="text-[10px] font-bold font-mono text-rose-700 dark:text-rose-300 mt-1">
                                            Fine: ${{ number_format($record->calculateFine(), 2) }}
                                        </div>
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400 ring-1 ring-sky-600/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                        Active Loan
                                    </span>
                                @endif
                            </td>

                            <!-- Action -->
                            <td class="py-4 px-5 text-right">
                                @if (!$record->returned_at)
                                    <button type="button"
                                            wire:click="confirmReturn({{ $record->id }})"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-indigo-700 dark:text-indigo-300 hover:text-white bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-600 dark:hover:bg-indigo-600 rounded-xl transition-all border border-indigo-200/80 dark:border-indigo-800">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Process Return
                                    </button>
                                @else
                                    <span class="text-[11px] text-slate-400 dark:text-zinc-500 italic">
                                        Completed
                                    </span>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center text-slate-400 dark:text-zinc-500">
                                <div class="max-w-xs mx-auto">
                                    <svg class="w-10 h-10 mx-auto text-slate-300 dark:text-zinc-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p class="font-bold text-slate-700 dark:text-zinc-300">No loan records found</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Issue a new book borrowing transaction to start tracking circulation.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->borrowings->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-850">
                {{ $this->borrowings->links() }}
            </div>
        @endif
    </div>

    <!-- =================================================================== -->
    <!-- 4. ISSUE NEW LOAN MODAL                                             -->
    <!-- =================================================================== -->
    @if ($showIssueModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                            Issue New Book Loan
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                            Standard 7-day loan period with live stock verification.
                        </p>
                    </div>
                    <button type="button" wire:click="closeIssueModal" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form wire:submit="issueLoan" class="p-6 space-y-4">
                    
                    <div class="space-y-4">
                        
                        <!-- Book Selection -->
                        <div>
                            <label for="borrow_book" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Select Book <span class="text-rose-500">*</span>
                            </label>
                            <select id="borrow_book" wire:model="book_id"
                                    class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('book_id') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">-- Choose Book from Available Inventory --</option>
                                @foreach ($this->books as $b)
                                    <option value="{{ $b->id }}">
                                        {{ $b->title }} ({{ $b->copies_available }} available on shelves)
                                    </option>
                                @endforeach
                            </select>
                            @error('book_id')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Member Selection -->
                        <div>
                            <label for="borrow_member" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Select Borrowing Member <span class="text-rose-500">*</span>
                            </label>
                            <select id="borrow_member" wire:model="member_id"
                                    class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('member_id') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">-- Choose Registered Member --</option>
                                @foreach ($this->members as $m)
                                    <option value="{{ $m->id }}">
                                        {{ $m->name }} ({{ $m->member_code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('member_id')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Date Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="borrow_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                    Loan Date <span class="text-rose-500">*</span>
                                </label>
                                <input id="borrow_date" type="date" wire:model="borrowed_at"
                                       class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                @error('borrowed_at')
                                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="due_date" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                    Due Date (+7 Days) <span class="text-rose-500">*</span>
                                </label>
                                <input id="due_date" type="date" wire:model="due_date"
                                       class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                @error('due_date')
                                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="borrow_notes" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Circulation Notes (Optional)
                            </label>
                            <textarea id="borrow_notes" wire:model="notes" rows="2"
                                      placeholder="Any notes on book condition or course reservation..."
                                      class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                            @error('notes')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-end gap-3">
                        <button type="button"
                                wire:click="closeIssueModal"
                                class="px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-xl shadow-sm transition-all focus:ring-2 focus:ring-indigo-500/20">
                            Confirm Loan Issuance
                        </button>
                    </div>

                </form>

            </div>
        </div>
    @endif

    <!-- =================================================================== -->
    <!-- 5. PROCESS RETURN & FINE SETTLEMENT MODAL                           -->
    <!-- =================================================================== -->
    @if ($showReturnModal && $returningRecord)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="relative w-full max-w-md bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">
                            Process Book Return
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                            Verify item condition and settle any accrued fines.
                        </p>
                    </div>
                    <button type="button" wire:click="closeReturnModal" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 rounded-xl transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    
                    <!-- Item details callout -->
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-200/80 dark:border-zinc-700/60 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-zinc-400">Title:</span>
                            <span class="font-bold text-slate-900 dark:text-white text-right max-w-[200px] truncate">{{ $returningRecord->book?->title }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-zinc-400">Borrower:</span>
                            <span class="font-semibold text-slate-800 dark:text-zinc-200">{{ $returningRecord->member?->name }} ({{ $returningRecord->member?->member_code }})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-zinc-400">Due Date:</span>
                            <span class="font-mono text-slate-700 dark:text-zinc-300">{{ $returningRecord->due_date?->format('M d, Y') }}</span>
                        </div>
                    </div>

                    <!-- Overdue Fine Alert if Delinquent -->
                    @if ($returningRecord->isOverdue())
                        @php
                            $fine = $returningRecord->calculateFine();
                            $days = $returningRecord->daysOverdue();
                        @endphp
                        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 dark:bg-rose-950/40 dark:border-rose-900 text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-rose-800 dark:text-rose-300">Overdue Delinquency</span>
                                <span class="font-bold font-mono text-base text-rose-700 dark:text-rose-200">${{ number_format($fine, 2) }}</span>
                            </div>
                            <p class="text-[11px] text-rose-700 dark:text-rose-400">
                                This item is {{ $days }} {{ Str::plural('day', $days) }} overdue (charged at $10.00 / day). Collect fine upon book return.
                            </p>
                        </div>
                    @else
                        <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 dark:bg-emerald-950/40 dark:border-emerald-900 text-xs flex items-center gap-2 text-emerald-800 dark:text-emerald-300">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Returned on time. Zero fine amount due.</span>
                        </div>
                    @endif

                    <!-- Return Notes -->
                    <div>
                        <label for="return_notes" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                            Return Condition / Notes
                        </label>
                        <input id="return_notes" type="text" wire:model="returnNotes"
                               placeholder="e.g. Good condition, returned to shelf 3A"
                               class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>

                    <!-- Actions -->
                    <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-end gap-3">
                        <button type="button"
                                wire:click="closeReturnModal"
                                class="px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="button"
                                wire:click="processReturn"
                                class="px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xs transition-colors">
                            Confirm Return &amp; Restock
                        </button>
                    </div>

                </div>

            </div>
        </div>
    @endif

</div>