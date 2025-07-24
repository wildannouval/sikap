{{-- File: resources/views/layouts/partials/_menu_lainnya.blade.php --}}
<flux:navlist.group :heading="__('Menu Lainnya')">
    <flux:navlist.item
        href="{{ route('notifications.index') }}"
        icon="bell"
        :badge="auth()->user()->unreadNotifications()->count() > 0 ? auth()->user()->unreadNotifications()->count() : ''">
        Notifikasi
    </flux:navlist.item>
    <flux:navlist.item icon="calendar" :href="route('seminar.kalender')" :current="request()->routeIs('seminar.kalender')" wire:navigate>
        {{ __('Kalender Seminar') }}
    </flux:navlist.item>
</flux:navlist.group>
