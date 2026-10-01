<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CreateEnquiryRequest;
use App\Http\Resources\EnquiryResource;
use App\Models\User;
use App\Services\Enquiries\CreateEnquiry;
use App\Services\Enquiries\CreateEnquiryCommand;
use Illuminate\Http\JsonResponse;

class EnquiryController extends V1Controller
{
    public function store(CreateEnquiryRequest $request, CreateEnquiry $creator): JsonResponse
    {
        $input = $request->normalizedInput();
        $actor = $request->user();

        $enquiry = $creator->create(new CreateEnquiryCommand(
            actor: $actor instanceof User ? $actor : null,
            name: $input->name,
            phone: $input->phone,
            email: $input->email,
            subject: $input->subject,
            message: $input->message,
            category: $input->category,
            productId: $input->productId,
            orderId: $input->orderId,
        ));

        $enquiry->loadMissing(['product', 'order']);

        return (new EnquiryResource($enquiry))
            ->response()
            ->setStatusCode(201)
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Vary' => 'Authorization',
            ]);
    }

    public function meIndex(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function meShow(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function index(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function show(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function close(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function storeAttachment(): JsonResponse
    {
        return $this->notImplemented();
    }
}
