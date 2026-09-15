<?php

namespace App\Authentication\Clerk;

use Clerk\Backend\ClerkBackend;
use RuntimeException;
use Throwable;

final class OfficialClerkUserGateway implements ClerkUserGateway
{
    public function __construct(private readonly ClerkBackend $client) {}

    public function getById(string $clerkUserId): ClerkUserSnapshot
    {
        try {
            $response = $this->client->users->get($clerkUserId);
            $user = $response->user;
        } catch (Throwable $exception) {
            throw new RuntimeException('Clerk user retrieval failed.', 0, $exception);
        }

        if ($user === null || $user->id !== $clerkUserId) {
            throw new RuntimeException('Clerk user retrieval returned no matching user.');
        }

        $email = $this->primaryEmail($user);

        if ($email === null) {
            throw new RuntimeException('Clerk user has no primary email.');
        }

        return new ClerkUserSnapshot(
            $user->id,
            $email,
            $this->name($user->firstName, $user->lastName),
            $this->primaryPhone($user),
            $this->emailVerified($user, $email),
        );
    }

    private function primaryEmail(object $user): ?string
    {
        foreach ($user->emailAddresses as $email) {
            if ($email->id === $user->primaryEmailAddressId) {
                return $email->emailAddress;
            }
        }

        return null;
    }

    private function primaryPhone(object $user): ?string
    {
        foreach ($user->phoneNumbers as $phone) {
            if ($phone->id === $user->primaryPhoneNumberId) {
                return $phone->phoneNumber;
            }
        }

        return null;
    }

    private function name(?string $firstName, ?string $lastName): ?string
    {
        $name = trim(implode(' ', array_filter([$firstName, $lastName])));

        return $name === '' ? null : $name;
    }

    private function emailVerified(object $user, string $email): bool
    {
        foreach ($user->emailAddresses as $emailAddress) {
            if ($emailAddress->emailAddress === $email) {
                return $emailAddress->verification?->status === 'verified';
            }
        }

        return false;
    }
}
