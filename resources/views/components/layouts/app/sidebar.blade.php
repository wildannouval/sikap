<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline" class="mt-4">
                <flux:navlist.item
                    href="{{ route('notifications.index') }}"
                    icon="bell"
                    :badge="auth()->user()->unreadNotifications()->count() > 0 ? auth()->user()->unreadNotifications()->count() : ''">
                    Notifikasi
                </flux:navlist.item>
            </flux:navlist>

            <flux:navlist variant="outline">
                {{-- Tampilkan Menu Sesuai Role --}}
                @if(auth()->user()->role === 'Mahasiswa')

                    {{-- ================= MENU MAHASISWA ================= --}}
                    <flux:navlist.group :heading="__('Menu Mahasiswa')">
                        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                        <flux:navlist.item icon="envelope" :href="route('surat-pengantar.index')" :current="request()->routeIs('surat-pengantar.*')" wire:navigate>{{ __('Surat Pengantar') }}</flux:navlist.item>
                        <flux:navlist.item icon="document-plus" :href="route('kp.pengajuan')" :current="request()->routeIs('kp.pengajuan')" wire:navigate>{{ __('Pengajuan KP') }}</flux:navlist.item>
                        <flux:navlist.item icon="chat-bubble-left-right" :href="route('kp.bimbingan')" :current="request()->routeIs('kp.bimbingan')" wire:navigate>{{ __('Bimbingan KP') }}</flux:navlist.item>
                        <flux:navlist.item icon="calendar-days" :href="route('seminar.pendaftaran')" :current="request()->routeIs('seminar.pendaftaran')" wire:navigate>{{ __('Pendaftaran Seminar') }}</flux:navlist.item>
                        <flux:navlist.item icon="academic-cap" :href="route('kp.nilai')" :current="request()->routeIs('kp.nilai')" wire:navigate>{{ __('Lihat Nilai') }}</flux:navlist.item>
                    </flux:navlist.group>

                @elseif(auth()->user()->role === 'Bapendik')

                    {{-- ================= MENU BAPENDIK ================= --}}
                    <flux:navlist.group :heading="__('Menu Bapendik')">
                        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                        <flux:navlist.item icon="envelope-open" :href="route('bapendik.surat-pengantar')" :current="request()->routeIs('bapendik.surat-pengantar.*')" wire:navigate>{{ __('Validasi Surat') }}</flux:navlist.item>
                        <flux:navlist.item icon="document-check" :href="route('bapendik.pengajuan-kp')" :current="request()->routeIs('bapendik.pengajuan-kp.*')" wire:navigate>{{ __('Validasi KP') }}</flux:navlist.item>
                        <flux:navlist.item icon="calendar" :href="route('bapendik.penjadwalan-seminar')" :current="request()->routeIs('bapendik.penjadwalan-seminar.*')" wire:navigate>{{ __('Penjadwalan Seminar') }}</flux:navlist.item>
                        <flux:navlist.item icon="archive-box" :href="route('bapendik.laporan')" :current="request()->routeIs('bapendik.laporan.*')" wire:navigate>{{ __('Laporan & Arsip') }}</flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group :heading="__('Data Master')">
                        <flux:navlist.item icon="users" :href="route('master.pengguna')" :current="request()->routeIs('master.pengguna.*')" wire:navigate>{{ __('Data Pengguna') }}</flux:navlist.item>
                        <flux:navlist.item icon="building-office" :href="route('master.ruangan')" :current="request()->routeIs('master.ruangan.*')" wire:navigate>{{ __('Data Ruangan') }}</flux:navlist.item>
                        <flux:navlist.item icon="book-open" :href="route('master.jurusan')" :current="request()->routeIs('master.jurusan.*')" wire:navigate>{{ __('Data Jurusan') }}</flux:navlist.item>
                    </flux:navlist.group>

                @elseif(auth()->user()->role === 'Dosen Pembimbing')

                    {{-- ================= MENU DOSEN PEMBIMBING ================= --}}
                    <flux:navlist.group :heading="__('Menu Dosen')">
                        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                        <flux:navlist.item icon="user-group" :href="route('dospem.mahasiswa')" :current="request()->routeIs('dospem.mahasiswa.*')" wire:navigate>{{ __('Mahasiswa Bimbingan') }}</flux:navlist.item>
                        <flux:navlist.item icon="calendar" :href="route('dospem.jadwal-seminar')" :current="request()->routeIs('dospem.jadwal-seminar.*')" wire:navigate>{{ __('Jadwal Seminar') }}</flux:navlist.item>
                        <flux:navlist.item icon="clipboard-document-check" :href="route('dospem.penilaian')" :current="request()->routeIs('dospem.penilaian.*')" wire:navigate>{{ __('Penilaian KP') }}</flux:navlist.item>
                    </flux:navlist.group>

                @elseif(auth()->user()->role === 'Dosen Komisi')

                    {{-- ================= MENU DOSEN KOMISI ================= --}}
                    <flux:navlist.group :heading="__('Menu Komisi')">
                        <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                        <flux:navlist.item icon="user-group" :href="route('doskom.mahasiswa')" :current="request()->routeIs('doskom.mahasiswa.*')" wire:navigate>{{ __('Mahasiswa Bimbingan') }}</flux:navlist.item>
                        <flux:navlist.item icon="clipboard-document-check" :href="route('doskom.penilaian')" :current="request()->routeIs('doskom.penilaian.*')" wire:navigate>{{ __('Penilaian KP') }}</flux:navlist.item>
                        <flux:navlist.item icon="document-magnifying-glass" :href="route('doskom.validasi-kp')" :current="request()->routeIs('doskom.validasi-kp.*')" wire:navigate>{{ __('Validasi Pengajuan KP') }}</flux:navlist.item>
                        <flux:navlist.item icon="archive-box" :href="route('doskom.laporan')" :current="request()->routeIs('doskom.laporan.*')" wire:navigate>{{ __('Laporan & Arsip') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endif

            </flux:navlist>

            <flux:spacer />

{{--         Bagian User Menu di Bawah Sidebar--}}
{{--            <flux:navlist variant="outline">--}}
{{--Button notifikasi--}}
{{--                <flux:navlist.item icon="bell-alert" href="#" target="_blank">--}}
{{--                Route ke notifikasi--}}
{{--                </flux:navlist.item>--}}

{{--                <flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">--}}
{{--                {{ __('Repository') }}--}}
{{--                </flux:navlist.item>--}}

{{--                <flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">--}}
{{--                {{ __('Documentation') }}--}}
{{--                </flux:navlist.item>--}}
{{--            </flux:navlist>--}}

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts

        @persist('toast')
        <flux:toast />
        @endpersist
    </body>
</html>
