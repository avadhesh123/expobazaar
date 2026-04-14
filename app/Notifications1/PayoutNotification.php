<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PayoutNotification extends Notification { use Queueable; public function __construct(public $payout, public string $type) {} public function via($n): array { return ['mail', 'database']; } public function toMail($n) { return (new MailMessage)->subject("Vendor Payout: {$this->type}")->line("Payout of {$this->payout->net_payout} for {$this->payout->payout_month}/{$this->payout->payout_year}: {$this->type}"); } public function toArray($n): array { return ['payout_id' => $this->payout->id, 'type' => $this->type, 'message' => "Payout {$this->type}: {$this->payout->net_payout}"]; } }
