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
    <flux:subheading size="lg" class="mb-6">Jadwal seminar Kerja Praktik yang telah ditetapkan. Klik pada jadwal untuk melihat detail.</flux:subheading>
    <flux:separator variant="subtle"/>

    {{-- Kustomisasi CSS yang lebih stabil dan kontras --}}
    <style>
        .fc {
            background-color: white;
        }
        .dark .fc {
            background-color: #0f172a; /* Slate-900 */
        }

        :root {
            --fc-border-color: #e2e8f0; /* Slate-200 */
            --fc-daygrid-day-bg-color: transparent;
            --fc-day-today-bg-color: #f1f5f9; /* Slate-100 */
            --fc-button-bg-color: #1e293b; /* Slate-800 */
            --fc-button-border-color: #334155; /* Slate-700 */
            --fc-button-hover-bg-color: #334155; /* Slate-700 */
            --fc-button-active-bg-color: #475569; /* Slate-600 */
            --fc-button-text-color: #f8fafc; /* Slate-50 */
        }
        .dark {
            --fc-border-color: #334155; /* Slate-700 */
            --fc-day-today-bg-color: #1e293b; /* Slate-800 */
            --fc-button-bg-color: #334155; /* Slate-700 */
            --fc-button-border-color: #475569; /* Slate-600 */
            --fc-button-hover-bg-color: #475569; /* Slate-600 */
            --fc-button-active-bg-color: #64748b; /* Slate-500 */
            --fc-button-text-color: #f8fafc; /* Slate-50 */
        }
        .fc-toolbar-title { font-size: 1.25rem !important; }

        /* Styling Event agar sesuai tema Blue */
        .fc-event {
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
            background-color: rgb(59 130 246 / 0.1) !important; /* blue-500/10 */
            border: 1px solid rgb(59 130 246 / 0.2) !important; /* blue-500/20 */
        }
        .fc-event .fc-event-title, .fc-event-time {
            color: #1e40af; /* blue-800 */
            font-weight: 600;
        }
        .dark .fc-event {
            background-color: rgb(59 130 246 / 0.2) !important; /* blue-500/20 */
            border: 1px solid rgb(59 130 246 / 0.4) !important; /* blue-500/40 */
        }
        .dark .fc-event .fc-event-title, .dark .fc-event-time {
            color: #93c5fd; /* blue-300 */
        }

        /* Tambahkan aturan ini di dalam <style> Anda */
        .dark .fc-col-header-cell-cushion {
            color: #334155; /* Slate-700 */
        }
    </style>

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
                    // Kita tidak lagi memerlukan eventContent, biarkan CSS yang bekerja
                });
                calendar.render();
            }
        }"
    >
        {{-- Kalender akan dirender di sini --}}
    </div>

    {{-- Modal untuk menampilkan detail seminar --}}
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
