<?php

namespace Tests\Unit;

use App\Services\Storage\ImageVariantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageVariantServiceTest extends TestCase
{
    public function test_it_falls_back_without_raster_processing_when_pixel_budget_is_exceeded(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        config()->set('media.disk', 'public');
        config()->set('media.private_disk', 'local');
        config()->set('media.post_image_allowed_mimes', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
        config()->set('media.post_image_processing_max_pixels', 1000);

        $fixturePath = base_path('tests/Fixtures/images/large-sample.jpg');
        $this->assertFileExists($fixturePath);

        $uploadedFile = new UploadedFile(
            path: $fixturePath,
            originalName: 'large-sample.jpg',
            mimeType: 'image/jpeg',
            test: true
        );

        $result = app(ImageVariantService::class)->storePostImageVariants(
            uploadedFile: $uploadedFile,
            postId: 101,
            mediaId: 101,
            userId: 5
        );

        $this->assertFalse((bool) data_get($result, 'variants_json.processed'));
        $this->assertSame('image/jpeg', (string) ($result['web_mime'] ?? ''));
        $this->assertSame(2400, (int) ($result['width'] ?? 0));
        $this->assertSame(1600, (int) ($result['height'] ?? 0));
        $this->assertTrue(Storage::disk('local')->exists((string) $result['original_path']));
        $this->assertTrue(Storage::disk('public')->exists((string) $result['web_path']));
    }
}

