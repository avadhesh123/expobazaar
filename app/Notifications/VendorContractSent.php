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
    public function toMail($n): MailMessage
    {
        return (new MailMessage)->subject('Contract Sent - Expo Bazaar')->line('Your consignment contract has been sent. Please download, sign, and upload.')->action('Login', url('/auth/login'));
    }
    public function toArray($n): array
    {
        return ['vendor_id' => $this->vendor->id, 'type' => 'contract_sent', 'title' => 'Contract Sent', 'message' => 'Please download, sign, and upload the consignment contract', 'url' => '/vendor/kyc'];
    }
}
