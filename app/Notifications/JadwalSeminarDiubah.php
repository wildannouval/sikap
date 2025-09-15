<?php

namespace App\Notifications;

use App\Models\Seminar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class JadwalSeminarDiubah extends Notification
{
    use Queueable;

    protected $seminar;

    /**
     * Create a new notification instance.
     */
    public function __construct(Seminar $seminar)
    {
        $this->seminar = $seminar;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $tanggal = Carbon::parse($this->seminar->tanggal_seminar)->isoFormat('dddd, D MMMM Y');
        $jam = Carbon::parse($this->seminar->jam_mulai)->format('H:i');

        return [
            'title' => 'Perubahan Jadwal Seminar KP',
            'message' => 'Bapendik menyarankan jadwal seminar baru pada ' . $tanggal . ' pukul ' . $jam . '. Mohon konfirmasi Anda.',
            'url' => route('seminar.pendaftaran'),
        ];
    }
}