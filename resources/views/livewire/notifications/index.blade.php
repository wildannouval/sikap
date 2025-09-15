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
    Flux::modal('clear-all-modal')->close();
    Flux::toast(variant: 'success', heading: 'Berhasil', text: 'Semua notifikasi telah dihapus.');
};

?>

<div>
    <div class="mb-6">
        <flux:heading size="xl" level="1">Notifikasi</flux:heading>
        <flux:subheading size="lg">Semua pemberitahuan terkait aktivitas Anda di SIKAP.</flux:subheading>
    </div>

    {{-- [START] PERUBAHAN LAYOUT MENJADI DUA KOLOM --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        {{-- Kolom Kiri (Utama): Daftar Notifikasi --}}
        <div class="lg:col-span-2 space-y-6">
            <flux:card>
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 border-b dark:border-neutral-700">
                    <div class="w-full sm:w-auto">
                        <flux:tabs wire:model.live="filter" variant="pills" size="sm">
                            <flux:tab name="all">Semua</flux:tab>
                            <flux:tab name="unread">Belum Dibaca</flux:tab>
                        </flux:tabs>
                    </div>
                    @if(count($this->notifications) > 0)
                        <div class="w-full sm:w-auto">
                            <flux:modal.trigger name="clear-all-modal">
                                <flux:button variant="danger" size="sm" class="w-full justify-center">
                                    Hapus Semua
                                </flux:button>
                            </flux:modal.trigger>
                        </div>
                    @endif
                </div>

                <div class="divide-y dark:divide-neutral-700">
                    @forelse($this->notifications as $notification)
                        <div :key="$notification->id" class="p-4 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <div class="mt-1 flex-shrink-0">
                                @if(!$notification->read_at)
                                    <div class="w-2.5 h-2.5 rounded-full bg-blue-500" title="Belum dibaca"></div>
                                @else
                                    <flux:icon name="check-circle" class="size-5 text-zinc-400" title="Sudah dibaca" />
                                @endif
                            </div>
                            <div class="flex-1">
                                <a href="{{ $notification->data['url'] ?? '#' }}" class="text-sm font-medium text-zinc-800 dark:text-zinc-200 hover:underline">{{ $notification->data['title'] ?? 'Notifikasi' }}</a>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $notification->data['message'] }}</p>
                                <p class="text-xs text-zinc-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                @if(!$notification->read_at)
                                    <flux:button size="xs" wire:click="markAsRead('{{ $notification->id }}')">Tandai Dibaca</flux:button>
                                @endif
                                <flux:button size="xs" variant="ghost" class="text-red-600" wire:click="deleteNotification('{{ $notification->id }}')">Hapus</flux:button>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center text-zinc-500">
                            <flux:icon name="bell-slash" class="size-10 mx-auto" />
                            <p class="mt-2 font-medium">Tidak ada notifikasi.</p>
                            <p class="text-sm">Saat ada aktivitas baru, Anda akan melihatnya di sini.</p>
                        </div>
                    @endforelse
                </div>

                @if($this->notifications->hasPages())
                    <div class="border-t p-4 dark:border-neutral-700">
                        {{ $this->notifications->links() }}
                    </div>
                @endif
            </flux:card>
        </div>
        
        {{-- Kolom Kanan (Informasi) --}}
        <div class="lg:col-span-1 space-y-8">
            <flux:card>
                <h3 class="text-lg font-semibold mb-4">Tentang Notifikasi</h3>
                <div class="space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
                    <p>Halaman ini menampilkan semua pemberitahuan terkait status pengajuan Anda atau tugas yang perlu Anda tanggapi.</p>
                    
                    <div class="flex items-start gap-3">
                        <div class="w-2.5 h-2.5 mt-1.5 rounded-full bg-blue-500 flex-shrink-0"></div>
                        <p><b>Belum Dibaca:</b> Titik biru menandakan notifikasi baru yang belum Anda lihat.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <flux:icon name="check-circle" class="size-5 text-zinc-400 flex-shrink-0" />
                        <p><b>Sudah Dibaca:</b> Ikon centang menandakan Anda sudah melihat atau berinteraksi dengan notifikasi tersebut.</p>
                    </div>
                    
                    <p class="pt-2 border-t dark:border-zinc-700"><b>Tips:</b> Klik pada judul atau isi notifikasi untuk langsung menuju ke halaman terkait.</p>
                </div>
            </flux:card>
        </div>
    </div>
    {{-- [END] PERUBAHAN LAYOUT --}}

    {{-- Modal untuk Konfirmasi Hapus Semua --}}
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