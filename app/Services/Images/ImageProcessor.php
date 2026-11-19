<?php

namespace App\Services\Images;

use Intervention\Image\Alignment;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Prépare les versions d'une photo : miniature et aperçu (WebP, sans métadonnées EXIF)
 * et version téléchargeable (JPEG), avec le logo en filigrane si demandé.
 *
 * Les photos HEIC nécessitent l'extension Imagick compilée avec libheif.
 */
class ImageProcessor
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class);
    }

    /**
     * @return array{width:int,height:int,thumb:string,preview:string,download:string}
     */
    public function process(string $bytes, bool $watermark = false): array
    {
        $image = $this->manager->decodeBinary($bytes); // orientation EXIF appliquée automatiquement
        $width = $image->width();
        $height = $image->height();
        $config = config('trouvmoi.images');

        $thumb = (clone $image)->scaleDown($config['thumb_size'], $config['thumb_size']);
        $preview = (clone $image)->scaleDown($config['preview_size'], $config['preview_size']);
        $download = (clone $image)->scaleDown(3000, 3000);

        if ($watermark) {
            $this->watermark($preview);
            $this->watermark($download);
        }

        return [
            'width' => $width,
            'height' => $height,
            'thumb' => $thumb->encode(new WebpEncoder($config['webp_quality'], strip: true))->toString(),
            'preview' => $preview->encode(new WebpEncoder($config['webp_quality'], strip: true))->toString(),
            'download' => $download->encode(new JpegEncoder(88, progressive: true, strip: true))->toString(),
        ];
    }

    /**
     * Logo TRECHART EMPIRE en bas à droite, large de 20 % de l'image, avec une ombre douce
     * pour rester lisible sur les fonds clairs comme sombres.
     */
    private function watermark(ImageInterface $image): void
    {
        $width = max(120, (int) round($image->width() * 0.2));
        $margin = (int) round($width * 0.06);

        $logo = $this->manager->decodePath(resource_path('images/watermark.png'))->scale(width: $width);

        $image->insert($logo, $margin, $margin, Alignment::BOTTOM_RIGHT, 0.85);
    }
}
