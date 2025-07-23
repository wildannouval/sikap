<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;;
use App\Models\KerjaPraktek;

class KpDisetujui extends Notification
{
    use Queueable;
    protected $kerjaPraktek;

    /**
     * Create a new notification instance.
     */
    public function __construct(KerjaPraktek $kerjaPraktek) { $this->kerjaPraktek = $kerjaPraktek; }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array { return ['database']; }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Pesan notifikasi akan berbeda untuk Mahasiswa dan Bapendik
        if ($notifiable->role === 'Bapendik') {
            return [
                'message' => 'KP untuk ' . $this->kerjaPraktek->mahasiswa->nama_mahasiswa . ' telah disetujui. Silakan terbitkan SPK.',
                'url' => route('bapendik.pengajuan-kp', ['tab' => 'penerbitan']),
                'icon' => 'document-check',
            ];
        }
        return [ // Untuk Mahasiswa
            'message' => 'Selamat! Pengajuan KP Anda telah disetujui.',
            'url' => route('kp.pengajuan'),
            'icon' => 'check-circle',
        ];
    }
}
