<?php

use App\Models\Member;
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
    public ?int $editingMemberId = null;

    #[Locked]
    public ?int $deletingMemberId = null;

    public bool $showModal = false;
    public bool $showDeleteModal = false;

    // Filter properties
    public string $search = '';
    public string $statusFilter = 'all'; // 'all', 'active', 'inactive'

    // Form fields
    public string $member_code = '';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $status = 'active';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        $codeRule = Rule::unique('members', 'member_code');
        $emailRule = Rule::unique('members', 'email');

        if ($this->editingMemberId !== null) {
            $codeRule->ignore($this->editingMemberId);
            $emailRule->ignore($this->editingMemberId);
        }

        return [
            'member_code' => ['required', 'string', 'max:50', $codeRule],
            'name'        => 'required|string|max:150',
            'email'       => ['nullable', 'email', 'max:191', $emailRule],
            'phone'       => 'nullable|string|max:30',
            'address'     => 'nullable|string|max:1000',
            'status'      => 'required|in:active,inactive',
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editingMemberId = null;

        // Auto-generate next suggested code: MEM-YYYY-RAND
        $this->member_code = 'MEM-' . date('Y') . '-' . str_pad((string) rand(100, 999), 4, '0', STR_PAD_LEFT);
        $this->status = 'active';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $member = Member::query()->findOrFail($id);

        $this->editingMemberId = $member->id;
        $this->member_code = $member->member_code;
        $this->name = $member->name;
        $this->email = $member->email ?? '';
        $this->phone = $member->phone ?? '';
        $this->address = $member->address ?? '';
        $this->status = $member->status;

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
        $this->editingMemberId = null;
        $this->member_code = '';
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->address = '';
        $this->status = 'active';
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate();
        $isEditing = $this->editingMemberId !== null;

        $member = $isEditing
            ? Member::query()->findOrFail($this->editingMemberId)
            : new Member();

        $member->member_code = trim($validated['member_code']);
        $member->name = trim($validated['name']);
        $member->email = filled($validated['email'] ?? null) ? trim($validated['email']) : null;
        $member->phone = filled($validated['phone'] ?? null) ? trim($validated['phone']) : null;
        $member->address = filled($validated['address'] ?? null) ? trim($validated['address']) : null;
        $member->status = $validated['status'];

        $member->save();

        $this->showModal = false;
        $this->resetForm();

        session()->flash(
            'status',
            $isEditing ? 'Member profile updated successfully.' : 'New library member registered.'
        );
    }

    public function toggleStatus(int $id): void
    {
        $member = Member::query()->findOrFail($id);
        $member->status = $member->status === 'active' ? 'inactive' : 'active';
        $member->save();

        session()->flash('status', "Member account status changed to {$member->status}.");
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingMemberId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteMember(): void
    {
        if (!$this->deletingMemberId) {
            return;
        }

        $member = Member::query()->findOrFail($this->deletingMemberId);

        if ($member->borrowings()->exists()) {
            session()->flash('error', 'Cannot delete member because this account has past or active circulation history.');
            $this->showDeleteModal = false;
            $this->deletingMemberId = null;
            return;
        }

        $member->delete();

        session()->flash('status', 'Member deleted successfully.');
        $this->showDeleteModal = false;
        $this->deletingMemberId = null;
    }

    #[Computed]
    public function members()
    {
        $query = Member::query()
            ->withCount(['borrowings as active_loans_count' => function ($q) {
                $q->whereNull('returned_at');
            }])
            ->search($this->search);

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('name')->paginate(10);
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
                    Members Directory &bull; {{ $this->members->total() }} Total
                </span>
            </div>
            <h1 class="mt-1 text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Member Management
            </h1>
            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                Register student & faculty patron accounts, monitor loan eligibility, and update contact profiles.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 rounded-xl shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-indigo-500/20 cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                Register Member
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
    <!-- 2. FILTER & SEARCH TOOLBAR                                          -->
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
                   placeholder="Search member name, code, email, phone..."
                   class="w-full pl-10 pr-9 py-2 text-sm bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
            @if ($search)
                <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Status Filter Select -->
        <div class="flex items-center gap-3">
            <select wire:model.live="statusFilter"
                    class="py-2 px-3.5 text-xs font-medium bg-slate-50 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl text-slate-700 dark:text-zinc-200 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                <option value="all">All Account Statuses</option>
                <option value="active">Active Members Only</option>
                <option value="inactive">Inactive / Suspended</option>
            </select>
        </div>

    </div>

    <!-- =================================================================== -->
    <!-- 3. MEMBERS TABLE                                                    -->
    <!-- =================================================================== -->
    <div class="rounded-2xl border border-slate-200/90 bg-white shadow-sm overflow-hidden dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/70 dark:bg-zinc-800/60 border-b border-slate-100 dark:border-zinc-800 text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-semibold text-[11px]">
                        <th class="py-3.5 px-5">Member Details</th>
                        <th class="py-3.5 px-4">Member ID Code</th>
                        <th class="py-3.5 px-4">Contact Info</th>
                        <th class="py-3.5 px-4">Active Loans</th>
                        <th class="py-3.5 px-4">Account Status</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/80">
                    @forelse ($this->members as $member)
                        <tr wire:key="member-row-{{ $member->id }}" class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                            
                            <!-- Name & Avatar -->
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-xl bg-gradient-to-tr from-indigo-500 to-sky-500 text-white flex items-center justify-center font-bold text-xs shadow-xs">
                                        {{ strtoupper(substr($member->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm text-slate-900 dark:text-white">
                                            {{ $member->name }}
                                        </div>
                                        @if ($member->address)
                                            <div class="text-[11px] text-slate-400 dark:text-zinc-500 truncate max-w-xs">
                                                {{ $member->address }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Code -->
                            <td class="py-4 px-4">
                                <span class="font-mono text-xs px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold border border-slate-200/80 dark:border-zinc-700">
                                    {{ $member->member_code }}
                                </span>
                            </td>

                            <!-- Contact -->
                            <td class="py-4 px-4 text-slate-600 dark:text-zinc-300">
                                <div class="font-medium text-slate-800 dark:text-zinc-200">
                                    {{ $member->email ?: 'No email' }}
                                </div>
                                <div class="text-[11px] text-slate-400 dark:text-zinc-500 font-mono">
                                    {{ $member->phone ?: 'No phone' }}
                                </div>
                            </td>

                            <!-- Active Loans -->
                            <td class="py-4 px-4">
                                @if ($member->active_loans_count > 0)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-sky-50 text-sky-700 dark:bg-sky-950/60 dark:text-sky-400 ring-1 ring-sky-600/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                        {{ $member->active_loans_count }} Active {{ Str::plural('Loan', $member->active_loans_count) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] text-slate-400 dark:text-zinc-500">
                                        0 Active
                                    </span>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="py-4 px-4">
                                @if ($member->status === 'active')
                                    <button type="button" 
                                            wire:click="toggleStatus({{ $member->id }})"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 ring-1 ring-emerald-600/20 hover:opacity-80 transition-opacity"
                                            title="Click to toggle status">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </button>
                                @else
                                    <button type="button" 
                                            wire:click="toggleStatus({{ $member->id }})"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-400 ring-1 ring-slate-300 dark:ring-zinc-700 hover:opacity-80 transition-opacity"
                                            title="Click to toggle status">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        Inactive
                                    </button>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-5 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button"
                                            wire:click="edit({{ $member->id }})"
                                            class="p-1.5 rounded-lg text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors"
                                            title="Edit member profile">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>

                                    <button type="button"
                                            wire:click="confirmDelete({{ $member->id }})"
                                            class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                            title="Delete member">
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="font-bold text-slate-700 dark:text-zinc-300">No members found</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Try adjusting your search criteria or register a new member.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->members->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-850">
                {{ $this->members->links() }}
            </div>
        @endif
    </div>

    <!-- =================================================================== -->
    <!-- 4. ADD / EDIT MEMBER MODAL                                          -->
    <!-- =================================================================== -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="relative w-full max-w-lg bg-white dark:bg-zinc-900 rounded-3xl border border-slate-200 dark:border-zinc-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                            {{ $editingMemberId ? 'Edit Member Profile' : 'Register New Member' }}
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                            Enter the member's identification details and borrowing status.
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
                    
                    <div class="space-y-4">
                        
                        <!-- Member Code -->
                        <div>
                            <label for="mem_code" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Member Code <span class="text-rose-500">*</span>
                            </label>
                            <input id="mem_code" type="text" wire:model="member_code"
                                   placeholder="e.g. MEM-2026-0001"
                                   class="w-full px-3.5 py-2.5 text-sm font-mono bg-slate-50 dark:bg-zinc-800 border @error('member_code') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('member_code')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Full Name -->
                        <div>
                            <label for="mem_name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Full Name <span class="text-rose-500">*</span>
                            </label>
                            <input id="mem_name" type="text" wire:model="name"
                                   placeholder="e.g. Jane Austen"
                                   class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('name') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            @error('name')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email & Phone Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="mem_email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                    Email Address
                                </label>
                                <input id="mem_email" type="email" wire:model="email"
                                       placeholder="jane@university.edu"
                                       class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('email') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                @error('email')
                                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="mem_phone" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                    Phone Number
                                </label>
                                <input id="mem_phone" type="text" wire:model="phone"
                                       placeholder="+1 (555) 019-2834"
                                       class="w-full px-3.5 py-2.5 text-sm font-mono bg-slate-50 dark:bg-zinc-800 border @error('phone') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                @error('phone')
                                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Address -->
                        <div>
                            <label for="mem_address" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Physical Address / Department
                            </label>
                            <textarea id="mem_address" wire:model="address" rows="2"
                                      placeholder="Faculty of Science, Building B, Room 402"
                                      class="w-full px-3.5 py-2.5 text-sm bg-slate-50 dark:bg-zinc-800 border @error('address') border-rose-500 ring-2 ring-rose-500/20 @else border-slate-200 dark:border-zinc-700 @enderror rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                            @error('address')
                                <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Status Selection -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-1.5">
                                Account Status <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center gap-2 p-3 rounded-xl border @if($status === 'active') border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 @else border-slate-200 dark:border-zinc-700 @endif cursor-pointer transition-all">
                                    <input type="radio" wire:model="status" value="active" class="text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <span class="block text-xs font-bold text-slate-900 dark:text-white">Active</span>
                                        <span class="text-[10px] text-slate-500">Eligible to borrow</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-2 p-3 rounded-xl border @if($status === 'inactive') border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/30 @else border-slate-200 dark:border-zinc-700 @endif cursor-pointer transition-all">
                                    <input type="radio" wire:model="status" value="inactive" class="text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <span class="block text-xs font-bold text-slate-900 dark:text-white">Inactive</span>
                                        <span class="text-[10px] text-slate-500">Suspended account</span>
                                    </div>
                                </label>
                            </div>
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
                            {{ $editingMemberId ? 'Update Member' : 'Register Member' }}
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
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Delete Member Account?</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">This action cannot be undone.</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 dark:text-zinc-300">
                    Are you sure you want to permanently delete this member account? This is only permitted if the member has zero past or active circulation history.
                </p>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button"
                            wire:click="$set('showDeleteModal', false)"
                            class="px-4 py-2 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-xl transition-colors">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="deleteMember"
                            class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs transition-colors">
                        Yes, Delete Member
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
