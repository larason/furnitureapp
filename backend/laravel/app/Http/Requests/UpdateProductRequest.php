<?php

namespace App\Http\Requests;

use App\Services\Products\UpdateProductInput;

final class UpdateProductRequest extends ProductFormRequest
{
    public function rules(): array
    {
        return $this->productRules(false);
    }

    public function productInput(): UpdateProductInput
    {
        return new UpdateProductInput($this->validated());
    }
}
