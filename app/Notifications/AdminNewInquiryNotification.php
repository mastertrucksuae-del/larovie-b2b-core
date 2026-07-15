<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Instant email alert to the admin on every new inquiry (P0 #8).
 * Target response SLA is 4 business hours.
 */
class AdminNewInquiryNotification extends Notification
{
    public function __construct(public Inquiry $inquiry)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inquiry = $this->inquiry;
        $itemCount = $inquiry->items()->count();

        $mail = (new MailMessage)
            ->subject("New wholesale inquiry — {$inquiry->reference}")
            ->greeting('New inquiry received')
            ->line("**Reference:** {$inquiry->reference}")
            ->line("**Customer:** {$inquiry->customer_name}".($inquiry->customer_company ? " ({$inquiry->customer_company})" : ''))
            ->line("**Mobile:** {$inquiry->customer_mobile}")
            ->line('**Items:** '.($itemCount ?: 'general enquiry (no line items)'));

        if ($inquiry->utm_source) {
            $mail->line("**Source:** utm_source={$inquiry->utm_source}".($inquiry->utm_campaign ? " / {$inquiry->utm_campaign}" : ''));
        }
        if ($inquiry->referral_code) {
            $mail->line("**Referral code:** {$inquiry->referral_code}");
        }
        if ($inquiry->customer_message) {
            $mail->line('**Message:** '.$inquiry->customer_message);
        }

        return $mail
            ->action('Open in admin', url('/admin/inquiries/'.$inquiry->getKey().'/edit'))
            ->line('Target response SLA: 4 business hours.');
    }
}
