<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LiveSheetNotification extends Notification { use Queueable; public function __construct(public $liveSheet, public string $type) {} public function via($n): array { return ['database']; } public function toArray($n): array { return ['live_sheet_id' => $this->liveSheet->id, 'type' => $this->type, 'message' => "Live sheet {$this->liveSheet->live_sheet_number}: {$this->type}"]; } }
