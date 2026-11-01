<x-layouts::app :title="__('Circulation Dashboard')">

    <div class="flex w-full flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto">

        <!-- =================================================================== -->
        <!-- 1. HEADER & QUICK ACTION BAR                                        -->
        <!-- =================================================================== -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-zinc-800">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                        Circulation Live
                    </span>
                    <span class="text-xs text-slate-500 dark:text-zinc-400">Standard 7-Day Loan Window</span>
                </div>
                <h1 class="mt-1 text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Library Overview
                </h1>
                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">
                    Real-time monitoring of book catalog inventory, active loans, and overdue records.
                </p>
            </div>

            <!-- Quick Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="/borrowings" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-700 border border-slate-200 dark:border-zinc-700 rounded-xl shadow-xs transition-all">
                    <svg class="w-4 h-4 text-slate-500 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Borrowing History
                </a>

                <a href="/books?create=1" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-xl shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    + Add Book
                </a>

                <a href="/members" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-zinc-200 bg-white dark:bg-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-700 border border-slate-200 dark:border-zinc-700 rounded-xl shadow-xs transition-all">
                    <svg class="w-4 h-4 text-slate-500 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Members
                </a>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 2. OVERDUE DELINQUENCY ALERT BANNER (If any overdue exists)         -->
        <!-- =================================================================== -->
        @if ($overdueCount > 0)
            <div class="rounded-2xl border border-rose-200 bg-gradient-to-r from-rose-50 via-rose-50/70 to-amber-50 p-5 dark:border-rose-900/60 dark:from-rose-950/40 dark:to-amber-950/20 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-start sm:items-center gap-3.5">
                        <div class="h-10 w-10 shrink-0 rounded-xl bg-rose-600 text-white flex items-center justify-center shadow-md shadow-rose-600/20">
                            <svg class="w-5 h-5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-rose-900 dark:text-rose-200">
                                Attention: {{ $overdueCount }} {{ Str::plural('Book', $overdueCount) }} Currently Overdue
                            </h2>
                            <p class="text-xs text-rose-700 dark:text-rose-300 mt-0.5">
                                Accrued delinquent fines total <strong class="font-mono text-rose-900 dark:text-rose-100">${{ number_format($totalOverdueFines, 2) }}</strong> at the rate of $10.00/day. Immediate settlement is advised.
                            </p>
                        </div>
                    </div>
                    <a href="/borrowings" 
                       class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-xs font-bold text-rose-700 hover:text-white bg-white hover:bg-rose-600 border border-rose-300 dark:border-rose-700 dark:bg-zinc-900 dark:text-rose-300 dark:hover:bg-rose-700 dark:hover:text-white rounded-xl shadow-xs transition-colors shrink-0">
                        View Overdue List &rarr;
                    </a>
                </div>
            </div>
        @endif

        <!-- =================================================================== -->
        <!-- 3. FOUR ANALYTICS STATS CARDS                                       -->
        <!-- =================================================================== -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">

            <!-- Card 1: Total Book Copies -->
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm hover:shadow-md transition-shadow dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-zinc-400">
                            Total Inventory
                        </p>
                        <h3 class="mt-2 text-3xl font-black text-slate-900 dark:text-white tabular-nums tracking-tight">
                            {{ number_format($totalBooksCopies) }}
                        </h3>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400 flex items-center justify-center">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                    <span class="inline-flex items-center font-medium text-emerald-600 dark:text-emerald-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                        {{ number_format($availableCopies) }} on shelves
                    </span>
                    <span class="text-slate-400 dark:text-zinc-500">
                        {{ number_format($bookTitlesCount) }} titles
                    </span>
                </div>
            </div>

            <!-- Card 2: Active Borrows -->
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm hover:shadow-md transition-shadow dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-zinc-400">
                            Active Loans
                        </p>
                        <h3 class="mt-2 text-3xl font-black text-slate-900 dark:text-white tabular-nums tracking-tight">
                            {{ number_format($activeLoansCount) }}
                        </h3>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-400 flex items-center justify-center">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                    <span class="text-sky-700 dark:text-sky-400 font-medium">
                        {{ $checkedOutCopies }} total checked out
                    </span>
                    <span class="text-slate-400 dark:text-zinc-500">7-day cycle</span>
                </div>
            </div>

            <!-- Card 3: Overdue Delinquencies -->
            <div class="relative overflow-hidden rounded-2xl border @if($overdueCount > 0) border-rose-300 dark:border-rose-800/80 bg-rose-50/20 @else border-slate-200/90 dark:border-zinc-800 bg-white @endif p-6 shadow-sm hover:shadow-md transition-shadow dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-zinc-400">
                            Overdue Books
                        </p>
                        <h3 class="mt-2 text-3xl font-black @if($overdueCount > 0) text-rose-600 dark:text-rose-400 @else text-slate-900 dark:text-white @endif tabular-nums tracking-tight">
                            {{ number_format($overdueCount) }}
                        </h3>
                    </div>
                    <div class="h-12 w-12 rounded-xl @if($overdueCount > 0) bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 @else bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400 @endif flex items-center justify-center">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                    @if ($overdueCount > 0)
                        <span class="text-rose-600 dark:text-rose-400 font-semibold font-mono">
                            ${{ number_format($totalOverdueFines, 2) }} fines
                        </span>
                        <span class="text-rose-600 dark:text-rose-400 font-medium">Requires action</span>
                    @else
                        <span class="text-emerald-600 dark:text-emerald-400 font-medium">Zero overdue</span>
                        <span class="text-slate-400 dark:text-zinc-500">All clear</span>
                    @endif
                </div>
            </div>

            <!-- Card 4: Registered Members -->
            <div class="relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm hover:shadow-md transition-shadow dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-zinc-400">
                            Registered Members
                        </p>
                        <h3 class="mt-2 text-3xl font-black text-slate-900 dark:text-white tabular-nums tracking-tight">
                            {{ number_format($totalMembersCount) }}
                        </h3>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400 flex items-center justify-center">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m16-10a4 4 0 1 0 0-8m4 18v-2a4 4 0 0 0-3-3.87M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                        </svg>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                    <span class="text-purple-700 dark:text-purple-400 font-medium">
                        {{ number_format($activeMembersCount) }} active accounts
                    </span>
                    <span class="text-slate-400 dark:text-zinc-500">
                        {{ round($totalMembersCount > 0 ? ($activeMembersCount / $totalMembersCount) * 100 : 0) }}% rate
                    </span>
                </div>
            </div>

        </div>

        <!-- =================================================================== -->
        <!-- 4. RECENT CIRCULATION TABLE & INSTITUTION POLICIES                  -->
        <!-- =================================================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Recent Loans Ledger (Takes 2 Columns) -->
            <div class="lg:col-span-2 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-zinc-800">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            Recent Circulation Activity
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                            Latest book transactions across students and library members.
                        </p>
                    </div>
                    <a href="/borrowings" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 hover:underline">
                        View All &rarr;
                    </a>
                </div>

                <div class="mt-4 overflow-x-auto">
                    @if ($recentCirculation->isNotEmpty())
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="text-slate-400 dark:text-zinc-500 border-b border-slate-100 dark:border-zinc-800 font-semibold uppercase tracking-wider text-[11px]">
                                    <th class="py-3 pr-4">Book Title</th>
                                    <th class="py-3 px-4">Borrower</th>
                                    <th class="py-3 px-4">Due Date</th>
                                    <th class="py-3 pl-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/80">
                                @foreach ($recentCirculation as $record)
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                        <td class="py-3 pr-4 font-semibold text-slate-800 dark:text-zinc-100 max-w-[200px] truncate">
                                            {{ $record->book?->title ?? 'Unknown Title' }}
                                        </td>
                                        <td class="py-3 px-4 text-slate-600 dark:text-zinc-300">
                                            <span class="block font-medium">{{ $record->member?->name ?? 'Unknown Member' }}</span>
                                            <span class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono">{{ $record->member?->member_code }}</span>
                                        </td>
                                        <td class="py-3 px-4 tabular-nums text-slate-500 dark:text-zinc-400">
                                            {{ $record->due_date?->format('M d, Y') ?? '—' }}
                                        </td>
                                        <td class="py-3 pl-4 text-right">
                                            @if ($record->returned_at)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 ring-1 ring-emerald-600/20">
                                                    Returned
                                                </span>
                                            @elseif ($record->isOverdue())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 ring-1 ring-rose-600/20">
                                                    Overdue ({{ $record->daysOverdue() }}d)
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400 ring-1 ring-sky-600/20">
                                                    Active Loan
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="py-12 text-center text-slate-400 dark:text-zinc-500 text-xs">
                            No recent circulation records found. Issue a loan to begin tracking.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Library Loan Policy & Rules (1 Column) -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-zinc-800">
                        <div class="h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            Circulation Policies
                        </h3>
                    </div>

                    <div class="mt-4 space-y-3.5 text-xs">
                        <div class="flex items-start gap-2.5">
                            <span class="h-5 w-5 shrink-0 rounded-full bg-slate-100 dark:bg-zinc-800 flex items-center justify-center font-bold text-[10px] text-slate-600 dark:text-zinc-300">1</span>
                            <div>
                                <p class="font-semibold text-slate-800 dark:text-zinc-200">Standard 7-Day Loan Window</p>
                                <p class="text-slate-500 dark:text-zinc-400 mt-0.5">Borrowed books must be returned within 7 calendar days of issuance.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <span class="h-5 w-5 shrink-0 rounded-full bg-slate-100 dark:bg-zinc-800 flex items-center justify-center font-bold text-[10px] text-slate-600 dark:text-zinc-300">2</span>
                            <div>
                                <p class="font-semibold text-slate-800 dark:text-zinc-200">Overdue Fines: $10.00 / Day</p>
                                <p class="text-slate-500 dark:text-zinc-400 mt-0.5">Penalties accrue automatically each day past the due date until the item is returned.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5">
                            <span class="h-5 w-5 shrink-0 rounded-full bg-slate-100 dark:bg-zinc-800 flex items-center justify-center font-bold text-[10px] text-slate-600 dark:text-zinc-300">3</span>
                            <div>
                                <p class="font-semibold text-slate-800 dark:text-zinc-200">Borrowing Suspension</p>
                                <p class="text-slate-500 dark:text-zinc-400 mt-0.5">Members with unreturned overdue books cannot borrow additional titles.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 dark:border-zinc-800 text-center">
                    <a href="/borrowings" class="inline-flex items-center justify-center w-full px-4 py-2.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-950/60 rounded-xl transition-colors">
                        Go to Circulation Desk &rarr;
                    </a>
                </div>
            </div>

        </div>

    </div>

</x-layouts::app>

