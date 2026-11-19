<?php

namespace App\Http\Controllers\Guest;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Guest\Concerns\ResolvesEvent;
use App\Jobs\ProcessPhoto;
use App\Models\Download;
use App\Models\Event;
use App\Models\Folder;
use App\Models\Order;
use App\Models\Photo;
use App\Services\Guests\GuestSession;
use App\Services\Orders\Cart;
use App\Services\Orders\OrderPayments;
use App\Services\Payments\PaymentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/** Achat des photos en version originale : panier, paiement, téléchargements payés. */
class OrderController extends Controller
{
    use ResolvesEvent;

    public function __construct(
        private readonly GuestSession $guests,
        private readonly Cart $cart,
        private readonly OrderPayments $orders,
    ) {}

    public function add(string $slug, string $photoId): RedirectResponse
    {
        $event = $this->accessibleEvent($slug, $this->guests);
        $photo = $this->eventPhoto($event, $photoId);
        abort_unless($event->priceFor($photo) > 0, 404);

        $this->cart->add($event, $photo);

        return back()->with('status', __('Photo ajoutée au panier.'));
    }

    public function remove(string $slug, string $photoId): RedirectResponse
    {
        $event = $this->accessibleEvent($slug, $this->guests);
        $this->cart->remove($event, $photoId);

        return back()->with('status', __('Photo retirée du panier.'));
    }

    /** Récapitulatif avant paiement : panier, forfait (?pack=1) ou dossier (?folder=lien). */
    public function checkout(Request $request, string $slug): View|RedirectResponse
    {
        $event = $this->accessibleEvent($slug, $this->guests);
        [$photos, $pack, $folder] = $this->selection($request, $event);

        if ($photos->isEmpty()) {
            return redirect()->route('event.show', $event->slug)->with('status', __('Votre panier est vide.'));
        }

        return view('guest.checkout', [
            'event' => $event,
            'photos' => $photos,
            'pack' => $pack,
            'folder' => $folder,
            'total' => $pack ? $event->packOffer() : $photos->sum(fn (Photo $photo) => $event->priceFor($photo)),
            'demo' => app(PaymentManager::class)->simulationAllowed(),
        ]);
    }

