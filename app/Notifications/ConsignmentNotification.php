<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsignmentNotification extends Notification
{
    use Queueable;
    public function __construct(public $consignment, public string $type) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage
    {
        $num = $this->consignment->consignment_number;
        return match($this->type) {
            'created' => (new MailMessage)->subject("Consignment {$num} Created")->line("A consignment has been created for your products.")->action('View', url('/vendor/consignments')),
            'ready_for_planning' => (new MailMessage)->subject("Consignment {$num} Ready for Planning")->line('A new consignment is ready for container planning.')->action('Container Planning', url('/logistics/container-planning')),
            default => (new MailMessage)->subject("Consignment Update")->line("Consignment {$num}: {$this->type}"),
        };
    }
    public function toArray($n): array
    {
        $num = $this->consignment->consignment_number;
        $messages = [
            'created' => "Consignment {$num} created for your products",
            'ready_for_planning' => "Consignment {$num} ready for container planning",
        ];
        $urls = ['created' => '/vendor/consignments', 'ready_for_planning' => '/logistics/container-planning'];
        return ['consignment_id' => $this->consignment->id, 'type' => $this->type, 'title' => 'Consignment ' . ucfirst(str_replace('_', ' ', $this->type)), 'message' => $messages[$this->type] ?? "Consignment {$num}: {$this->type}", 'url' => $urls[$this->type] ?? '/'];
    }
}
