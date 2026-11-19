<?php

namespace Database\Seeders;

use App\Enums\AccessMode;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\PhotoStatus;
use App\Jobs\ProcessPhoto;
use App\Models\Event;
use App\Models\Face;
use App\Models\Order;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Deux événements de démonstration, avec des photos d'illustration générées :
 * un événement public et une séance pour une personnalité, plus quelques ventes.
 */
class DemoSeeder extends Seeder
{
    public function run(User $owner): void
    {
        $gala = Event::create([
            'user_id' => $owner->id,
            'title' => 'Gala TRECHART 2026',
            'type' => EventType::Public,
            'event_date' => now()->subDays(3),
            'location' => 'Cotonou, Bénin',
            'description' => 'Merci d\'avoir partagé cette soirée avec nous ! Retrouvez vos photos en un geste.',
            'access_mode' => AccessMode::Gallery,
            'status' => EventStatus::Published,
            'watermark_enabled' => true,
            'original_price' => 1500,
        ]);
        $this->photos($gala, 18, seed: 1);

        $shooting = Event::create([
            'user_id' => $owner->id,
            'title' => 'Shooting portrait - Mme Awa Mensah',
            'type' => EventType::Client,
            'client_name' => 'Mme Awa Mensah',
            'event_date' => now()->subDay(),
            'location' => 'Studio TRECHART, Cotonou',
            'description' => 'Votre séance portrait est prête. Choisissez vos photos préférées ou prenez la série complète.',
            'access_mode' => AccessMode::Gallery,
            'status' => EventStatus::Published,
            'original_price' => 3000,
            'pack_price' => 25000,
        ]);
        $portraits = $this->photos($shooting, 12, seed: 7);

        $gala->update(['scans_count' => 146]);
        $shooting->update(['scans_count' => 9]);

        $paid = Order::create([
            'event_id' => $shooting->id,
            'customer_name' => 'Awa Mensah',
            'customer_phone' => '01 97 00 00 00',
            'amount' => 25000,
            'is_pack' => true,
            'gateway' => 'fake',
        ]);
        $paid->items()->createMany($portraits->map(fn (Photo $photo) => ['photo_id' => $photo->id, 'price' => 0])->all());
        $paid->markPaid('SIM-'.$paid->reference);

        $galaPhotos = $gala->photos()->take(3)->get();
        $single = Order::create([
            'event_id' => $gala->id,
            'customer_name' => 'Koffi Agbo',
            'customer_phone' => '01 66 00 00 00',
            'amount' => 4500,
            'gateway' => 'fake',
        ]);
        $single->items()->createMany($galaPhotos->map(fn (Photo $photo) => ['photo_id' => $photo->id, 'price' => 1500])->all());
        $single->markPaid('SIM-'.$single->reference);

        Order::create([
            'event_id' => $gala->id,
            'customer_name' => 'Sènan Houngbo',
            'customer_phone' => '01 61 00 00 00',
            'amount' => 1500,
            'gateway' => 'fake',
        ])->items()->create(['photo_id' => $galaPhotos->last()->id, 'price' => 1500]);
    }

    /** @return Collection<int, Photo> */
    private function photos(Event $event, int $count, int $seed)
    {
        $disk = Storage::disk(Photo::disk());

        return collect(range(1, $count))->map(function (int $i) use ($event, $disk, $seed) {
            [$bytes, $face] = $this->illustration($seed + $i, $i % 3 === 0);
            $path = "events/{$event->id}/originals/demo-{$i}.jpg";
            $disk->put($path, $bytes);

            $photo = Photo::create([
                'event_id' => $event->id,
                'original_name' => "TRECHART-{$i}.jpg",
                'original_path' => $path,
                'size_bytes' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'status' => PhotoStatus::Received,
                'taken_at' => now()->subHours(40 - $i),
            ]);
            ProcessPhoto::dispatchSync($photo, indexFaces: false);
            $event->increment('photos_count');

            // Cadre du visage d'illustration : la visionneuse propose « Touchez votre visage ».
            Face::create($face + ['photo_id' => $photo->id, 'event_id' => $event->id, 'external_id' => "demo-{$photo->id}"]);
            $photo->update(['faces_count' => 1, 'status' => PhotoStatus::Indexed]);
            $event->increment('faces_count');

            return $photo->refresh();
        });
    }

    /**
     * Image d'illustration : fond dégradé chaud et silhouette, à la place d'une vraie photo.
     *
     * @return array{0: string, 1: array{x: int, y: int, w: int, h: int}}
     */
    private function illustration(int $seed, bool $landscape): array
    {
        [$width, $height] = $landscape ? [1800, 1200] : [1200, 1600];
        $image = imagecreatetruecolor($width, $height);
        $palettes = [
            [[237, 139, 0], [51, 51, 51]], [[214, 178, 140], [70, 52, 44]], [[120, 150, 170], [40, 44, 52]],
            [[190, 120, 90], [60, 40, 40]], [[230, 200, 160], [90, 70, 60]], [[160, 110, 150], [45, 40, 60]],
        ];
        [$top, $bottom] = $palettes[$seed % count($palettes)];

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $color = imagecolorallocate($image, ...array_map(fn ($a, $b) => (int) ($a + ($b - $a) * $t), $top, $bottom));
            imageline($image, 0, $y, $width, $y, $color);
        }

        $light = imagecolorallocatealpha($image, 255, 255, 255, 105);
        imagefilledellipse($image, (int) ($width * (0.2 + ($seed % 5) / 8)), (int) ($height * 0.25), (int) ($width * 0.7), (int) ($width * 0.7), $light);

        $skin = [[92, 58, 40], [120, 78, 52], [70, 44, 32]][$seed % 3];
        $cloth = [[30, 30, 30], [237, 139, 0], [240, 236, 228], [32, 60, 110]][$seed % 4];
        $cx = (int) ($width * ($landscape ? 0.35 + ($seed % 3) * 0.15 : 0.5));
        $head = (int) ($height * ($landscape ? 0.2 : 0.17));
        imagefilledellipse($image, $cx, (int) ($height * 1.02), (int) ($head * 3.6), (int) ($height * 0.9), imagecolorallocate($image, ...$cloth));
        imagefilledrectangle($image, $cx - (int) ($head * 0.22), (int) ($height * 0.5), $cx + (int) ($head * 0.22), (int) ($height * 0.62), imagecolorallocate($image, ...$skin));
        imagefilledellipse($image, $cx, (int) ($height * 0.45), $head, (int) ($head * 1.25), imagecolorallocate($image, ...$skin));

        ob_start();
        imagejpeg($image, null, 90);
        $faceHeight = (int) ($head * 1.25);

        return [(string) ob_get_clean(), [
            'x' => $cx - intdiv($head, 2),
            'y' => (int) ($height * 0.45) - intdiv($faceHeight, 2),
            'w' => $head,
            'h' => $faceHeight,
        ]];
    }
}