    public function store(Request $request, string $slug, PaymentManager $payments): RedirectResponse|View
    {
        $event = $this->accessibleEvent($slug, $this->guests);
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+ ().-]{8,30}$/'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'pack' => ['nullable', 'boolean'],
            'folder' => ['nullable', 'string', 'max:64'],
        ]);

        [$photos, $pack] = $this->selection($request, $event);
        abort_if($photos->isEmpty(), 422);

        $order = $this->orders->create($event, $photos, $data, $this->guests->ensure(), $pack);

        if (! $pack && ! $request->filled('folder')) {
            $this->cart->clear($event);
        }

        return $this->startPayment($order, $payments);
    }

    public function show(string $token): View
    {
        $order = $this->order($token);

        return view('guest.order', [
            'order' => $order,
            'event' => $order->event,
            'photos' => $order->isPaid() ? $this->orders->downloadablePhotos($order) : $order->photos()->get(),
        ]);
    }

    /** Retour depuis la page de paiement : le statut est vérifié auprès du prestataire. */
    public function return(Request $request, string $token): RedirectResponse
    {
        $order = $this->order($token);

        try {
            $result = $this->orders->confirm($order, $request);
        } catch (Throwable $e) {
            Log::warning('Vérification du paiement impossible', ['order' => $order->reference, 'error' => $e->getMessage()]);

            return redirect()->route('order.show', $token)
                ->with('status', __('Paiement en cours de vérification. Actualisez cette page dans un instant.'));
        }

        return redirect()->route('order.show', $token)->with('status', match (true) {
            $result->isPaid() => __('Paiement confirmé. Merci !'),
            $result->isFailed() => __('Le paiement n\'a pas abouti. Vous pouvez réessayer.'),
            default => __('Paiement en cours de vérification. Actualisez cette page dans un instant.'),
        });
    }

    /** Relance le paiement d'une commande non aboutie. */
    public function retry(string $token, PaymentManager $payments): RedirectResponse|View
    {
        $order = $this->order($token);
        abort_if($order->isPaid() || $order->event->isExpired(), 404);

        $order->forceFill(['status' => OrderStatus::Pending, 'gateway' => $payments->default()->name(), 'gateway_reference' => null])->save();

        return $this->startPayment($order, $payments);
    }

    private function startPayment(Order $order, PaymentManager $payments): RedirectResponse|View
    {
        try {
            return $payments->driver($order->gateway)->start($order);
        } catch (Throwable $e) {
            Log::error('Paiement impossible à démarrer', ['order' => $order->reference, 'error' => $e->getMessage()]);
            $order->markFailed();

            return redirect()->route('order.show', $order->access_token)
                ->withErrors(['payment' => __('Le paiement n\'a pas pu démarrer. Réessayez dans un instant.')]);
        }
    }

    public function download(string $token, string $photoId): StreamedResponse
    {
        $order = $this->paidOrder($token);
        $photo = $this->orders->downloadablePhotos($order)->firstWhere('id', $photoId) ?? abort(404);

        $this->log($order, 'hd', 1);

        return Storage::disk(Photo::disk())->download($this->hdPath($photo), $this->fileName($order->event, $photo));
    }

    public function zip(string $token): StreamedResponse
    {
        $order = $this->paidOrder($token);
        $photos = $this->orders->downloadablePhotos($order);
        abort_if($photos->isEmpty(), 404);

        $this->log($order, 'hd-zip', $photos->count());
        $paths = $photos->mapWithKeys(fn (Photo $photo) => [$this->fileName($order->event, $photo) => $this->hdPath($photo)]);

        return response()->streamDownload(function () use ($paths) {
            $zip = new ZipStream(sendHttpHeaders: false, defaultCompressionMethod: CompressionMethod::STORE, defaultEnableZeroHeader: true);
            $disk = Storage::disk(Photo::disk());

            foreach ($paths as $name => $path) {
                if ($stream = $disk->readStream($path)) {
                    $zip->addFileFromStream($name, $stream);
                    fclose($stream);
                }
            }

            $zip->finish();
        }, Str::slug($order->event->title).'-originaux.zip', ['Content-Type' => 'application/zip']);
    }

    /** Page de paiement simulé (démonstration locale uniquement). */
    public function simulate(string $token, PaymentManager $payments): View
    {
        abort_unless($payments->simulationAllowed(), 404);
        $order = $this->order($token);
        abort_if($order->isPaid(), 404);

        return view('payments.simulation', ['order' => $order]);
    }

    public function simulateSubmit(Request $request, string $token, PaymentManager $payments): RedirectResponse
    {
        abort_unless($payments->simulationAllowed(), 404);

        return redirect()->route('order.return', [
            'token' => $token,
            'simulation' => $request->input('result') === 'paid' ? 'paid' : 'failed',
        ]);
    }

    /**
     * @return array{0: Collection<int, Photo>, 1: bool, 2: Folder|null}
     */
    private function selection(Request $request, Event $event): array
    {
        if ($request->boolean('pack') && $event->packOffer()) {
            return [$this->galleryQuery($event)->get(), true, null];
        }

        if ($token = $request->input('folder')) {
            $folder = Folder::where('access_token', $token)->where('event_id', $event->id)->firstOrFail();
            $owned = $this->orders->purchasedPhotoIds($event, $this->guests->current());
            $photos = $folder->keptPhotos()->whereNotNull('thumb_path')->get()
                ->filter(fn (Photo $photo) => $event->priceFor($photo) > 0 && ! $owned->owns($photo))
                ->values();

            return [$photos, false, $folder];
        }

        return [$this->cart->photos($event), false, null];
    }

    private function order(string $token): Order
    {
        $order = Order::where('access_token', $token)->with('event')->firstOrFail();
        abort_if($order->event->isExpired(), 410);

        return $order;
    }

    private function paidOrder(string $token): Order
    {
        $order = $this->order($token);
        abort_unless($order->isPaid(), 403);

        return $order;
    }

    /** Version originale ; refaite à la volée pour les photos envoyées avant son introduction. */
    private function hdPath(Photo $photo): string
    {
        if (! $photo->hd_path) {
            ProcessPhoto::dispatchSync($photo, indexFaces: false);
            $photo->refresh();
        }

        return $photo->hd_path ?? abort(404);
    }

    private function fileName(Event $event, Photo $photo): string
    {
        return Str::slug($event->title).'-'.strtolower(substr($photo->id, -8)).'-original.jpg';
    }

    private function log(Order $order, string $type, int $count): void
    {
        Download::create([
            'event_id' => $order->event_id,
            'guest_id' => $this->guests->current()?->id,
            'type' => $type,
            'photos_count' => $count,
        ]);
    }
}
