<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LiveSheetNotification extends Notification
{
    use Queueable;
    public function __construct(public $liveSheet, public string $type) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage
    {
        $num = $this->liveSheet->live_sheet_number;
        return match($this->type) {
            'fill_required' => (new MailMessage)->subject("Live Sheet {$num} - Fill Details")->line("A live sheet has been created for your products. Please fill the detailed information.")->action('Fill Live Sheet', url('/vendor/live-sheets')),
            'submitted' => (new MailMessage)->subject("Live Sheet {$num} Submitted")->line("Vendor has submitted live sheet details for review.")->action('Review', url('/sourcing/live-sheets')),
            'approved' => (new MailMessage)->subject("Live Sheet {$num} Approved")->line('Your live sheet has been approved and locked.'),
            'ready_for_shipment' => (new MailMessage)->subject("Live Sheet {$num} Ready")->line('A live sheet has been approved and is ready for container planning.')->action('Container Planning', url('/logistics/container-planning')),
            default => (new MailMessage)->subject("Live Sheet Update")->line("Live sheet {$num} status: {$this->type}"),
        };
    }
    public function toArray($n): array
    {
        $num = $this->liveSheet->live_sheet_number;
        $messages = [
            'fill_required' => "Live sheet {$num} created — fill your product details",
            'submitted' => "Live sheet {$num} submitted for review",
            'approved' => "Live sheet {$num} approved and locked",
            'ready_for_shipment' => "Live sheet {$num} ready for container planning",
        ];
        $urls = [
            'fill_required' => '/vendor/live-sheets',
            'submitted' => '/sourcing/live-sheets',
            'approved' => '/vendor/live-sheets',
            'ready_for_shipment' => '/logistics/container-planning',
        ];
        return ['live_sheet_id' => $this->liveSheet->id, 'type' => $this->type, 'title' => 'Live Sheet ' . ucfirst(str_replace('_', ' ', $this->type)), 'message' => $messages[$this->type] ?? "Live sheet {$num}: {$this->type}", 'url' => $urls[$this->type] ?? '/'];
    }
}
