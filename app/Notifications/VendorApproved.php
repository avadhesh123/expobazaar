<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorApproved extends Notification
{
    use Queueable;
    public function __construct(public $vendor, public string $type) {}
    public function via($n): array { return ['mail', 'database']; }
    public function toMail($n): MailMessage
    {
        return match($this->type) {
            'creation_request' => (new MailMessage)->subject('Vendor Creation Request - Expo Bazaar')->line("Vendor '{$this->vendor->company_name}' requires approval."),
            'welcome' => (new MailMessage)
                ->subject('Welcome to Expo Bazaar - Complete Your KYC')
                ->greeting("Hello {$this->vendor->contact_person},")
                ->line("Your vendor account has been created on Expo Bazaar SCM platform.")
                ->line("Company: {$this->vendor->company_name}")
                ->line("Vendor Code: {$this->vendor->vendor_code}")
                ->line('Please login using your email OTP and complete your KYC registration.')
                ->action('Login to Expo Bazaar', url('/auth/login')),
            'account_creation' => (new MailMessage)->subject('Account Approved - Expo Bazaar')->line('Your vendor account has been approved.')->action('Login', url('/auth/login')),
            'kyc_rejected' => (new MailMessage)->subject('KYC Rejected - Expo Bazaar')->line("Reason: {$this->vendor->kyc_rejection_reason}"),
            default => (new MailMessage)->subject('Vendor Update - Expo Bazaar')->line('Your vendor status has been updated.'),
        };
    }
    public function toArray($n): array
    {
        $messages = [
            'creation_request' => "New vendor '{$this->vendor->company_name}' needs approval",
            'welcome' => "Welcome! Please complete your KYC registration",
            'account_creation' => "Your account has been approved",
            'kyc_rejected' => "KYC rejected: {$this->vendor->kyc_rejection_reason}",
        ];
        return [
            'vendor_id' => $this->vendor->id,
            'type' => $this->type,
            'title' => 'Vendor ' . ucfirst(str_replace('_', ' ', $this->type)),
            'message' => $messages[$this->type] ?? "Vendor {$this->vendor->company_name}: {$this->type}",
            'url' => $this->type === 'creation_request' ? '/admin/vendors/pending' : '/vendor/dashboard',
        ];
    }
}
