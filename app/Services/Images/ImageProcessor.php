<?php

namespace App\Services\Images;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

/**
 * Prépare les versions d'une photo : miniature et aperçu (WebP, sans métadonnées EXIF)
 * et version téléchargeable (JPEG), filigranées si demandé.
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
    public function process(string $bytes, ?string $watermark = null): array
    {
        $image = $this->manager->decodeBinary($bytes); // orientation EXIF appliquée automatiquement
        $width = $image->width();
        $height = $image->height();
        $config = config('trouvmoi.images');

        $thumb = (clone $image)->scaleDown($config['thumb_size'], $config['thumb_size']);
        $preview = (clone $image)->scaleDown($config['preview_size'], $config['preview_size']);
        $download = (clone $image)->scaleDown(3000, 3000);

        if ($watermark) {
            $this->watermark($preview, $watermark);
            $this->watermark($download, $watermark);
        }

        return [
            'width' => $width,
            'height' => $height,
            'thumb' => $thumb->encode(new WebpEncoder($config['webp_quality'], strip: true))->toString(),
            'preview' => $preview->encode(new WebpEncoder($config['webp_quality'], strip: true))->toString(),
            'download' => $download->encode(new JpegEncoder(88, progressive: true, strip: true))->toString(),
        ];
    }

    /** Texte discret en bas à droite, taille proportionnelle à l'image. */
    private function watermark(ImageInterface $image, string $text): void
    {
        $size = max(14, (int) round(min($image->width(), $image->height()) * 0.035));
        $margin = (int) round($size * 0.9);

        $shadow = max(1, (int) round($size / 12));
        $x = $image->width() - $margin;
        $y = $image->height() - $margin;

        // Ombre légère puis texte blanc : lisible sur fond clair comme sombre.
        foreach ([[$shadow, 'rgba(0, 0, 0, 0.35)'], [0, 'rgba(255, 255, 255, 0.78)']] as [$offset, $color]) {
            $image->text($text, $x + $offset, $y + $offset, function (FontFactory $font) use ($size, $color) {
                $font->filename(resource_path('fonts/Poppins-SemiBold.ttf'));
                $font->size($size);
                $font->color($color);
                $font->align('right', 'bottom');
            });
        }
    }
}
