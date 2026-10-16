<?php

namespace App\Services\ProductImages;

use App\Jobs\DeleteProductImageObject;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Removes an image record and its stored object. The database row is the
 * authority and is deleted first: an object-deletion failure then leaves an
 * invisible orphan rather than a visible reference to a missing object. A failed
 * object deletion is recorded durably as a retryable job instead of being
 * reported as a completed removal with nothing on record.
 */
final class DeleteProductImage
{
    public function __construct(private readonly ProductImageStorage $storage) {}

    public function delete(Product $product, ProductImage $image): void
    {
        $disk = (string) config('product_images.disk');
        $objectKey = $image->file_path;

        DB::transaction(function () use ($product, $image): void {
            Product::query()->lockForUpdate()->findOrFail($product->getKey());

            ProductImage::query()
                ->where('product_id', $product->getKey())
                ->whereKey($image->getKey())
                ->delete();
        });

        $this->discardObject($disk, $objectKey);
    }

    /**
     * The deletion target always comes from the persisted record, never from
     * client input, so a retry can only ever target this image's object.
     */
    private function discardObject(string $disk, string $objectKey): void
    {
        try {
            $this->storage->delete($objectKey);
        } catch (Throwable $exception) {
            DeleteProductImageObject::dispatch($disk, $objectKey);

            Log::warning('product_image.object_delete_deferred', [
                'disk' => $disk,
                'object_key' => $objectKey,
                'exception' => $exception::class,
            ]);
        }
    }
}
