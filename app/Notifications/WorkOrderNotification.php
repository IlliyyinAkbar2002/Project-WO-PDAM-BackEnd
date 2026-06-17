<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi Database untuk lifecycle Work Order.
 *
 * Payload `data` di tabel `notifications` mengikuti kontrak FE:
 *   - title         : judul notifikasi (string)
 *   - message       : pesan body (string)
 *   - work_order_id : ID WO terkait (int)
 *   - type          : "wo_created" | "wo_assigned" | "wo_completed"
 *   - sender_name   : nama pengirim untuk avatar/initial di UI
 */
class WorkOrderNotification extends Notification
{
    use Queueable;

    private string $title;
    private string $message;
    private int $workOrderId;
    private string $type;
    private string $senderName;

    public function __construct(
        string $title,
        string $message,
        int $workOrderId,
        string $type,
        string $senderName
    ) {
        $this->title       = $title;
        $this->message     = $message;
        $this->workOrderId = $workOrderId;
        $this->type        = $type;
        $this->senderName  = $senderName;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title'         => $this->title,
            'message'       => $this->message,
            'work_order_id' => $this->workOrderId,
            'type'          => $this->type,
            'sender_name'   => $this->senderName,
        ];
    }
}
