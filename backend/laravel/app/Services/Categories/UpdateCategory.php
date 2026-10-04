<?php

namespace App\Services\Categories;

use App\Exceptions\Api\ApiException;
use App\Models\Category;
use App\Support\ApiErrorCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class UpdateCategory
{
    public function __construct(private readonly CategorySlugAvailability $slugs) {}

    public function update(Category $category, UpdateCategoryInput $input): Category
    {
        try {
            return DB::transaction(function () use ($category, $input): Category {
                $lockedCategory = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();

                if ($lockedCategory->slug === CreateCategory::ROOT_SLUG) {
                    throw new ApiException(ApiErrorCode::FORBIDDEN, 'The structural category cannot be updated.', 403);
                }

                if ($input->hasSlug) {
                    $this->slugs->assertAvailable($input->slug, $lockedCategory);
                    $lockedCategory->slug = $input->slug;
                }

                if ($input->hasName) {
                    $lockedCategory->name = $input->name;
                }

                if ($input->hasDescription) {
                    $lockedCategory->description = $input->description;
                }

                if ($input->hasImageUrl) {
                    $lockedCategory->image_url = $input->imageUrl;
                }

                if ($lockedCategory->isDirty()) {
                    $lockedCategory->save();
                }

                return $lockedCategory;
            });
        } catch (QueryException $exception) {
            $this->slugs->throwIfUniqueViolation($exception);

            throw $exception;
        }
    }
}
