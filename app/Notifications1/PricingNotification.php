<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PricingNotification extends Notification { use Queueable; public function __construct(public $asn, public string $type) {} public function via($n): array { return ['database']; } public function toArray($n): array { return ['asn_id' => $this->asn->id, 'type' => $this->type, 'message' => "Pricing {$this->type}"]; } }
