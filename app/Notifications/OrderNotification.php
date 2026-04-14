<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderNotification extends Notification { use Queueable; public function __construct(public $count, public string $type) {} public function via($n): array { return ['database']; } public function toArray($n): array { return ['count' => $this->count, 'type' => $this->type, 'message' => "{$this->count} orders: {$this->type}"]; } }
