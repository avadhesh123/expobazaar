<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConsignmentNotification extends Notification { use Queueable; public function __construct(public $consignment, public string $type) {} public function via($n): array { return ['mail', 'database']; } public function toMail($n) { return (new \Illuminate\Notifications\Messages\MailMessage)->subject("Consignment {$this->consignment->consignment_number}")->line("Consignment update: {$this->type}"); } public function toArray($n): array { return ['consignment_id' => $this->consignment->id, 'type' => $this->type, 'message' => "Consignment {$this->consignment->consignment_number}: {$this->type}"]; } }
