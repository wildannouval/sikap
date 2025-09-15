<?php
use function Livewire\Volt\{state, computed, on};
use Illuminate\Support\Facades\Auth;

state(['unreadCount' => fn() => Auth::user()->unreadNotifications()->count()]);

on(['echo:users.'.Auth::id().',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => function () {
    $this->unreadCount = Auth::user()->unreadNotifications()->count();
}]);

on(['notification-read' => function () {
    $this->unreadCount = Auth::user()->unreadNotifications()->count();
}]);

?>

{{-- Komponen ini sekarang akan merender angka dengan style yang lebih baik --}}
@if($unreadCount > 0)
    <div class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
        <span>{{ $unreadCount }}</span>
    </div>
@endif