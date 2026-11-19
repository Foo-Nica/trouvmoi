<?php

namespace App\Services\Orders;

use App\Models\Event;
use App\Models\Photo;
use Illuminate\Support\Collection;

/** Panier de l'invité, gardé en session, un par événement. */
class Cart
{
    private const MAX_ITEMS = 200;

    /** @return list<string> */
    public function ids(Event $event): array
    {
        return array_values(session()->get($this->key($event), []));
    }

    public function has(Event $event, Photo $photo): bool
    {
        return in_array($photo->id, $this->ids($event), true);
    }

    public function add(Event $event, Photo $photo): void
    {
        $ids = $this->ids($event);
        if (! in_array($photo->id, $ids, true) && count($ids) < self::MAX_ITEMS) {
            $ids[] = $photo->id;
            session()->put($this->key($event), $ids);
        }
    }

    public function remove(Event $event, string $photoId): void
    {
        session()->put($this->key($event), array_values(array_diff($this->ids($event), [$photoId])));
    }

    public function clear(Event $event): void
    {
        session()->forget($this->key($event));
    }

    /** Photos du panier encore visibles et payantes. @return Collection<int, Photo> */
    public function photos(Event $event): Collection
    {
        $ids = $this->ids($event);
        if ($ids === []) {
            return collect();
        }

        return Photo::where('event_id', $event->id)
            ->where('is_visible', true)
            ->whereKey($ids)
            ->get()
            ->filter(fn (Photo $photo) => $event->priceFor($photo) > 0)
            ->values();
    }

    public function total(Event $event): int
    {
        return $this->photos($event)->sum(fn (Photo $photo) => $event->priceFor($photo));
    }

    private function key(Event $event): string
    {
        return "cart.{$event->id}";
    }
}
