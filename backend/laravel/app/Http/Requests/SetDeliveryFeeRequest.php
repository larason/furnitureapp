<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SetDeliveryFeeRequest extends FormRequest
{
    private const ALLOWED_FIELDS = ['delivery_fee'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_fee' => ['required', 'array'],
            'delivery_fee.amount' => ['required', 'integer:strict', 'min:0', 'max:'.Order::MAX_DELIVERY_FEE_AMOUNT],
            'delivery_fee.currency' => ['required', 'string', 'size:3', 'in:'.Order::CURRENCY_TZS],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, self::ALLOWED_FIELDS, true)) {
                    $validator->errors()->add($field, 'This order field cannot be set here.');
                }
            }

            $deliveryFee = $this->input('delivery_fee');
            if (is_array($deliveryFee)) {
                foreach (array_keys($deliveryFee) as $field) {
                    if (! in_array($field, ['amount', 'currency'], true)) {
                        $validator->errors()->add('delivery_fee.'.$field, 'This delivery fee field cannot be set.');
                    }
                }
            }
        });
    }
}
