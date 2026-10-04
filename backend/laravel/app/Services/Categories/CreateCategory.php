<?php

namespace App\Services\Categories;

use App\Exceptions\Api\ApiException;
use App\Models\Category;
use App\Support\ApiErrorCode;
use App\Support\SpaceType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CreateCategory
{
    public const ROOT_SLUG = 'furnitures-root';

    public function __construct(private readonly CategorySlugAvailability $slugs) {}

    public function create(CreateCategoryInput $input): Category
    {
        try {
            return DB::transaction(function () use ($input): Category {
                $root = Category::query()->where('slug', self::ROOT_SLUG)->lockForUpdate()->first();

                if ($root === null) {
                    throw new ApiException(ApiErrorCode::RESOURCE_NOT_FOUND, 'The required structural category was not found.', 404);
                }

                $this->slugs->assertAvailable($input->slug);
                $category = new Category;
                $category->parent_id = $root->id;
                $category->name = $input->name;
                $category->slug = $input->slug;
                $category->description = $input->description;
                $category->image_url = $input->imageUrl;
                $category->space_type = SpaceType::HYBRID->value;
                $category->display_order = ($root->children()->max('display_order') ?? 0) + 1;
                $category->is_active = true;
                $category->save();

                return $category;
            }, 3);
        } catch (QueryException $exception) {
            $this->slugs->throwIfUniqueViolation($exception);

            throw $exception;
        }
    }
}
