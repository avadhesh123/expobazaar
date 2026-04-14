<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AsnNotification extends Notification { use Queueable; public function __construct(public $asn, public string $type) {} public function via($n): array { return ['mail', 'database']; } public function toMail($n) { return (new \Illuminate\Notifications\Messages\MailMessage)->subject("ASN {$this->asn->asn_number}: {$this->type}")->line("ASN ready for action.")->action('View ASN', url('/hod/asn')); } public function toArray($n): array { return ['asn_id' => $this->asn->id, 'type' => $this->type, 'message' => "ASN {$this->asn->asn_number}: {$this->type}"]; } }
