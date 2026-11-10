<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Wish;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class WishBooked extends Notification
{
    use Queueable;

    public function __construct(protected Wish $wish, protected Booking $booking) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Подарок «' . $this->wish->title . '» забронирован')
            ->line($this->booking->name . ' забронировал(а) подарок «' . $this->wish->title . '» из вишлиста «' . $this->wish->wishlist->title . '».');

        if ($this->booking->contact) {
            $mail->line('Контакт для связи: ' . $this->booking->contact);
        } else {
            $mail->line('Даритель не оставил контакт — если это незнакомый вам человек, укажите способ передачи подарка (адрес/телеграм) в настройках вишлиста, чтобы он увидел это на странице.');
        }

        return $mail;
    }
}