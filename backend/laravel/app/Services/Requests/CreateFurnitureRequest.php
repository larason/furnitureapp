<?php

namespace App\Services\Requests;

use App\Models\Attachment;
use App\Models\FurnitureRequest;
use App\Models\Product;
use App\Services\Attachments\AttachFileToParent;
use App\Support\ReferenceGenerator;
use App\Support\RequestStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Authoritative REQ-001 creation service.
 *
 * Derives ownership from the resolved actor, resolves the optional linked
 * Product through the shared public visibility authority, generates the
 * server reference, maps the public `notes` field to the persistence `message`
 * column, optionally stores the inline private attachment, and persists the
 * Request as `SUBMITTED`. It creates no Order, Payment, or inventory effect and
 * commits no price/quote. When an attachment file was written but the creation
 * transaction fails, the file is compensatorily deleted.
 */
final class CreateFurnitureRequest
{
    private const REFERENCE_LENGTH = 10;

    private const MAX_REFERENCE_ATTEMPTS = 3;

    public function __construct(
        private readonly RequestableProductResolver $products,
        private readonly AttachFileToParent $attachments,
    ) {}

    public function create(CreateFurnitureRequestCommand $command): FurnitureRequest
    {
        $product = $this->products->resolve($command->productId);
        $storedAttachment = null;

        try {
            return DB::transaction(function () use ($command, $product, &$storedAttachment): FurnitureRequest {
                $request = $this->persist($command, $product);

                if ($command->attachment !== null) {
                    $storedAttachment = $this->attachments->attachRequest($request, $command->attachment);
                }

                return $request;
            });
        } catch (Throwable $exception) {
            if ($storedAttachment instanceof Attachment) {
                try {
                    $this->attachments->delete($storedAttachment);
                } catch (Throwable $cleanup) {
                    Log::warning('attachment.cleanup_failed', [
                        'exception' => $cleanup::class,
                    ]);
                }
            }

            throw $exception;
        }
    }

    private function persist(CreateFurnitureRequestCommand $command, ?Product $product): FurnitureRequest
    {
        for ($attempt = 1; ; $attempt++) {
            $request = $this->build($command, $product);

            try {
                $request->save();

                return $request;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= self::MAX_REFERENCE_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    private function build(CreateFurnitureRequestCommand $command, ?Product $product): FurnitureRequest
    {
        $request = new FurnitureRequest;
        $request->user_id = $command->actor === null ? null : (int) $command->actor->getKey();
        $request->request_reference = ReferenceGenerator::generate(FurnitureRequest::REFERENCE_PREFIX, self::REFERENCE_LENGTH);
        $request->product_id = $product?->getKey();
        $request->name = $command->name;
        $request->phone = $command->phone;
        $request->email = $command->email;
        $request->message = $command->notes;
        $request->quantity = $command->quantity;
        $request->dimensions = $command->dimensions;
        $request->material = $command->material;
        $request->color = $command->color;
        $request->request_status = RequestStatus::SUBMITTED;

        return $request;
    }
}
