<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WarehouseNotification extends Notification
{
    use Queueable;
    public function __construct(public $charge, public string $type) {}
    public function via($n): array { return ['database']; }
    public function toArray($n): array
    {
        return ['type' => $this->type, 'title' => 'Warehouse Charges', 'message' => "Warehouse charges {$this->type} for vendor", 'url' => '/logistics/warehouse-charges'];
    }
}
