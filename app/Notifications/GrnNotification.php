<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GrnNotification extends Notification
{
    use Queueable;
    public function __construct(public $grn, public string $type) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage
    {
        $num = $this->grn->grn_number;
        return match($this->type) {
            'received' => (new MailMessage)->subject("GRN {$num} - Goods Received")->line('Your shipment has been received at the warehouse.')->action('View', url('/vendor/consignments')),
            'inventory_available' => (new MailMessage)->subject("GRN {$num} - Inventory Available")->line('New inventory has been added from GRN.')->action('Inventory', url('/logistics/inventory')),
            default => (new MailMessage)->subject("GRN Update")->line("GRN {$num}: {$this->type}"),
        };
    }
    public function toArray($n): array
    {
        $num = $this->grn->grn_number;
        $messages = ['received' => "GRN {$num}: your goods have been received", 'inventory_available' => "GRN {$num}: inventory now available for cataloguing"];
        $urls = ['received' => '/vendor/consignments', 'inventory_available' => '/logistics/inventory'];
        return ['grn_id' => $this->grn->id, 'type' => $this->type, 'title' => 'GRN ' . ucfirst(str_replace('_', ' ', $this->type)), 'message' => $messages[$this->type] ?? "GRN {$num}: {$this->type}", 'url' => $urls[$this->type] ?? '/'];
    }
}
