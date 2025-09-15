<?php

use function Livewire\Volt\{state, on, computed};
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\DatabaseNotification;

state([
    'unreadCount' => fn() => Auth::user()->unreadNotifications()->count(),
    'recentNotifications' => fn() => Auth::user()->notifications()->latest()->take(5)->get(),
]);

on(['echo:users.'.Auth::id().',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => function () {
    $this->unreadCount = Auth::user()->unreadNotifications()->count();
    $this->recentNotifications = Auth::user()->notifications()->latest()->take(5)->get();
}]);

$markAsRead = function ($notificationId) {
    $notification = Auth::user()->notifications()->find($notificationId);
    if ($notification) {
        $notification->markAsRead();
        $this->unreadCount = Auth::user()->unreadNotifications()->count();
        $this->recentNotifications = Auth::user()->notifications()->latest()->take(5)->get();
        
        // Kirim event untuk memberitahu komponen lain (seperti badge di sidebar)
        $this->dispatch('notification-read');

        // Redirect ke URL notifikasi
        if (isset($notification->data['url'])) {
            return $this->redirect($notification->data['url'], navigate: true);
        }
    }
};

?>

<flux:dropdown position="bottom" align="end">
    <flux:button variant="ghost" class="relative">
        <flux:icon name="bell" />
        {{-- Menggunakan komponen badge yang sudah di-refactor --}}
        <livewire:layout.notification-badge />
    </flux:button>

    <flux:popover class="w-80 flex flex-col">
        <div class="p-4 border-b dark:border-neutral-700">
            <flux:heading size="sm">Notifikasi Terbaru</flux:heading>
        </div>
        <div class="flex-1 max-h-96 overflow-y-auto">
            @forelse($this->recentNotifications as $notification)
                <div class="block px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ !$notification->read_at ? 'bg-blue-50 dark:bg-blue-900/10' : '' }}">
                    <div class="flex items-start gap-3">
                        {{-- Indikator Titik Biru --}}
                        @if(!$notification->read_at)
                            <div class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 flex-shrink-0" title="Belum dibaca"></div>
                        @else
                            {{-- Beri ruang kosong agar sejajar --}}
                            <div class="w-2 h-2 mt-1.5 flex-shrink-0"></div>
                        @endif
                        
                        {{-- Konten Notifikasi yang Diperbarui --}}
                        <div class="flex-1 cursor-pointer" wire:click="markAsRead('{{ $notification->id }}')">
                            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $notification->data['title'] ?? 'Notifikasi Baru' }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $notification->data['message'] }}</p>
                            <p class="text-xs text-zinc-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-sm text-zinc-500">
                    <flux:icon name="bell-slash" class="size-8 mx-auto mb-2" />
                    <p>Tidak ada notifikasi.</p>
                </div>
            @endforelse
        </div>
        <div class="p-2 border-t dark:border-neutral-700">
            <flux:button as="a" href="{{ route('notifications.index') }}" wire:navigate variant="ghost" class="w-full justify-center !text-indigo-600 hover:underline">
                Lihat Semua Notifikasi
            </flux:button>
        </div>
    </flux:popover>
</flux:dropdown>