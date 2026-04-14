<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AsnNotification extends Notification
{
    use Queueable;
    public function __construct(public $asn, public string $type) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage
    {
        $num = $this->asn->asn_number;
        return match($this->type) {
            'pricing_required' => (new MailMessage)->subject("ASN {$num} - Pricing Required")->line('A new ASN has been generated and requires platform pricing.')->action('Prepare Pricing', url('/hod/asn')),
            default => (new MailMessage)->subject("ASN Update")->line("ASN {$num}: {$this->type}"),
        };
    }
    public function toArray($n): array
    {
        return ['asn_id' => $this->asn->id, 'type' => $this->type, 'title' => 'ASN ' . ucfirst(str_replace('_', ' ', $this->type)), 'message' => "ASN {$this->asn->asn_number} requires platform pricing", 'url' => '/hod/asn'];
    }
}
