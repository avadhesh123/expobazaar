<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class TrialLogin extends Command
{
    protected $signature = 'trial:login {--email= : Email of user to generate OTP for} {--list : List all available users}';
    protected $description = 'Generate a test OTP for local trial (dev only)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command is not available in production.');
            return 1;
        }

        if ($this->option('list')) {
            $users = User::where('status', 'active')->get(['id', 'name', 'email', 'user_type', 'department']);
            $this->table(['ID', 'Name', 'Email', 'Type', 'Department'], $users->toArray());
            return 0;
        }

        $email = $this->option('email') ?? $this->ask('Enter user email', 'admin@expobazaar.com');
        $user = User::where('email', $email)->where('status', 'active')->first();

        if (!$user) {
            $this->error("No active user found with email: {$email}");
            $this->info('Use --list to see available users.');
            return 1;
        }

        // Generate fixed OTP for trial
        $otp = \App\Models\EmailOtp::generate($email);

        $this->newLine();
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║       TRIAL LOGIN OTP                ║');
        $this->info('╠══════════════════════════════════════╣');
        $this->info("║  User:  {$user->name}");
        $this->info("║  Email: {$email}");
        $this->info("║  Type:  {$user->user_type} / {$user->department}");
        $this->info('║                                      ');
        $this->info("║  OTP:   {$otp->otp}                  ");
        $this->info('║                                      ');
        $this->info("║  Expires: {$otp->expires_at->format('H:i:s')}");
        $this->info('╚══════════════════════════════════════╝');
        $this->newLine();
        $this->info("1. Go to http://localhost:8000/auth/login");
        $this->info("2. Enter email: {$email}");
        $this->info("3. Enter OTP:   {$otp->otp}");
        $this->newLine();

        return 0;
    }
}
