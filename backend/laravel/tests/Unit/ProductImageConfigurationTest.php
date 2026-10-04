<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

class ProductImageConfigurationTest extends TestCase
{
    public function test_non_local_environment_requires_a_valid_public_image_url(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');
        config(['product_images.public_base_url' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Product image public delivery is not configured.');

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_non_local_environment_accepts_a_valid_public_image_url(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');
        config(['product_images.public_base_url' => 'https://examplefurnitures.com']);

        (new AppServiceProvider($this->app))->boot();

        $this->addToAssertionCount(1);
    }
}
