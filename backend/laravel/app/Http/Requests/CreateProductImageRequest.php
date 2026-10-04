<?php

namespace App\Http\Requests;

use App\Exceptions\Api\ApiException;
use App\Services\ProductImages\ProductImageValidator;
use App\Services\ProductImages\ValidatedProductImage;
use App\Support\ApiErrorCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class CreateProductImageRequest extends FormRequest
{
    private ?ValidatedProductImage $image = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->getInputSource()->all() !== []) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'Only the image file is accepted.', 422, (string) array_key_first($this->getInputSource()->all()));
        }

        $files = $this->allFiles();
        $unknown = array_diff(array_keys($files), ['image']);
        if ($unknown !== []) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'The request contains an unsupported file field.', 422, (string) reset($unknown));
        }

        $file = $files['image'] ?? null;
        if (! $file instanceof UploadedFile) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'Exactly one image file is required.', 422, 'image');
        }

        $this->image = app(ProductImageValidator::class)->validate($file);
    }

    public function validatedImage(): ValidatedProductImage
    {
        return $this->image ?? throw new \LogicException('Product image was not validated.');
    }
}
