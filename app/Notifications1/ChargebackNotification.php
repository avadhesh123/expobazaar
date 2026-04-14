<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ChargebackNotification extends Notification { use Queueable; public function __construct(public $chargeback, public string $type) {} public function via($n): array { return ['mail', 'database']; } public function toMail($n) { return (new MailMessage)->subject("Chargeback {$this->type}")->line("Chargeback of {$this->chargeback->amount} for order #{$this->chargeback->order_id}: {$this->type}"); } public function toArray($n): array { return ['chargeback_id' => $this->chargeback->id, 'type' => $this->type, 'message' => "Chargeback {$this->type}: {$this->chargeback->amount}"]; } }
