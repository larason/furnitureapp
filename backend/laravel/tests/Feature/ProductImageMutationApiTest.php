<?php

namespace Tests\Feature;

use App\Jobs\DeleteProductImageObject;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Support\PermissionName;
use App\Support\ProductIdentifier;
use App\Support\ProductImageIdentifier;
use Database\Seeders\RbacSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

/**
 * Covers the four approved product-media mutations: delete (CAT-015), alt text
 * (CAT-016), primary selection (CAT-017) and reorder (CAT-018).
 */
final class ProductImageMutationApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    private const PRODUCTS = '/api/v1/products/';

    private const PUBLIC_BASE_URL = 'https://cdn.test/media';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'product_images.disk' => 'product_images',
            'product_images.public_base_url' => self::PUBLIC_BASE_URL,
        ]);
        Storage::fake('product_images');
        $this->seed(RbacSeeder::class);
    }

    public function test_delete_removes_the_record_and_object_without_touching_siblings(): void
    {
        $product = Product::factory()->create();
        $target = $this->createImage($product, ['sort_order' => 1]);
        $sibling = $this->createImage($product, ['sort_order' => 2]);
        $headers = $this->operator();

        $this->json('DELETE', $this->imageUrl($product, $target), [], $headers)->assertNoContent();

        $this->assertDatabaseMissing('product_images', ['id' => $target->getKey()]);
        Storage::disk('product_images')->assertMissing($target->file_path);
        $this->assertDatabaseHas('product_images', ['id' => $sibling->getKey()]);
        Storage::disk('product_images')->assertExists($sibling->file_path);
        $this->assertDatabaseHas('products', ['id' => $product->getKey()]);

        $this->json('DELETE', $this->imageUrl($product, $target), [], $headers)->assertNotFound();
    }

    public function test_delete_rejects_cross_product_unknown_and_malformed_targets(): void
    {
        $owner = Product::factory()->create();
        $foreign = Product::factory()->create();
        $image = $this->createImage($owner);
        $headers = $this->operator();

        $this->json('DELETE', $this->imageUrl($foreign, $image), [], $headers)->assertNotFound();
        $this->json('DELETE', $this->productUrl($owner, '/img_zzzzzzzzzz'), [], $headers)->assertNotFound();
        $this->json('DELETE', $this->productUrl($owner, '/not-an-identifier'), [], $headers)->assertNotFound();
        $this->json('DELETE', self::PRODUCTS.'prod_zzzzzzzz/images/img_1', [], $headers)->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $image->getKey()]);
        Storage::disk('product_images')->assertExists($image->file_path);
    }

    public function test_deleting_the_primary_image_leaves_the_product_without_one(): void
    {
        $product = Product::factory()->create();
        $primary = $this->createImage($product, ['sort_order' => 1, 'is_primary' => true]);
        $secondary = $this->createImage($product, ['sort_order' => 2]);

        $this->json('DELETE', $this->imageUrl($product, $primary), [], $this->operator())->assertNoContent();

        $this->assertSinglePrimary($product, null);
        $this->assertFalse($secondary->fresh()->is_primary);
    }

    public function test_delete_tolerates_a_missing_object_and_defers_on_storage_failure(): void
    {
        $product = Product::factory()->create();
        $absent = ProductImage::factory()->for($product)->create(['file_path' => 'products/prod_x/gone.png']);

        $this->json('DELETE', $this->imageUrl($product, $absent), [], $this->operator())->assertNoContent();
        $this->assertDatabaseMissing('product_images', ['id' => $absent->getKey()]);

        Queue::fake();
        $present = $this->createImage($product, ['sort_order' => 2]);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('product_images', $disk);

        $this->json('DELETE', $this->imageUrl($product, $present), [], $this->operator())->assertNoContent();

        $this->assertDatabaseMissing('product_images', ['id' => $present->getKey()]);
        Queue::assertPushed(
            DeleteProductImageObject::class,
            fn (DeleteProductImageObject $job): bool => $job->disk === 'product_images'
                && $job->objectKey === $present->file_path,
        );
    }

    public function test_delete_failure_is_retained_on_a_durable_queue_connection(): void
    {
        config(['queue.default' => 'database']);
        $product = Product::factory()->create();
        $image = $this->createImage($product, ['sort_order' => 1]);
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('product_images', $disk);

        $this->json('DELETE', $this->imageUrl($product, $image), [], $this->operator())->assertNoContent();

        $this->assertDatabaseMissing('product_images', ['id' => $image->getKey()]);

        $payload = json_decode((string) DB::table('jobs')->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        /** @var DeleteProductImageObject $job */
        $job = unserialize((string) $payload['data']['command'], ['allowed_classes' => [DeleteProductImageObject::class]]);

        $this->assertInstanceOf(
            DeleteProductImageObject::class,
            $job,
            'A failed object deletion must be retained on the durable queue for retry.',
        );
        $this->assertSame('product_images', $job->disk);
        $this->assertSame($image->file_path, $job->objectKey);
    }

    public function test_alt_text_updates_and_clears_without_touching_other_fields(): void
    {
        $product = Product::factory()->create();
        $image = $this->createImage($product, ['alt_text' => 'Old text', 'sort_order' => 4, 'is_primary' => true]);
        $headers = $this->operator();

        $updated = $this->json('PATCH', $this->imageUrl($product, $image), ['alt_text' => 'New text'], $headers)->assertOk();
        $updated->assertJsonPath('data.alt_text', 'New text');
        $updated->assertJsonPath('data.url', self::PUBLIC_BASE_URL.'/'.$image->file_path);
        $this->assertSame(['id', 'url', 'alt_text', 'sort_order', 'is_primary'], array_keys($updated->json('data')));

        $this->json('PATCH', $this->imageUrl($product, $image), ['alt_text' => '  padded  '], $headers)
            ->assertOk()
            ->assertJsonPath('data.alt_text', 'padded');
        $this->json('PATCH', $this->imageUrl($product, $image), ['alt_text' => null], $headers)
            ->assertOk()
            ->assertJsonPath('data.alt_text', null);
        $this->json('PATCH', $this->imageUrl($product, $image), ['alt_text' => '   '], $headers)
            ->assertOk()
            ->assertJsonPath('data.alt_text', null);

        $stored = $image->fresh();
        $this->assertSame(4, $stored->sort_order);
        $this->assertTrue($stored->is_primary);
        $this->assertSame($image->file_path, $stored->file_path);
    }

    public function test_alt_text_rejects_invalid_values_and_unrelated_fields(): void
    {
        $product = Product::factory()->create();
        $image = $this->createImage($product, ['alt_text' => 'Original', 'sort_order' => 3]);
        $headers = $this->operator();
        $url = $this->imageUrl($product, $image);

        $this->json('PATCH', $url, ['alt_text' => str_repeat('a', 500)], $headers)->assertOk();
        $this->json('PATCH', $url, ['alt_text' => str_repeat('a', 501)], $headers)->assertUnprocessable();
        $this->json('PATCH', $url, ['alt_text' => 42], $headers)->assertUnprocessable();
        $this->json('PATCH', $url, ['alt_text' => true], $headers)->assertUnprocessable();
        $this->json('PATCH', $url, [], $headers)->assertUnprocessable();
        $this->json('PATCH', $url, ['alt_text' => 'ok', 'sort_order' => 9], $headers)->assertUnprocessable();
        $this->json('PATCH', $url, ['alt_text' => 'ok', 'is_primary' => false], $headers)->assertUnprocessable();
        $this->json('PATCH', $url, ['alt_text' => 'ok', 'url' => 'https://evil.test/x.png'], $headers)->assertUnprocessable();
        $this->json('PATCH', $url, ['alt_text' => 'ok', 'file_path' => 'products/steal.png'], $headers)->assertUnprocessable();

        $stored = $image->fresh();
        $this->assertSame(3, $stored->sort_order);
        $this->assertFalse($stored->is_primary);
        $this->assertSame($image->file_path, $stored->file_path);
    }

    public function test_alt_text_rejects_cross_product_access(): void
    {
        $owner = Product::factory()->create();
        $foreign = Product::factory()->create();
        $image = $this->createImage($owner, ['alt_text' => 'Original']);

        $this->json('PATCH', $this->imageUrl($foreign, $image), ['alt_text' => 'Hijacked'], $this->operator())
            ->assertNotFound();

        $this->assertSame('Original', $image->fresh()->alt_text);
    }

    public function test_selecting_a_primary_demotes_the_previous_primary(): void
    {
        $product = Product::factory()->create();
        $previous = $this->createImage($product, ['sort_order' => 1, 'is_primary' => true]);
        $target = $this->createImage($product, ['sort_order' => 2]);

        $response = $this->json('POST', $this->imageUrl($product, $target, '/primary'), [], $this->operator())->assertOk();

        $response->assertJsonPath('data.is_primary', true);
        $response->assertJsonPath('data.id', ProductImageIdentifier::encode($target));
        $this->assertSinglePrimary($product, (int) $target->getKey());
        $this->assertFalse($previous->fresh()->is_primary);
        $this->assertSame(2, $target->fresh()->sort_order);
    }

    public function test_selecting_the_current_primary_is_idempotent(): void
    {
        $product = Product::factory()->create();
        $primary = $this->createImage($product, ['sort_order' => 1, 'is_primary' => true]);
        $headers = $this->operator();

        $this->json('POST', $this->imageUrl($product, $primary, '/primary'), [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);
        $this->json('POST', $this->imageUrl($product, $primary, '/primary'), [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this->assertSinglePrimary($product, (int) $primary->getKey());
        $this->assertSame(1, $primary->fresh()->sort_order);
    }

    public function test_primary_selection_rejects_cross_product_targets_unknown_ids_and_bodies(): void
    {
        $owner = Product::factory()->create();
        $foreign = Product::factory()->create();
        $image = $this->createImage($owner, ['is_primary' => true]);
        $headers = $this->operator();

        $this->json('POST', $this->imageUrl($foreign, $image, '/primary'), [], $headers)->assertNotFound();
        $this->json('POST', $this->productUrl($owner, '/img_zzzzzzzzzz/primary'), [], $headers)->assertNotFound();
        $this->json('POST', $this->productUrl($owner, '/broken/primary'), [], $headers)->assertNotFound();
        $this->json('POST', $this->imageUrl($owner, $image, '/primary'), ['is_primary' => false], $headers)
            ->assertUnprocessable();
        $this->json('POST', $this->imageUrl($owner, $image, '/primary'), ['sort_order' => 1], $headers)
            ->assertUnprocessable();

        $this->assertSinglePrimary($owner, (int) $image->getKey());
        $this->assertSame(0, $image->fresh()->sort_order);
    }

    public function test_reorder_persists_the_complete_submitted_order(): void
    {
        $product = Product::factory()->create();
        $first = $this->createImage($product, ['sort_order' => 1, 'alt_text' => 'first']);
        $second = $this->createImage($product, ['sort_order' => 2, 'is_primary' => true, 'alt_text' => 'second']);
        $third = $this->createImage($product, ['sort_order' => 3, 'alt_text' => 'third']);

        $response = $this->json('POST', $this->productUrl($product, '/reorder'), [
            'images' => [
                ProductImageIdentifier::encode($third),
                ProductImageIdentifier::encode($first),
                ProductImageIdentifier::encode($second),
            ],
        ], $this->operator())->assertOk();

        $this->assertSame(
            [$third->getKey(), $first->getKey(), $second->getKey()],
            array_map(
                fn (array $image): int => ProductImageIdentifier::decode($image['id']),
                $response->json('data'),
            ),
        );

        $persisted = $this->orderedImages($product);
        $this->assertSame([$third->getKey(), $first->getKey(), $second->getKey()], $persisted->pluck('id')->all());
        $this->assertSame([1, 2, 3], $persisted->pluck('sort_order')->all());
        $this->assertSame(['third', 'first', 'second'], $persisted->pluck('alt_text')->all());
        $this->assertSinglePrimary($product, (int) $second->getKey());
        $this->assertSame($third->file_path, $persisted->first()->file_path);
    }

    public function test_reorder_normalizes_legacy_zero_based_positions_to_contiguous_values(): void
    {
        $product = Product::factory()->create();
        $first = $this->createImage($product, ['sort_order' => 0]);
        $second = $this->createImage($product, ['sort_order' => 0]);
        $third = $this->createImage($product, ['sort_order' => 0]);

        $this->json('POST', $this->productUrl($product, '/reorder'), [
            'images' => [
                ProductImageIdentifier::encode($second),
                ProductImageIdentifier::encode($third),
                ProductImageIdentifier::encode($first),
            ],
        ], $this->operator())->assertOk();

        $this->assertSame([1, 2, 3], $this->orderedImages($product)->pluck('sort_order')->all());
        $this->assertSame(
            [$second->getKey(), $third->getKey(), $first->getKey()],
            $this->orderedImages($product)->pluck('id')->all(),
        );
    }

    public function test_reorder_supports_empty_and_single_image_collections(): void
    {
        $headers = $this->operator();
        $empty = Product::factory()->create();

        $this->json('POST', $this->productUrl($empty, '/reorder'), ['images' => []], $headers)
            ->assertOk()
            ->assertJsonPath('data', []);

        $single = Product::factory()->create();
        $image = $this->createImage($single);

        $this->json('POST', $this->productUrl($single, '/reorder'), ['images' => [ProductImageIdentifier::encode($image)]], $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertSame(1, $image->fresh()->sort_order);

        $this->json('POST', $this->productUrl($single, '/reorder'), ['images' => []], $headers)->assertUnprocessable();
        $this->assertSame(1, $image->fresh()->sort_order);
    }

    public function test_reorder_rejects_duplicate_foreign_unknown_and_incomplete_lists(): void
    {
        $product = Product::factory()->create();
        $first = $this->createImage($product, ['sort_order' => 1]);
        $second = $this->createImage($product, ['sort_order' => 2]);
        $foreign = $this->createImage(Product::factory()->create());
        $headers = $this->operator();
        $url = $this->productUrl($product, '/reorder');

        $firstId = ProductImageIdentifier::encode($first);
        $secondId = ProductImageIdentifier::encode($second);

        $this->json('POST', $url, ['images' => [$firstId, $firstId]], $headers)->assertUnprocessable();
        $this->json('POST', $url, ['images' => [$firstId, ProductImageIdentifier::encode($foreign)]], $headers)->assertUnprocessable();
        $this->json('POST', $url, ['images' => [$firstId]], $headers)->assertUnprocessable();
        $this->json('POST', $url, ['images' => [$firstId, $secondId, 'img_zzzzzzzzzz']], $headers)->assertUnprocessable();
        $this->json('POST', $url, ['images' => [$firstId, 'not-an-identifier']], $headers)->assertUnprocessable();
        $this->json('POST', $url, ['images' => $firstId], $headers)->assertUnprocessable();
        $this->json('POST', $url, ['images' => [[ProductImageIdentifier::encode($first)]]], $headers)->assertUnprocessable();
        $this->json('POST', $url, ['images' => [$firstId, $secondId], 'sort_order' => 1], $headers)->assertUnprocessable();
        $this->json('POST', $url, [], $headers)->assertUnprocessable();
        $this->json('POST', self::PRODUCTS.'prod_zzzzzzzz/images/reorder', ['images' => []], $headers)->assertNotFound();

        $this->assertSame([1, 2], $this->orderedImages($product)->pluck('sort_order')->all());
        $this->assertDatabaseHas('product_images', ['id' => $foreign->getKey()]);
    }

    public function test_every_media_mutation_rejects_unauthenticated_and_unauthorized_callers(): void
    {
        $product = Product::factory()->create();
        $image = $this->createImage($product);
        $other = $this->createImage($product, ['sort_order' => 2]);

        $cases = [
            ['DELETE', $this->imageUrl($product, $image), []],
            ['PATCH', $this->imageUrl($product, $image), ['alt_text' => 'x']],
            ['POST', $this->imageUrl($product, $image, '/primary'), []],
            ['POST', $this->productUrl($product, '/reorder'), ['images' => [
                ProductImageIdentifier::encode($image),
                ProductImageIdentifier::encode($other),
            ]]],
        ];

        // Anonymous checks run first: authenticateAs installs a token-verifier
        // mock that answers any verify() call, so a later "anonymous" request
        // would be authenticated by that mock rather than rejected.
        foreach ($cases as [$method, $url, $payload]) {
            $this->json($method, $url, $payload)->assertUnauthorized();
        }

        foreach ($cases as [$method, $url, $payload]) {
            $customer = $this->identity(User::factory()->customer(), 'customer');
            $this->json($method, $url, $payload, $customer)->assertForbidden();

            $staff = $this->identity(User::factory()->staff(), 'staff');
            Role::findByName('STAFF')->revokePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
            $this->json($method, $url, $payload, $staff)->assertForbidden();
            Role::findByName('STAFF')->givePermissionTo(PermissionName::PRODUCTS_MANAGE->value);

            $admin = $this->identity(User::factory()->admin(), 'admin');
            Role::findByName('ADMIN')->revokePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
            $this->json($method, $url, $payload, $admin)->assertForbidden();
            Role::findByName('ADMIN')->givePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        }

        $this->assertSame([0, 2], $this->orderedImages($product)->pluck('sort_order')->all());
        $this->assertDatabaseHas('product_images', ['id' => $image->getKey()]);
    }

    public function test_operational_staff_with_permission_can_perform_every_mutation(): void
    {
        $product = Product::factory()->create();
        $first = $this->createImage($product, ['sort_order' => 1]);
        $second = $this->createImage($product, ['sort_order' => 2]);
        $third = $this->createImage($product, ['sort_order' => 3]);
        $headers = $this->operator();

        $this->json('PATCH', $this->imageUrl($product, $first), ['alt_text' => 'renamed'], $headers)->assertOk();
        $this->json('POST', $this->imageUrl($product, $second, '/primary'), [], $headers)->assertOk();
        $this->json('POST', $this->productUrl($product, '/reorder'), ['images' => [
            ProductImageIdentifier::encode($third),
            ProductImageIdentifier::encode($second),
            ProductImageIdentifier::encode($first),
        ]], $headers)->assertOk();
        $this->json('DELETE', $this->imageUrl($product, $first), [], $headers)->assertNoContent();

        $remaining = $this->orderedImages($product);
        $this->assertSame([$third->getKey(), $second->getKey()], $remaining->pluck('id')->all());
        $this->assertSame([1, 2], $remaining->pluck('sort_order')->all());
        $this->assertSinglePrimary($product, (int) $second->getKey());
    }

    public function test_retry_job_removes_the_recorded_object_and_reports_a_permanent_failure(): void
    {
        $job = new DeleteProductImageObject('product_images', 'products/prod_x/img_y.png');
        Storage::fake('product_images');
        Storage::disk('product_images')->put('products/prod_x/img_y.png', 'bytes');

        $job->handle();
        Storage::disk('product_images')->assertMissing('products/prod_x/img_y.png');

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('product_images', $disk);

        $this->expectException(RuntimeException::class);
        $job->handle();
    }

    /** @return array<string, string> */
    private function operator(): array
    {
        return $this->identity(User::factory()->staff(), 'operator');
    }

    /** @return array<string, string> */
    private function identity(Factory $factory, string $label): array
    {
        return $this->authenticateAs($factory->create(['clerk_user_id' => $label.'_'.Str::lower(Str::random(12))]));
    }

    /** @param array<string, mixed> $attributes */
    private function createImage(Product $product, array $attributes = []): ProductImage
    {
        /** @var ProductImage $image */
        $image = ProductImage::factory()->for($product)->create($attributes);
        Storage::disk('product_images')->put($image->file_path, 'image-bytes');

        return $image;
    }

    /** @return Collection<int, ProductImage> */
    private function orderedImages(Product $product): Collection
    {
        return ProductImage::query()
            ->where('product_id', $product->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function assertSinglePrimary(Product $product, ?int $expectedImageId): void
    {
        $primaries = ProductImage::query()
            ->where('product_id', $product->getKey())
            ->where('is_primary', true)
            ->pluck('id')
            ->all();

        $this->assertLessThanOrEqual(1, count($primaries));

        if ($expectedImageId !== null) {
            $this->assertSame([$expectedImageId], $primaries);
        }
    }

    private function productUrl(Product $product, string $suffix = ''): string
    {
        return self::PRODUCTS.ProductIdentifier::encode($product).'/images'.$suffix;
    }

    private function imageUrl(Product $product, ProductImage $image, string $suffix = ''): string
    {
        return $this->productUrl($product, '/'.ProductImageIdentifier::encode($image).$suffix);
    }
}
