{{-- File: resources/views/layouts/partials/_dosen_pembimbing_menu.blade.php --}}
<flux:navlist.group :heading="__('Menu Dosen Pembimbing')">
    <flux:navlist.item
        icon="user-group"
        :href="route('dospem.mahasiswa')"
        {{-- PERBAIKAN: Gunakan wildcard (*) agar halaman detail juga terdeteksi --}}
        :current="request()->routeIs('dospem.mahasiswa*')"
        wire:navigate>
        {{ __('Mahasiswa Bimbingan') }}
    </flux:navlist.item>
    <flux:navlist.item
        icon="clipboard-document-check"
        :href="route('dospem.penilaian')"
        :current="request()->routeIs('dospem.penilaian*')"
        wire:navigate>
        {{ __('Penilaian KP') }}
    </flux:navlist.item>
    <flux:navlist.item
        icon="archive-box"
        :href="route('bapendik.laporan')"
        :current="request()->routeIs('bapendik.laporan*')"
        wire:navigate>
        {{ __('Laporan & Arsip') }}
    </flux:navlist.item>
</flux:navlist.group>
