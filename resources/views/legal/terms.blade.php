@php($legal = config('trouvmoi.legal'))
<x-layouts.guest title="Conditions d'utilisation">
    <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <h1 class="text-2xl font-bold text-ink">Conditions générales d'utilisation</h1>
        <p class="mt-1 text-sm text-slate-500">Dernière mise à jour : {{ \Illuminate\Support\Carbon::parse($legal['updated_at'])->translatedFormat('j F Y') }}</p>

        <x-legal-section title="1. Objet">
            <p>Ce site, édité par {{ $legal['company'] }}, permet à l'équipe TRECHART de publier les photos des événements qu'elle couvre et aux invités de retrouver les leurs. L'utilisation du service vaut acceptation des présentes conditions et de la <a class="text-accent-strong underline" href="{{ route('legal.privacy') }}">politique de confidentialité</a>.</p>
        </x-legal-section>

        <x-legal-section title="2. Invités">
            <ul>
                <li>L'accès aux photos d'un événement est gratuit et se fait par le QR code fourni par l'organisateur.</li>
                <li>La recherche par visage n'est possible qu'après acceptation de la notice de consentement.</li>
                <li>Les photos sont réservées à un usage personnel. Toute réutilisation commerciale nécessite l'accord écrit de {{ $legal['company'] }}.</li>
                <li>Il est interdit de rechercher ou de télécharger les photos d'une autre personne sans son accord.</li>
            </ul>
        </x-legal-section>

        <x-legal-section title="3. Engagements de TRECHART">
            <ul>
                <li>Les comptes de l'équipe sont créés par l'administrateur ; chaque membre garde son mot de passe confidentiel.</li>
                <li>TRECHART informe les participants de chaque événement (par exemple avec l'affiche portant le QR code).</li>
                <li>La reconnaissance faciale est désactivée pour les événements accueillant des mineurs lorsque cela est demandé.</li>
                <li>Les demandes de retrait des invités sont traitées sous 72 heures.</li>
            </ul>
        </x-legal-section>

        <x-legal-section title="4. Achat des photos">
            <ul>
                <li>Pour un événement public, chaque photo peut être téléchargée gratuitement avec le logo TRECHART. La version originale, sans filigrane, est payante lorsqu'un prix est affiché.</li>
                <li>Pour une séance réalisée pour une personnalité, les photos se téléchargent après paiement, à l'unité ou en forfait, aux prix affichés.</li>
                <li>Les prix sont indiqués en francs CFA (FCFA), toutes taxes comprises. Le paiement est traité par KKiaPay ou FedaPay (Mobile Money, carte bancaire) : {{ $legal['company'] }} ne reçoit jamais vos données de carte.</li>
                <li>Les photos achetées sont livrées dès la confirmation du paiement, par un lien privé permettant de les retélécharger pendant la durée de conservation de l'événement.</li>
            </ul>
        </x-legal-section>

        <x-legal-section title="5. Propriété intellectuelle">
            <p>Les photos et le logo restent la propriété de {{ $legal['company'] }}. Les invités peuvent télécharger et partager les photos où ils apparaissent pour un usage personnel, sans retirer le filigrane.</p>
        </x-legal-section>

        <x-legal-section title="6. Responsabilité">
            <p>La reconnaissance faciale peut produire des erreurs : l'invité vérifie les photos proposées avant de créer son dossier. {{ $legal['company'] }} met en œuvre les moyens raisonnables pour assurer la disponibilité et la sécurité du service, sans garantir une disponibilité ininterrompue.</p>
        </x-legal-section>

        <x-legal-section title="7. Accès au service">
            <p>L'accès à un événement ou à un dossier peut être retiré en cas d'usage abusif, notamment en cas de recherche des photos d'une autre personne sans son accord.</p>
        </x-legal-section>

        <x-legal-section title="8. Droit applicable">
            <p>Les présentes conditions sont soumises au droit béninois. Contact : <a class="text-accent-strong underline" href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>.</p>
        </x-legal-section>
    </article>
</x-layouts.guest>
