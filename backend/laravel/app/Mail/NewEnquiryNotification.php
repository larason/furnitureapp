<?php

namespace App\Mail;

use App\Models\Enquiry;
use App\Models\Notification;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class NewEnquiryNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly Notification $notification,
        private readonly Enquiry $enquiry,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('notifications.email.from_address'),
                (string) config('notifications.email.from_name'),
            ),
            replyTo: $this->replyToAddresses(),
            subject: 'New customer enquiry received — SL Furnitures',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.new-enquiry',
            text: 'emails.notifications.new-enquiry-text',
            with: [
                'notification' => $this->notification,
                'enquiryReference' => $this->enquiry->enquiry_reference,
                'submittedAt' => $this->submittedAt(),
            ],
        );
    }

    private function submittedAt(): string
    {
        $submittedAt = $this->enquiry->getAttribute('created_at');

        return $submittedAt instanceof DateTimeInterface
            ? $submittedAt->format(DateTimeInterface::ATOM)
            : (string) $submittedAt;
    }

    /** @return array<int, Address> */
    private function replyToAddresses(): array
    {
        $address = config('notifications.email.reply_to_address');
        if (! is_string($address) || trim($address) === '') {
            return [];
        }

        return [new Address($address, (string) config('notifications.email.reply_to_name'))];
    }
}
