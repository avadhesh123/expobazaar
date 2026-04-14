<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorApproved extends Notification
{
    use Queueable;
    public function __construct(public $vendor, public string $type)
    {
    }
    public function via($n): array
    {
        return ['mail', 'database'];
    }
    public function toMail($n): MailMessage
    {
        return match($this->type) {
            'creation_request' => (new MailMessage())
                ->subject('Vendor Creation Request')
                ->line("Vendor '{$this->vendor->company_name}' requires approval."),
            'welcome' => (new MailMessage())
                ->subject('Welcome to Expo Bazaar - Complete Your KYC')
                ->greeting("Hello {$this->vendor->contact_person},")
                ->line("Your vendor account has been created on Expo Bazaar SCM platform.")
                ->line("**Company:** {$this->vendor->company_name}")
                ->line("**Vendor Code:** {$this->vendor->vendor_code}")
                ->line("Please login using your email OTP and complete your KYC registration to access all features.")
                ->action('Login to Expo Bazaar', url('/auth/login'))
                ->line('After logging in, go to KYC Documents to submit your registration form and signed contract.'),
            'account_creation' => (new MailMessage())
                ->subject('Account Approved - Expo Bazaar')
                ->line('Your vendor account has been approved. Please login and complete your KYC.')
                ->action('Login', url('/auth/login')),
            'kyc_rejected' => (new MailMessage())
                ->subject('KYC Rejected - Expo Bazaar')
                ->line("Your KYC submission has been rejected.")
                ->line("**Reason:** {$this->vendor->kyc_rejection_reason}")
                ->line('Please login and re-submit your KYC with the required corrections.')
                ->action('Login & Re-submit', url('/auth/login')),
            default => (new MailMessage())
                ->subject('Vendor Update - Expo Bazaar')
                ->line('Your vendor status has been updated.'),
        };
    }
    public function toArray($n): array
    {
        return [
            'vendor_id' => $this->vendor->id,
            'type' => $this->type,
            'message' => "Vendor {$this->vendor->company_name}: {$this->type}",
        ];
    }
}
