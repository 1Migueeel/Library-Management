
<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome', [
    'books' => \App\Models\Book::query()
        ->orderBy('title')
        ->get(),
])->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');
});


Route::livewire('/books', 'pages::books.index');
Route::livewire('/members', 'pages::members.index');

// Protected: only authenticated staff may access the Borrowings circulation desk
Route::middleware(['auth'])->group(function () {
    Route::livewire('/borrowings', 'pages::borrowings.index');
});

// Guest borrow redirect: flash a friendly message then send to login
Route::get('/borrow/guest-redirect', function (\Illuminate\Http\Request $request) {
    // Store the book_id so we can redirect back after login
    $bookId = $request->query('book_id');
    if ($bookId) {
        session()->put('url.intended', url('/borrowings?book_id=' . $bookId));
    }
    return redirect()->route('login')->with(
        'status',
        '🔒 Please log in or create an account to borrow a book.'
    );
})->name('borrow.guest-redirect');


require __DIR__.'/settings.php';
