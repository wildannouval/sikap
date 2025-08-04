<?php

use function Livewire\Volt\{state, computed};
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use Flux\Flux;

state(['filter' => 'all']);

$notifications = computed(function () {
    if ($this->filter === 'unread') {
        return Auth::user()->unreadNotifications()->paginate(10);
    }
    return Auth::user()->notifications()->paginate(10);
});

$markAsRead = function ($id) {
    Auth::user()->notifications()->find($id)?->markAsRead();
};

$deleteNotification = function ($id) {
    Auth::user()->notifications()->find($id)?->delete();
};

$clearAll = function () {
    Auth::user()->notifications()->delete();

    // PERBAIKAN: Perintahkan modal untuk menutup
    Flux::modal('clear-all-modal')->close();

    // Tambahan: Beri notifikasi toast bahwa aksi berhasil
    Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Semua notifikasi telah dihapus.');
};

?>

<div>
    {{-- Header Halaman --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">Notifikasi</flux:heading>
            <flux:subheading size="lg">Semua pemberitahuan terkait aktivitas Anda di SIKAP.</flux:subheading>
        </div>
        @if(count($this->notifications) > 0)
            {{-- PERBAIKAN: Tombol sekarang menjadi pemicu modal --}}
            <flux:modal.trigger name="clear-all-modal">
                <flux:button variant="danger" size="sm">
                    Hapus Semua
                </flux:button>
            </flux:modal.trigger>
        @endif
    </div>
    <flux:separator variant="subtle"/>

    {{-- Konten Utama --}}
    <flux:card class="mt-6">
        {{-- Header Card dengan Filter --}}
        <div class="p-4 border-b dark:border-neutral-700">
            <flux:tabs wire:model.live="filter" variant="pills" size="sm">
                <flux:tab name="all">Semua</flux:tab>
                <flux:tab name="unread">Belum Dibaca</flux:tab>
            </flux:tabs>
        </div>

        {{-- Daftar Notifikasi --}}
        <div class="divide-y dark:divide-neutral-700">
            @forelse($this->notifications as $notification)
                <div :key="$notification->id" class="p-4 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                    {{-- Indikator Status --}}
                    <div class="mt-1">
                        @if(!$notification->read_at)
                            <div class="w-2.5 h-2.5 rounded-full bg-blue-500" title="Belum dibaca"></div>
                        @else
                            <flux:icon name="check-circle" class="size-5 text-zinc-400" title="Sudah dibaca" />
                        @endif
                    </div>
                    {{-- Konten Notifikasi --}}
                    <div class="flex-1">
                        <a href="{{ $notification->data['url'] ?? '#' }}" class="text-sm hover:underline">{{ $notification->data['message'] }}</a>
                        <p class="text-xs text-zinc-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    {{-- Tombol Aksi --}}
                    <div class="flex items-center gap-2">
                        @if(!$notification->read_at)
                            <flux:button size="xs" wire:click="markAsRead('{{ $notification->id }}')">Tandai Dibaca</flux:button>
                        @endif
                        <flux:button size="xs" variant="danger" wire:click="deleteNotification('{{ $notification->id }}')">Hapus</flux:button>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-zinc-500">
                    <flux:icon name="bell-slash" class="size-10 mx-auto" />
                    <p class="mt-2">Tidak ada notifikasi.</p>
                </div>
            @endforelse
        </div>

        {{-- Paginasi --}}
        @if($this->notifications->hasPages())
            <div class="border-t p-4 dark:border-neutral-700">
                {{ $this->notifications->links() }}
            </div>
        @endif
    </flux:card>

    {{-- MODAL BARU untuk Konfirmasi Hapus Semua --}}
    <flux:modal name="clear-all-modal" class="md:w-96">
        <div class="space-y-6 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100">
                <flux:icon name="trash" class="size-6 text-red-600"/>
            </div>
            <div>
                <flux:heading size="lg">Hapus Semua Notifikasi?</flux:heading>
                <flux:text class="mt-2">
                    Anda yakin ingin menghapus semua notifikasi Anda? Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
            <div class="flex justify-center gap-3">
                <flux:modal.close><flux:button variant="ghost">Batal</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="clearAll">Ya, Hapus Semua</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
