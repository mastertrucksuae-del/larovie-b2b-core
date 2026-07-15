<?php

namespace App\Notifications;

use App\Models\Inquiry;
use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Acknowledgement email to the buyer with their reference number (P1 #11).
 */
class BuyerInquiryReceivedNotification extends Notification
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
        $company = Setting::current()->company_name;
        $isArabic = $inquiry->locale === 'ar';

        if ($isArabic) {
            return (new MailMessage)
                ->subject("تم استلام طلبك — {$inquiry->reference}")
                ->greeting("مرحباً {$inquiry->customer_name}")
                ->line("شكراً لتواصلك مع {$company}. لقد استلمنا طلبك.")
                ->line("رقمك المرجعي: **{$inquiry->reference}**")
                ->line('سيتواصل معك فريقنا قريباً بعرض السعر.')
                ->salutation("مع خالص التقدير، فريق {$company}");
        }

        return (new MailMessage)
            ->subject("We've received your inquiry — {$inquiry->reference}")
            ->greeting("Hello {$inquiry->customer_name}")
            ->line("Thank you for contacting {$company}. We have received your wholesale inquiry.")
            ->line("Your reference number: **{$inquiry->reference}**")
            ->line('Our team will be in touch shortly with a quotation.')
            ->salutation("Warm regards, the {$company} team");
    }
}
