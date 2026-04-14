<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GrnNotification extends Notification { use Queueable; public function __construct(public $grn, public string $type) {} public function via($n): array { return ['database']; } public function toArray($n): array { return ['grn_id' => $this->grn->id, 'type' => $this->type, 'message' => "GRN {$this->grn->grn_number}: {$this->type}"]; } }
