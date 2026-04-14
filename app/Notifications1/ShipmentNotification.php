<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ShipmentNotification extends Notification { use Queueable; public function __construct(public $shipment, public string $type) {} public function via($n): array { return ['mail', 'database']; } public function toMail($n) { return (new MailMessage)->subject("Shipment {$this->shipment->shipment_code}: {$this->type}")->line("Shipment update for {$this->shipment->shipment_code}."); } public function toArray($n): array { return ['shipment_id' => $this->shipment->id, 'type' => $this->type, 'message' => "Shipment {$this->shipment->shipment_code}: {$this->type}"]; } }
