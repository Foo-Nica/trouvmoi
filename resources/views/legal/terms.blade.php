@php($legal = config('trouvmoi.legal'))
<x-layouts.guest title="Conditions d'utilisation">
    <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <h1 class="text-2xl font-bold text-indigo-brand">Conditions générales d'utilisation</h1>
        <p class="mt-1 text-sm text-slate-500">Dernière mise à jour : {{ \Illuminate\Support\Carbon::parse($legal['updated_at'])->translatedFormat('j F Y') }}</p>

        <x-legal-section title="1. Objet">
            <p>{{ config('app.name') }}, édité par {{ $legal['company'] }}, est une plateforme qui permet aux photographes de publier les photos d'un événement et aux invités de retrouver les leurs. L'utilisation du service vaut acceptation des présentes conditions et de la <a class="text-orange-brand underline" href="{{ route('legal.privacy') }}">politique de confidentialité</a>.</p>
        </x-legal-section>

        <x-legal-section title="2. Invités">
            <ul>
                <li>L'accès aux photos d'un événement est gratuit et se fait par le QR code fourni par l'organisateur.</li>
                <li>La recherche par visage n'est possible qu'après acceptation de la notice de consentement.</li>
                <li>Les photos sont réservées à un usage personnel. Toute réutilisation commerciale nécessite l'accord du photographe.</li>
                <li>Il est interdit de rechercher ou de télécharger les photos d'une autre personne sans son accord.</li>
            </ul>
        </x-legal-section>

        <x-legal-section title="3. Photographes">
            <ul>
                <li>Le photographe crée un compte avec des informations exactes et garde son mot de passe confidentiel.</li>
                <li>Il garantit détenir les droits sur les photos publiées et avoir informé les participants de l'événement (par exemple avec l'affiche générée avec le QR code).</li>
                <li>Il active l'option « Événement avec des mineurs » lorsque des mineurs sont photographiés.</li>
                <li>Il traite les demandes de retrait des invités sous 72 heures.</li>
                <li>Il s'interdit de publier des contenus illicites, contraires aux bonnes mœurs ou portant atteinte à la vie privée ou à l'image d'autrui.</li>
            </ul>
        </x-legal-section>

        <x-legal-section title="4. Forfaits et paiement">
            <p>Le service est proposé aux photographes sous forme d'abonnement mensuel, sans engagement :</p>
            <ul>
                @foreach (config('trouvmoi.plans') as $plan)
                    <li>{{ $plan['label'] }} : {{ number_format($plan['price_xof'], 0, ',', ' ') }} FCFA par mois, {{ $plan['events_per_month'] ? $plan['events_per_month'].' événement(s) par mois' : 'événements illimités' }}, {{ number_format($plan['photos_per_event'], 0, ',', ' ') }} photos par événement.</li>
                @endforeach
            </ul>
            <p>Un forfait non renouvelé limite l'accès aux fonctions payantes à la fin de la période payée. Les photos restent soumises aux durées de conservation de la politique de confidentialité.</p>
        </x-legal-section>

        <x-legal-section title="5. Propriété intellectuelle">
            <p>Les photos restent la propriété de leurs auteurs. Le photographe accorde à {{ $legal['company'] }} le seul droit de les stocker, de les traiter (miniatures, filigrane, reconnaissance faciale) et de les afficher aux invités pour la durée de conservation de l'événement.</p>
        </x-legal-section>

        <x-legal-section title="6. Responsabilité">
            <p>La reconnaissance faciale peut produire des erreurs : l'invité vérifie les photos proposées avant de créer son dossier. {{ $legal['company'] }} met en œuvre les moyens raisonnables pour assurer la disponibilité et la sécurité du service, sans garantir une disponibilité ininterrompue.</p>
        </x-legal-section>

        <x-legal-section title="7. Suspension">
            <p>Un compte peut être suspendu en cas de manquement aux présentes conditions, notamment en cas de publication de contenus illicites ou de non-traitement des demandes de retrait.</p>
        </x-legal-section>

        <x-legal-section title="8. Droit applicable">
            <p>Les présentes conditions sont soumises au droit béninois. Contact : <a class="text-orange-brand underline" href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>.</p>
        </x-legal-section>
    </article>
</x-layouts.guest>
