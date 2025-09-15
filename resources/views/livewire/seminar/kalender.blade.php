<?php

use App\Models\Seminar;
use Flux\Flux;
use function Livewire\Volt\{state, computed};

state(['seminarToView' => null]);

$seminars = computed(function () {
    return Seminar::with(['kerjaPraktek.mahasiswa', 'ruangan'])
        ->where('status_seminar', 'Dijadwalkan')
        ->get()
        ->map(function (Seminar $seminar) {
            return [
                'id' => $seminar->id,
                'title' => $seminar->kerjaPraktek->mahasiswa->nama_mahasiswa,
                'start' => $seminar->tanggal_seminar . 'T' . $seminar->jam_mulai,
                'end' => $seminar->tanggal_seminar . 'T' . $seminar->jam_selesai,
                'extendedProps' => [
                    'judul_kp' => $seminar->judul_kp_final,
                    'ruangan' => $seminar->ruangan->nama_ruangan,
                ],
            ];
        });
});

$showDetail = function ($seminarId) {
    $this->seminarToView = Seminar::with(['kerjaPraktek.mahasiswa', 'ruangan'])->find($seminarId);
    Flux::modal('detail-seminar-modal')->show();
};

?>

<div>
    <flux:heading size="xl" level="1">Kalender Seminar</flux:heading>
    <flux:subheading size="lg" class="mb-6">
        Jadwal seminar Kerja Praktik yang telah ditetapkan. Klik pada jadwal untuk melihat detail.
    </flux:subheading>

    {{-- Kartu Informasi --}}
    <flux:card>
        <h3 class="text-lg font-semibold mb-4">Informasi Kalender</h3>
        <div class="space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
            <p>Halaman ini menampilkan semua jadwal seminar Kerja Praktik yang telah final dan dikonfirmasi oleh mahasiswa.</p>
            <ol class="list-decimal list-inside space-y-2 pl-1">
                <li>Gunakan tombol navigasi (panah, today) untuk berpindah antar bulan.</li>
                <li>Ubah tampilan antara Bulan (Month), Minggu (Week), atau Daftar (List) di pojok kanan atas kalender.</li>
                <li>Klik pada salah satu jadwal seminar untuk melihat detail lengkapnya, termasuk judul KP, mahasiswa, dan ruangan.</li>
            </ol>
        </div>
    </flux:card>

    {{-- CSS --}}
    <style>
        .fc { background-color: white; }
        .dark .fc { background-color: #0f172a; }

        :root {
            --fc-border-color: #e2e8f0;
            --fc-day-today-bg-color: #f1f5f9;
            --fc-button-bg-color: #1e293b;
            --fc-button-border-color: #334155;
            --fc-button-hover-bg-color: #334155;
            --fc-button-active-bg-color: #475569;
            --fc-button-text-color: #f8fafc;
        }

        .dark {
            --fc-border-color: #334155;
            --fc-day-today-bg-color: #1e293b;
            --fc-button-bg-color: #334155;
            --fc-button-border-color: #475569;
            --fc-button-hover-bg-color: #475569;
            --fc-button-active-bg-color: #64748b;
            --fc-button-text-color: #f8fafc;
        }

        .fc-toolbar-title { font-size: 1.25rem !important; }

        .fc-event {
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
            background-color: rgb(59 130 246 / 0.1) !important;
            border: 1px solid rgb(59 130 246 / 0.2) !important;
        }

        .fc-event .fc-event-title, .fc-event-time {
            color: #1e40af;
            font-weight: 600;
        }

        .dark .fc-event {
            background-color: rgb(59 130 246 / 0.2) !important;
            border: 1px solid rgb(59 130 246 / 0.4) !important;
        }

        .dark .fc-event .fc-event-title, .dark .fc-event-time {
            color: #93c5fd;
        }

        .dark .fc-col-header-cell-cushion {
            color: #334155;
        }
    </style>

    {{-- Kalender --}}
    <div
        class="mt-8 text-sm"
        wire:ignore
        x-data="{
    events: {{ Js::from($this->seminars) }},
    init() {
        const calendar = new Calendar(this.$el, {
            plugins: [ dayGridPlugin ],
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek'
            },
            events: this.events,
            eventClick: (info) => {
                @this.call('showDetail', info.event.id);
            }
        });
        calendar.render();
    }
}"

    >
    </div>

    {{-- Modal --}}
    <flux:modal name="detail-seminar-modal" class="md:w-[32rem]">
        @if ($seminarToView)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Detail Jadwal Seminar</flux:heading>
                </div>
                <div class="space-y-4 rounded-lg border bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Mahasiswa</span>
                        <span class="col-span-2 font-semibold">{{ $seminarToView->kerjaPraktek->mahasiswa->nama_mahasiswa }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Judul KP</span>
                        <span class="col-span-2">{{ $seminarToView->judul_kp_final }}</span>
                    </div>
                    <hr class="dark:border-neutral-700">
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Tanggal</span>
                        <span class="col-span-2">{{ \Carbon\Carbon::parse($seminarToView->tanggal_seminar)->translatedFormat('l, d F Y') }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Waktu</span>
                        <span class="col-span-2">{{ \Carbon\Carbon::parse($seminarToView->jam_mulai)->format('H:i') }} - {{ \Carbon\Carbon::parse($seminarToView->jam_selesai)->format('H:i') }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-sm">
                        <span class="text-neutral-500">Ruangan</span>
                        <span class="col-span-2">{{ $seminarToView->ruangan->nama_ruangan }}</span>
                    </div>
                </div>
                <div class="flex justify-end">
                    <flux:modal.close><flux:button variant="ghost">Tutup</flux:button></flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
