@php($legal = config('trouvmoi.legal'))
<x-layouts.guest title="Politique de confidentialité">
    <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
        <h1 class="text-2xl font-bold text-indigo-brand">Politique de confidentialité</h1>
        <p class="mt-1 text-sm text-slate-500">Dernière mise à jour : {{ \Illuminate\Support\Carbon::parse($legal['updated_at'])->translatedFormat('j F Y') }}</p>
        <p class="mt-4 text-sm leading-relaxed text-slate-700">
            {{ config('app.name') }} permet aux photographes de publier les photos d'un événement et à chaque invité
            de retrouver les photos où il apparaît grâce à la reconnaissance faciale. Cette page explique quelles
            données nous traitons, pourquoi, combien de temps, et comment exercer vos droits.
        </p>

        <x-legal-section title="1. Responsable du traitement">
            <p>{{ $legal['company'] }}, {{ $legal['address'] }}. Contact : <a class="text-orange-brand underline" href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>.</p>
            <p>Le traitement est déclaré auprès de l'Autorité de protection des données à caractère personnel du Bénin (APDP)@if ($legal['apdp_reference']) sous la référence {{ $legal['apdp_reference'] }}@endif, conformément au Livre V de la loi n° 2017-20 portant Code du numérique.</p>
            <p>Pour chaque événement, le photographe qui publie les photos est responsable de leur prise de vue et de l'information des participants.</p>
        </x-legal-section>

        <x-legal-section title="2. Données traitées">
            <ul>
                <li><strong>Photographes</strong> : nom, adresse e-mail, mot de passe (chiffré), téléphone et nom commercial facultatifs, forfait.</li>
                <li><strong>Photos des événements</strong> envoyées par les photographes. Les métadonnées (dont la localisation GPS) sont retirées des versions affichées et téléchargées.</li>
                <li><strong>Empreintes faciales</strong> : pour chaque visage détecté sur les photos d'un événement, une suite de nombres qui permet de comparer des visages. Ce n'est pas une image et elle ne permet pas de reconstituer le visage.</li>
                <li><strong>Invités</strong> : aucun compte n'est demandé. Un identifiant aléatoire est enregistré dans un cookie pour retrouver vos dossiers. Nous conservons la preuve de votre consentement (date, version de la notice, empreinte non réversible de votre adresse IP).</li>
                <li><strong>Selfie</strong> : s'il est utilisé, il est analysé en mémoire pour la recherche puis effacé immédiatement. Il n'est jamais enregistré.</li>
                <li><strong>Statistiques</strong> : nombre de scans du QR code, de recherches et de téléchargements, sans identifier les personnes.</li>
            </ul>
        </x-legal-section>

        <x-legal-section title="3. Finalités et base légale">
            <ul>
                <li>Permettre à un invité de retrouver ses photos d'un événement : <strong>consentement</strong> de l'invité, recueilli avant toute recherche faciale, et retirable à tout moment.</li>
                <li>Fournir le service aux photographes (compte, envoi des photos, QR code, statistiques, facturation) : <strong>exécution du contrat</strong>.</li>
                <li>Sécurité du service et prévention des abus : <strong>intérêt légitime</strong>.</li>
            </ul>
            <p>Les empreintes faciales ne servent qu'à la recherche au sein d'un seul événement. Elles ne sont jamais comparées entre événements, vendues ni utilisées pour une autre finalité.</p>
        </x-legal-section>

        <x-legal-section title="4. Durées de conservation">
            <ul>
                <li>Empreintes faciales : supprimées automatiquement à l'expiration de l'événement ({{ config('trouvmoi.default_retention_days') }} jours après sa date par défaut, réglable par le photographe).</li>
                <li>Photos : supprimées {{ config('trouvmoi.photos_grace_days') }} jours après l'expiration de l'événement.</li>
                <li>Preuves de consentement : conservées tant que l'événement existe, puis supprimées avec lui.</li>
                <li>Compte photographe : jusqu'à sa fermeture.</li>
            </ul>
        </x-legal-section>

        <x-legal-section title="5. Destinataires et hébergement">
            <p>Les photos d'un événement sont visibles par les personnes qui disposent de son QR code (et de son code PIN le cas échéant), ou seulement par chaque invité pour ses propres photos en mode selfie. Un dossier personnel n'est accessible qu'à qui possède son lien privé.</p>
            <p>La reconnaissance faciale est réalisée sur nos propres serveurs : aucune donnée faciale n'est confiée à un service tiers. Les fichiers peuvent être hébergés par un prestataire de stockage situé hors du Bénin, dans le respect des garanties prévues par le Code du numérique.</p>
        </x-legal-section>

        <x-legal-section title="6. Vos droits">
            <p>Vous disposez d'un droit d'accès, de rectification, d'opposition et d'effacement de vos données, ainsi que du droit de retirer votre consentement à tout moment.</p>
            <ul>
                <li>Sur chaque photo, le lien « Demander le retrait de cette photo » prévient le photographe, qui la retire sous 72 heures.</li>
                <li>Depuis votre dossier, vous pouvez retirer une photo ou supprimer le dossier.</li>
                <li>Pour toute autre demande : <a class="text-orange-brand underline" href="mailto:{{ $legal['email'] }}">{{ $legal['email'] }}</a>.</li>
            </ul>
            <p>Vous pouvez également saisir l'APDP (apdp.bj).</p>
        </x-legal-section>

        <x-legal-section title="7. Mineurs">
            <p>Pour un événement accueillant des mineurs, le photographe peut désactiver la reconnaissance faciale : aucune empreinte n'est alors calculée et la galerie se consulte sans recherche par visage.</p>
        </x-legal-section>

        <x-legal-section title="8. Cookies">
            <ul>
                <li>Cookie de session et protection contre la falsification de requêtes (nécessaires au fonctionnement).</li>
                <li>Cookie invité « tm_guest » (1 an) : identifiant aléatoire pour retrouver vos dossiers.</li>
                <li>Aucun cookie publicitaire ni de mesure d'audience tierce.</li>
            </ul>
        </x-legal-section>
    </article>
</x-layouts.guest>
