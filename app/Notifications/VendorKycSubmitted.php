<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorKycSubmitted extends Notification
{
    use Queueable;
    public function __construct(public $vendor) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage
    {
        return (new MailMessage)
            ->subject('KYC Submitted - ' . $this->vendor->company_name)
            ->line("Vendor '{$this->vendor->company_name}' has submitted KYC documents for review.")
            ->action('Review KYC', url('/finance/kyc'));
    }
    public function toArray($n): array
    {
        return [
            'vendor_id' => $this->vendor->id,
            'type' => 'kyc_submitted',
            'title' => 'KYC Submitted',
            'message' => "{$this->vendor->company_name} submitted KYC documents for review",
            'url' => '/finance/kyc',
        ];
    }
}
