{{-- File: resources/views/layouts/partials/_dosen_pembimbing_menu.blade.php --}}
<flux:navlist.group :heading="__('Menu Dosen Pembimbing')">
    <flux:navlist.item icon="user-group" :href="route('dospem.mahasiswa')" :current="request()->routeIs('dospem.mahasiswa.*')" wire:navigate>{{ __('Mahasiswa Bimbingan') }}</flux:navlist.item>
    <flux:navlist.item icon="clipboard-document-check" :href="route('dospem.penilaian')" :current="request()->routeIs('dospem.penilaian.*')" wire:navigate>{{ __('Penilaian KP') }}</flux:navlist.item>
</flux:navlist.group>
