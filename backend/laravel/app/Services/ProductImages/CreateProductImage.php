<?php

namespace App\Services\ProductImages;

use App\Exceptions\Api\ApiException;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ApiErrorCode;
use App\Support\ProductIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class CreateProductImage
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(private readonly ProductImageStorage $storage) {}

    public function create(Product $product, ValidatedProductImage $file): ProductImage
    {
        $key = $this->key($product, $file);

        try {
            $this->storage->put($key, $file);
        } catch (Throwable $exception) {
            throw new ApiException(ApiErrorCode::EXTERNAL_SERVICE_ERROR, 'The image storage service is temporarily unavailable.', 503, previous: $exception);
        }

        try {
            return DB::transaction(function () use ($product, $key): ProductImage {
                $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->getKey());
                $images = ProductImage::query()->where('product_id', $lockedProduct->id);
                $sortOrder = ((int) $images->max('sort_order')) + 1;
                $isPrimary = ! (clone $images)->where('is_primary', true)->exists();

                $image = new ProductImage([
                    'product_variant_id' => null,
                    'file_path' => $key,
                    'alt_text' => $lockedProduct->name,
                    'sort_order' => $sortOrder,
                    'is_primary' => $isPrimary,
                ]);
                $image->product_id = $lockedProduct->id;
                $image->save();

                return $image;
            }, self::TRANSACTION_ATTEMPTS);
        } catch (Throwable $exception) {
            $this->cleanup($key, $exception);

            throw new ApiException(ApiErrorCode::INTERNAL_SERVER_ERROR, 'The image could not be saved.', 500, previous: $exception);
        }
    }

    private function key(Product $product, ValidatedProductImage $file): string
    {
        return 'products/'.ProductIdentifier::encode($product).'/img_'.strtolower((string) Str::ulid()).'.'.$file->extension;
    }

    private function cleanup(string $key, Throwable $persistenceFailure): void
    {
        try {
            $this->storage->delete($key);
        } catch (Throwable $cleanupFailure) {
            Log::error('product_image.cleanup_failed', [
                'object_key' => $key,
                'operation' => 'delete_after_database_failure',
                'request_id' => request()->attributes->get('request_id'),
                'persistence_exception' => $persistenceFailure::class,
                'cleanup_exception' => $cleanupFailure::class,
            ]);
        }
    }
}
