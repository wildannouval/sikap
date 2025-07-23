<?php
use function Livewire\Volt\{computed};
use Illuminate\Support\Facades\Auth;

$unreadCount = computed(fn() => Auth::user()->unreadNotifications()->count());
?>

{{-- Komponen ini hanya akan merender angka jika ada notifikasi --}}
@if($this->unreadCount > 0)
    <span>{{ $this->unreadCount }}</span>
@endif
