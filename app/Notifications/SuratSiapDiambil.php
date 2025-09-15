<?php

namespace App\Notifications;

use App\Models\SuratPengantar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SuratSiapDiambil extends Notification
{
    use Queueable;

    protected $suratPengantar;

    /**
     * Create a new notification instance.
     */
    public function __construct(SuratPengantar $suratPengantar)
    {
        $this->suratPengantar = $suratPengantar;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Anda bisa menambahkan 'mail' jika ingin mengirim email
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Surat Pengantar Siap Diambil',
            'message' => 'Surat Pengantar KP Anda untuk instansi ' . $this->suratPengantar->lokasi_surat_pengantar . ' sudah dapat diambil di Bapendik.',
            'url' => route('surat-pengantar.index'),
        ];
    }
}