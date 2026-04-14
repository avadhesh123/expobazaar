<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChargebackNotification extends Notification
{
    use Queueable;
    public function __construct(public $chargeback, public string $type) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage
    {
        return match($this->type) {
            'raised' => (new MailMessage)->subject('Chargeback Raised - Needs Confirmation')->line("A chargeback of \${$this->chargeback->amount} has been raised against order #{$this->chargeback->order_id}.")->action('Review', url('/sourcing/chargebacks')),
            'confirmed' => (new MailMessage)->subject('Chargeback Confirmed')->line("Chargeback of \${$this->chargeback->amount} has been confirmed and will be deducted from your payout."),
            'rejected' => (new MailMessage)->subject('Chargeback Rejected')->line("Chargeback of \${$this->chargeback->amount} has been rejected."),
            default => (new MailMessage)->subject('Chargeback Update')->line("Chargeback status: {$this->type}"),
        };
    }
    public function toArray($n): array
    {
        $messages = [
            'raised' => "Chargeback \${$this->chargeback->amount} raised — needs confirmation",
            'confirmed' => "Chargeback \${$this->chargeback->amount} confirmed — will be deducted from payout",
            'rejected' => "Chargeback \${$this->chargeback->amount} rejected",
        ];
        $urls = ['raised' => '/sourcing/chargebacks', 'confirmed' => '/vendor/chargebacks', 'rejected' => '/vendor/chargebacks'];
        return ['chargeback_id' => $this->chargeback->id, 'type' => $this->type, 'title' => 'Chargeback ' . ucfirst($this->type), 'message' => $messages[$this->type] ?? "Chargeback: {$this->type}", 'url' => $urls[$this->type] ?? '/'];
    }
}
