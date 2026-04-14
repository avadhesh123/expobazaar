<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorContractSent extends Notification
{
    use Queueable;
    public function __construct(public $vendor) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage { return (new MailMessage)->subject('Contract Ready for Signature')->line('Your contract is ready. Please sign via DocuSign.')->action('Sign Contract', url('/vendor/dashboard')); }
    public function toArray($n): array { return ['vendor_id' => $this->vendor->id, 'message' => 'Contract sent for signature']; }
}
