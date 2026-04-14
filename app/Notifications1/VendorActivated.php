<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorActivated extends Notification
{
    use Queueable;
    public function __construct(public $vendor) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage { return (new MailMessage)->subject('Vendor Panel Activated')->line('Your vendor panel is now active. You can start submitting offer sheets.')->action('Go to Dashboard', url('/vendor/dashboard')); }
    public function toArray($n): array { return ['vendor_id' => $this->vendor->id, 'message' => "Vendor {$this->vendor->company_name} activated"]; }
}
