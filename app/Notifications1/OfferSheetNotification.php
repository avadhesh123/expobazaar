<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferSheetNotification extends Notification { use Queueable; public function __construct(public $offerSheet, public string $type) {} public function via($n): array { return ['database']; } public function toArray($n): array { return ['offer_sheet_id' => $this->offerSheet->id, 'type' => $this->type, 'message' => "Offer sheet {$this->offerSheet->offer_sheet_number}: {$this->type}"]; } }
