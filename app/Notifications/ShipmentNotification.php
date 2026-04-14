<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShipmentNotification extends Notification
{
    use Queueable;
    public function __construct(public $shipment, public string $type) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage
    {
        $code = $this->shipment->shipment_code;
        return match($this->type) {
            'capacity_warning' => (new MailMessage)->subject("Shipment {$code} Over Capacity")->line('This shipment exceeds container capacity. Please review.')->action('View', url('/logistics/shipments')),
            'sailing_date_updated' => (new MailMessage)->subject("Shipment {$code} Sailing Date Set")->line("Sailing date: {$this->shipment->sailing_date}.")->action('View', url('/vendor/consignments')),
            default => (new MailMessage)->subject("Shipment Update")->line("Shipment {$code}: {$this->type}"),
        };
    }
    public function toArray($n): array
    {
        $code = $this->shipment->shipment_code;
        $messages = [
            'capacity_warning' => "Shipment {$code} exceeds container capacity",
            'sailing_date_updated' => "Shipment {$code} sailing date set: {$this->shipment->sailing_date}",
        ];
        return ['shipment_id' => $this->shipment->id, 'type' => $this->type, 'title' => 'Shipment ' . ucfirst(str_replace('_', ' ', $this->type)), 'message' => $messages[$this->type] ?? "Shipment {$code}: {$this->type}", 'url' => '/logistics/shipments'];
    }
}
