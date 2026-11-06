<?php

namespace Tests;

use App\Services\FaceEngine\FaceEngine;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeFaceEngine;

abstract class TestCase extends BaseTestCase
{
    protected FakeFaceEngine $faceEngine;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('trouvmoi.photos_disk'));
        $this->faceEngine = new FakeFaceEngine;
        $this->app->instance(FaceEngine::class, $this->faceEngine);
    }

    /** Une vraie image JPEG (les traitements d'image ne sont pas simulés). */
    protected function jpeg(int $width = 1200, int $height = 800, int $seed = 1): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 40 * $seed % 255, 120, 200));
        ob_start();
        imagejpeg($image, null, 85);

        return ob_get_clean();
    }
}
