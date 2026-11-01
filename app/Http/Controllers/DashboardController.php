<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Member;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the library circulation dashboard with real-time analytics.
     */
    public function __invoke(): View
    {
        // ── Inventory Stats ──────────────────────────────────────────
        $totalBooksCopies = (int) Book::sum('copies_total');
        $availableCopies  = (int) Book::sum('copies_available');
        $bookTitlesCount  = Book::count();
        $checkedOutCopies = max(0, $totalBooksCopies - $availableCopies);

        // ── Loan Stats ───────────────────────────────────────────────
        $activeLoansCount = Borrowing::active()->count();
        $overdueLoans     = Borrowing::with(['book', 'member'])
            ->overdue()
            ->latest('due_date')
            ->get();
        $overdueCount     = $overdueLoans->count();

        // Calculate accrued overdue fines ($10.00 / day)
        $totalOverdueFines = $overdueLoans->sum(fn ($b) => $b->calculateFine());

        // ── Member Stats ─────────────────────────────────────────────
        $totalMembersCount  = Member::count();
        $activeMembersCount = Member::active()->count();

        // ── Recent Circulation Activity ──────────────────────────────
        $recentCirculation = Borrowing::with(['book', 'member'])
            ->latest('id')
            ->limit(6)
            ->get();

        // ── Render ───────────────────────────────────────────────────
        return view('dashboard', compact(
            'totalBooksCopies',
            'availableCopies',
            'bookTitlesCount',
            'checkedOutCopies',
            'activeLoansCount',
            'overdueLoans',
            'overdueCount',
            'totalOverdueFines',
            'totalMembersCount',
            'activeMembersCount',
            'recentCirculation',
        ));
    }
}
