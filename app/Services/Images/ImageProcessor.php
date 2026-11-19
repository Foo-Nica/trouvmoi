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
 * Prépare les versions d'une photo, toutes sans métadonnées EXIF :
 * - miniature et aperçu (WebP) et version téléchargeable (JPEG), avec le logo en filigrane si demandé ;
 * - version originale (JPEG pleine qualité) vendue aux invités, avec un logo minuscule et discret.
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
     * @return array{width:int,height:int,thumb:string,preview:string,download:string,hd:string}
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
        $hd = (clone $image)->scaleDown(5000, 5000);
        $this->signature($hd);

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
            'hd' => $hd->encode(new JpegEncoder(94, progressive: true, strip: true))->toString(),
        ];
    }

    /**
     * Logo TRECHART EMPIRE en bas à droite, large de 20 % de l'image, avec une ombre douce
     * pour rester lisible sur les fonds clairs comme sombres.
     */
    private function watermark(ImageInterface $image): void
    {
        $this->insertLogo($image, max(120, (int) round($image->width() * 0.2)), 0.85);
    }

    /** Version originale : logo minuscule (6 % de la largeur), presque transparent. */
    private function signature(ImageInterface $image): void
    {
        $this->insertLogo($image, max(70, (int) round($image->width() * 0.06)), 0.55);
    }

    private function insertLogo(ImageInterface $image, int $width, float $opacity): void
    {
        $margin = (int) round($width * 0.08);
        $logo = $this->manager->decodePath(resource_path('images/watermark.png'))->scale(width: $width);

        $image->insert($logo, $margin, $margin, Alignment::BOTTOM_RIGHT, $opacity);
    }
}
