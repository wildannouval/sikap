<?php

use function Livewire\Volt\{state, on, computed};
use Illuminate\Support\Facades\Auth;

$unreadCount = computed(fn() => Auth::user()->unreadNotifications()->count());

// Ambil 5 notifikasi terbaru untuk ditampilkan di popover
$recentNotifications = computed(fn() => Auth::user()->notifications()->latest()->take(5)->get());

$markAsRead = function ($notificationId) {
    $notification = Auth::user()->notifications()->find($notificationId);
    if ($notification) {
        $notification->markAsRead();
    }
    // Redirect tidak diperlukan agar popover tetap terbuka
};

?>

<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" class="relative">
        <flux:icon name="bell" />
        @if($this->unreadCount > 0)
            <div class="absolute top-0 right-0 -mt-1 -mr-1 px-1.5 py-0.5 bg-red-500 text-white text-xs rounded-full">
                {{ $this->unreadCount }}
            </div>
        @endif
    </flux:button>

    <flux:popover class="w-80 flex flex-col">
        <div class="p-4 border-b dark:border-neutral-700">
            <flux:heading size="sm">Notifikasi Terbaru</flux:heading>
        </div>
        <div class="flex-1 max-h-96 overflow-y-auto">
            @forelse($this->recentNotifications as $notification)
                <a href="{{ $notification->data['url'] ?? '#' }}"
                   class="block px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800"
                   wire:click.prevent="markAsRead('{{ $notification->id }}')">
                    <div class="flex items-start gap-3">
                        @if(!$notification->read_at)
                            <div class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 flex-shrink-0"></div>
                        @else
                            <div class="w-2 h-2 rounded-full bg-transparent mt-1.5 flex-shrink-0"></div>
                        @endif
                        <div class="flex-1">
                            <p class="text-sm">{{ $notification->data['message'] }}</p>
                            <p class="text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-4 text-center text-sm text-zinc-500">
                    Tidak ada notifikasi.
                </div>
            @endforelse
        </div>
        <div class="p-2 border-t dark:border-neutral-700">
            <flux:button as="a" href="{{ route('notifications.index') }}" variant="ghost" class="w-full justify-center !text-indigo-600 hover:underline">
                Lihat Semua Notifikasi
            </flux:button>
        </div>
    </flux:popover>
</flux:dropdown>
