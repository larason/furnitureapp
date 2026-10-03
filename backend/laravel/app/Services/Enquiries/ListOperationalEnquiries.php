<?php

namespace App\Services\Enquiries;

use App\Models\Enquiry;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOperationalEnquiries
{
    /** @return LengthAwarePaginator<int, Enquiry> */
    public function paginate(OperationalEnquiryQuery $query): LengthAwarePaginator
    {
        return Enquiry::query()
            ->with([
                'product' => static fn ($product) => $product->withTrashed(),
                'order',
                'attachments',
            ])
            ->when($query->search !== null, function ($builder) use ($query): void {
                $term = '%'.addcslashes($query->search, '\\%_').'%';
                $builder->where(function ($nested) use ($term): void {
                    $nested->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('message', 'like', $term)
                        ->orWhere('enquiry_reference', 'like', $term)
                        ->orWhereHas('order', fn ($order) => $order->where('order_reference', 'like', $term));
                });
            })
            ->when($query->status !== null, fn ($builder) => $builder->where('enquiry_status', $query->status->value))
            ->when($query->category !== null, fn ($builder) => $builder->where('category', $query->category->value))
            ->when($query->productId !== null, fn ($builder) => $builder->where('product_id', $query->productId))
            ->when($query->orderPublicId !== null, fn ($builder) => $builder->whereHas('order', fn ($order) => $order->where('public_id', $query->orderPublicId)))
            ->when($query->createdFrom !== null, fn ($builder) => $builder->where('created_at', '>=', $query->createdFrom))
            ->when($query->createdTo !== null, fn ($builder) => $builder->where('created_at', '<=', $query->createdTo))
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
