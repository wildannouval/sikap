<?php
use App\Models\Seminar;
use function Livewire\Volt\{state, computed};

// Properti BARU untuk menampung data seminar yang akan ditampilkan di modal
state(['seminarToView' => null]);

$seminars = computed(function () {
    return Seminar::with(['kerjaPraktek.mahasiswa', 'ruangan'])
        ->where('status_seminar', 'Dijadwalkan')
        ->get()
        ->map(function (Seminar $seminar) {
            return [
                'id' => $seminar->id, // <-- Penting: tambahkan ID event
                'title' => $seminar->kerjaPraktek->mahasiswa->nama_mahasiswa,
                'start' => $seminar->tanggal_seminar . 'T' . $seminar->jam_mulai,
                'end' => $seminar->tanggal_seminar . 'T' . $seminar->jam_selesai,
                'extendedProps' => [
                    'judul_kp' => $seminar->judul_kp_final,
                    'ruangan' => $seminar->ruangan->nama_ruangan,
                ]
            ];
        });
});

// Fungsi BARU yang akan dipanggil dari kalender
$showDetail = function ($seminarId) {
    $this->seminarToView = Seminar::with(['kerjaPraktek.mahasiswa', 'ruangan'])->find($seminarId);
    Flux::modal('detail-seminar-modal')->show();
};
?>

<div>
    <flux:heading size="xl" level="1">Kalender Seminar</flux:heading>
    <flux:subheading size="lg" class="mb-6">Jadwal seminar Kerja Praktik yang telah ditetapkan. Klik pada jadwal untuk melihat detail.</flux:subheading>
    <flux:separator variant="subtle"/>

    <div
        class="mt-8 bg-white dark:bg-neutral-900 p-4 rounded-xl border dark:border-neutral-800"
        wire:ignore
        x-data="{
        events: {{ Js::from($this->seminars) }},
        init() {
            const calendar = new Calendar(this.$el, {
                plugins: [ dayGridPlugin ],
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek'
                },
                events: this.events,
                eventClick: (info) => {
                    @this.call('showDetail', info.event.id);
                },
                eventContent: function(info) {
                    let title = document.createElement('div');
                    title.classList.add('font-bold', 'truncate');
                    title.innerHTML = info.event.title;

                    let description = document.createElement('div');
                    description.classList.add('text-xs', 'truncate');
                    description.innerHTML = info.event.extendedProps.judul_kp;

                    let room = document.createElement('div');
                    room.classList.add('text-xs', 'italic', 'text-zinc-500');
                    room.innerHTML = info.event.extendedProps.ruangan;

                    return { domNodes: [title, description, room] };
                }
            });
            calendar.render();
        }
    }"
    >
    </div>

    {{-- Modal BARU untuk menampilkan detail seminar --}}
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

{{-- HAPUS @push('scripts') DARI SINI --}}
