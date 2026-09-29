<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Services\Checkout\DeliveryFulfillmentState;
use App\Support\ApiErrorCode;
use App\Support\FulfillmentType;
use App\Support\IdempotencyKeyHeader;
use DomainException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CHK-001 request boundary: strict transport/shape validation only. Input is
 * JSON-body only; any query parameter is rejected so business values cannot be
 * supplied outside the documented allow-list. It owns no Cart/Product/Variant/
 * stock/pricing queries; those are decided inside the Phase 7.7 transaction.
 * Branch and delivery-domain checks reuse the existing Phase 7.2/7.4
 * normalization authority and map to canonical codes.
 */
final class CheckoutRequest extends FormRequest
{
    private const TOP_LEVEL_FIELDS = ['fulfillment_type', 'delivery_address'];

    private const ADDRESS_FIELDS = ['recipient_name', 'phone', 'address_line', 'city'];

    /** @var array{recipient_name: string, phone: string, address_line: string, city: string}|null */
    private ?array $normalizedDeliveryAddress = null;

    private ?string $idempotencyKey = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->idempotencyKey = IdempotencyKeyHeader::require($this);

        $queryParameter = array_key_first($this->query->all());

        if ($queryParameter !== null) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'Checkout accepts a JSON body only.', 422, (string) $queryParameter);
        }

        $unknown = array_diff(array_keys($this->all()), self::TOP_LEVEL_FIELDS);

        if ($unknown !== []) {
            $field = (string) reset($unknown);

            throw new ApiException(
                ApiErrorCode::INVALID_VALUE,
                'The request contains an unsupported parameter.',
                422,
                $field === '' ? null : $field,
            );
        }
    }

    public function idempotencyKey(): string
    {
        return (string) $this->idempotencyKey;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'fulfillment_type' => ['required', 'string', Rule::in([
                FulfillmentType::PICKUP->value,
                FulfillmentType::DELIVERY->value,
            ])],
            'delivery_address' => [
                'required_if:fulfillment_type,'.FulfillmentType::DELIVERY->value,
                'nullable',
                'array:'.implode(',', self::ADDRESS_FIELDS),
            ],
            'delivery_address.recipient_name' => ['sometimes', 'string'],
            'delivery_address.phone' => ['sometimes', 'string'],
            'delivery_address.address_line' => ['sometimes', 'string'],
            'delivery_address.city' => ['sometimes', 'string'],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->validated('fulfillment_type') === FulfillmentType::PICKUP->value) {
            if ($this->input('delivery_address') !== null) {
                throw new ApiException(ApiErrorCode::INVALID_FULFILLMENT, 'PICKUP checkout does not accept a delivery address.', 422, 'delivery_address');
            }

            $this->normalizedDeliveryAddress = null;

            return;
        }

        try {
            $state = DeliveryFulfillmentState::fromInput([
                'fulfillment_type' => FulfillmentType::DELIVERY->value,
                'delivery_address' => $this->input('delivery_address'),
            ]);
        } catch (DomainException) {
            throw new ApiException(ApiErrorCode::INVALID_DELIVERY_INFORMATION, 'The delivery address is invalid.', 422, 'delivery_address');
        }

        $this->normalizedDeliveryAddress = $state->addressSnapshots()['delivery_address'];
    }

    /** @return array{recipient_name: string, phone: string, address_line: string, city: string}|null */
    public function normalizedDeliveryAddress(): ?array
    {
        return $this->normalizedDeliveryAddress;
    }
}
