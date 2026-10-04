<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Support\PermissionName;
use App\Support\ProductIdentifier;
use Database\Seeders\RbacSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\Support\AuthenticatesApiUser;
use Tests\Support\CreatesAttachmentFiles;
use Tests\TestCase;

class ProductImageApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use CreatesAttachmentFiles;
    use RefreshDatabase;

    private const URL = '/api/v1/products/';

    private const IMAGE_PATH = '/images';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        config(['product_images.disk' => 'product_images', 'product_images.public_base_url' => 'https://examplefurnitures.com']);
        Storage::fake('product_images');
    }

    public function test_authorization_matrix_requires_products_manage(): void
    {
        $product = Product::factory()->create();
        $url = self::URL.ProductIdentifier::encode($product).self::IMAGE_PATH;

        $this->upload($url, ['image' => $this->pngUpload()])->assertUnauthorized();
        $this->upload($url, ['image' => $this->pngUpload()], $this->authenticateAs(User::factory()->customer()->create(['clerk_user_id' => 'image_customer'])))->assertForbidden();

        Role::findByName('STAFF')->revokePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        $this->upload($url, ['image' => $this->pngUpload()], $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'image_staff_denied'])))->assertForbidden();

        Role::findByName('STAFF')->givePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        $this->upload($url, ['image' => $this->pngUpload()], $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'image_staff_allowed'])))->assertCreated();

        Role::findByName('ADMIN')->revokePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        $this->upload($url, ['image' => $this->pngUpload()], $this->authenticateAs(User::factory()->admin()->create(['clerk_user_id' => 'image_admin_denied'])))->assertForbidden();

        Role::findByName('ADMIN')->givePermissionTo(PermissionName::PRODUCTS_MANAGE->value);
        $this->upload($url, ['image' => $this->pngUpload()], $this->authenticateAs(User::factory()->admin()->create(['clerk_user_id' => 'image_admin_allowed'])))->assertCreated();
    }

    public function test_valid_image_types_are_uploaded_without_transformation(): void
    {
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'image_type_staff']));

        foreach (['jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'] as $type => $extension) {
            $product = Product::factory()->create();
            $file = $this->{$type.'Upload'}('client-name.'.$extension);
            $response = $this->upload(self::URL.ProductIdentifier::encode($product).self::IMAGE_PATH, ['image' => $file], $headers)->assertCreated();
            $stored = ProductImage::query()->latest('id')->firstOrFail();

            $this->assertStringEndsWith('.'.$extension, $stored->file_path);
            Storage::disk('product_images')->assertExists($stored->file_path);
            $this->assertSame($file->getContent(), Storage::disk('product_images')->get($stored->file_path));
            $response->assertJsonPath('data.url', 'https://examplefurnitures.com/'.$stored->file_path);
            $this->assertSame(['id', 'url', 'alt_text', 'sort_order', 'is_primary'], array_keys($response->json('data')));
        }
    }

    public function test_invalid_shape_and_size_are_rejected_without_storage_side_effects(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'image_validation_staff']));
        $url = self::URL.ProductIdentifier::encode($product).self::IMAGE_PATH;

        $this->upload($url, ['image' => [$this->pngUpload(), $this->pngUpload()]], $headers)->assertUnprocessable();
        $this->upload($url, ['image' => $this->pngUpload(), 'alt_text' => 'ignored'], $headers)->assertUnprocessable();
        $this->upload($url, ['image' => UploadedFile::fake()->create('large.png', 5121)], $headers)->assertStatus(413);
        $this->upload($url, ['image' => $this->zeroByteUpload()], $headers)->assertUnprocessable();
        $this->upload($url, ['image' => $this->fakeFile('vector.svg', '<svg/>')], $headers)->assertUnprocessable();
        $this->upload($url, ['image' => $this->pdfUpload()], $headers)->assertUnprocessable();
        $this->upload($url, ['image' => $this->fakeFile('photo.jpg', (string) $this->pdfUpload()->getContent())], $headers)->assertUnprocessable();
        $this->upload($url, ['image' => UploadedFile::fake()->createWithContent('photo.png', (string) $this->jpegUpload()->getContent(), 'image/png')], $headers)->assertUnprocessable();

        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([], Storage::disk('product_images')->allFiles());
    }

    public function test_gps_jpeg_and_filename_traversal_are_rejected_or_ignored_safely(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'image_gps_staff']));
        $url = self::URL.ProductIdentifier::encode($product).self::IMAGE_PATH;

        $this->upload($url, ['image' => $this->gpsJpegUpload()], $headers)->assertUnprocessable();
        $this->upload($url, ['image' => $this->pngUpload('../../outside.png')], $headers)->assertCreated();

        $stored = ProductImage::query()->sole();
        $this->assertMatchesRegularExpression('#^products/prod_[0-9a-z]+/img_[0-9a-z]+\.png$#', $stored->file_path);
    }

    public function test_metadata_appends_and_repairs_products_without_a_primary_image(): void
    {
        $product = Product::factory()->create(['name' => 'Walnut Table']);
        ProductImage::factory()->for($product)->create(['sort_order' => 7, 'is_primary' => false]);
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'image_metadata_staff']));
        $url = self::URL.ProductIdentifier::encode($product).self::IMAGE_PATH;

        $this->upload($url, ['image' => $this->pngUpload()], $headers)->assertCreated();
        $first = ProductImage::query()->latest('id')->firstOrFail();
        $this->assertSame(8, $first->sort_order);
        $this->assertTrue($first->is_primary);
        $this->assertSame('Walnut Table', $first->alt_text);
        $this->assertNull($first->product_variant_id);

        $this->upload($url, ['image' => $this->pngUpload()], $headers)->assertCreated();
        $second = ProductImage::query()->latest('id')->firstOrFail();
        $this->assertSame(9, $second->sort_order);
        $this->assertFalse($second->is_primary);
    }

    public function test_operational_draft_and_inactive_products_can_receive_images(): void
    {
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'image_operational_staff']));

        foreach ([Product::factory()->draft()->create(), Product::factory()->inactive()->create()] as $product) {
            $this->upload(self::URL.ProductIdentifier::encode($product).self::IMAGE_PATH, ['image' => $this->pngUpload()], $headers)->assertCreated();
        }
    }

    public function test_storage_failure_creates_no_row_and_database_failure_deletes_uploaded_object(): void
    {
        $product = Product::factory()->create();
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'image_failure_staff']));
        $url = self::URL.ProductIdentifier::encode($product).self::IMAGE_PATH;
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->andThrow(new \RuntimeException('provider unavailable'));
        Storage::set('product_images', $disk);

        $this->upload($url, ['image' => $this->pngUpload()], $headers)->assertStatus(503);
        $this->assertDatabaseCount('product_images', 0);

        Storage::fake('product_images');
        Schema::drop('product_images');
        $this->upload($url, ['image' => $this->pngUpload()], $headers)->assertStatus(500);
        $this->assertSame([], Storage::disk('product_images')->allFiles());
    }

    private function gpsJpegUpload(): UploadedFile
    {
        $jpeg = (string) $this->jpegUpload()->getContent();
        $tiff = "II\x2A\x00\x08\x00\x00\x00\x01\x00\x25\x88\x04\x00\x01\x00\x00\x00\x1A\x00\x00\x00\x00\x00\x00\x00\x01\x00\x02\x00\x05\x00\x03\x00\x00\x00\x2C\x00\x00\x00\x00\x00\x00\x00".str_repeat("\x01\x00\x00\x00\x01\x00\x00\x00", 3);
        $exif = "Exif\x00\x00".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return $this->fakeFile('gps.jpg', substr($jpeg, 0, 2).$segment.substr($jpeg, 2));
    }

    /** @param array<string, mixed> $payload @param array<string, string> $headers */
    private function upload(string $url, array $payload, array $headers = []): TestResponse
    {
        return $this->withHeaders([...$headers, 'Content-Type' => 'multipart/form-data; boundary=----product-image-test'])->post($url, $payload);
    }
}
