<?php

namespace App\Mail;

use App\Models\FurnitureRequest;
use App\Models\Notification;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class NewFurnitureRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly Notification $notification,
        private readonly FurnitureRequest $furnitureRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('notifications.email.from_address'),
                (string) config('notifications.email.from_name'),
            ),
            replyTo: $this->replyToAddresses(),
            subject: 'New furniture request received — SL Furnitures',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.new-furniture-request',
            text: 'emails.notifications.new-furniture-request-text',
            with: [
                'notification' => $this->notification,
                'requestReference' => $this->furnitureRequest->request_reference,
                'submittedAt' => $this->submittedAt(),
            ],
        );
    }

    private function submittedAt(): string
    {
        $submittedAt = $this->furnitureRequest->getAttribute('created_at');

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
