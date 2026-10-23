@php
    use App\Actions\Publication\RepondrePublication;
    use App\Enums\ModeCandidature;
    use App\Enums\ModeInscription;
    use App\Enums\SousTypeService;
    use App\Enums\StatutPublication;
    use App\Enums\TypeReponse;

    $p = $publication;
    $typeAccepte = RepondrePublication::typeAccepte($p);
    $libelleAction = match ($typeAccepte) {
        TypeReponse::Candidature => 'Postuler',
        TypeReponse::Inscription => 'S’inscrire',
        TypeReponse::Proposition => 'Je suis disponible',
        TypeReponse::Contact => 'Contacter',
        default => null,
    };
    $estAuteur = auth()->id() === $p->auteur_id;
    $emploi = $p->detailEmploi;
    $service = $p->detailService;
    $formation = $p->detailFormation;
    $lienExterne = ($emploi && $emploi->mode_candidature !== ModeCandidature::Rezohelp && $emploi->contact_candidature)
        || ($formation && $formation->mode_inscription !== ModeInscription::Rezohelp && $formation->contact_inscription);
@endphp

<section id="repondre" class="mt-6 space-y-3 border-t border-slate-100 pt-4" aria-label="Répondre">
    @if ($p->source === \App\Enums\SourcePublication::VeilleIa && $p->source_url)
        @if ($p->statut === StatutPublication::Publiee)
            <x-bouton :href="$p->source_url" target="_blank" rel="noopener nofollow" @class(['w-full sm:w-auto', 'bg-emploi' => $emploi, 'bg-formation' => $formation])>
                @php($surSource = $p->source_nom ? ' sur '.$p->source_nom : '')
                {{ match (true) { (bool) $emploi => 'Postuler'.$surSource, (bool) $formation => 'S’inscrire'.$surSource, default => 'Voir l’annonce'.$surSource } }}
                <span aria-hidden="true">↗</span>
            </x-bouton>
            <p class="text-sm text-slate-600">Annonce repérée par notre veille{{ $p->source_nom ? ' sur '.$p->source_nom : '' }} : ce résumé ne remplace pas l’annonce d’origine, qui s’ouvre dans un nouvel onglet.</p>
        @else
            <p class="text-sm text-slate-600">Cette annonce n’accepte plus de candidatures. <a href="{{ $p->source_url }}" target="_blank" rel="noopener nofollow" class="font-semibold text-brand hover:underline">Voir l’annonce d’origine</a></p>
        @endif
    @elseif ($p->statut !== StatutPublication::Publiee)
        <p class="text-sm text-slate-600">Cette publication n’accepte plus de réponse.</p>
    @elseif ($lienExterne)
        <x-bouton :href="route('publications.contact', $p->slug)" target="_blank" rel="noopener nofollow" @class(['w-full sm:w-auto', 'bg-emploi' => $emploi, 'bg-formation' => $formation])>
            {{ $emploi ? 'Postuler' : 'S’inscrire' }}
            @php($mode = $emploi?->mode_candidature?->value ?? $formation?->mode_inscription?->value)
            <span class="font-normal">({{ match ($mode) { 'email' => 'par e-mail', 'telephone' => 'par téléphone', default => 'sur le site' } }})</span>
        </x-bouton>
    @elseif ($service && $p->sous_type === SousTypeService::Offre && $service->afficher_telephone && $service->telephone_contact)
        <div class="flex flex-wrap gap-2">
            <x-bouton :href="'tel:'.$service->telephone_contact" class="bg-service">Appeler le {{ app(\App\Services\TelephoneService::class)->afficher($service->telephone_contact, auth()->user()?->pays_code) }}</x-bouton>
            @if ($service->whatsapp)
                <x-bouton :href="'https://wa.me/'.ltrim($service->whatsapp, '+')" target="_blank" rel="noopener" variante="secondaire">WhatsApp</x-bouton>
            @endif
        </div>
    @elseif ($typeAccepte !== null)
        @guest
            <x-bouton :href="route('login')" class="w-full sm:w-auto">{{ $libelleAction }}</x-bouton>
            <p class="text-sm text-slate-600">Connectez-vous ou créez un compte gratuit pour répondre.</p>
        @else
            @if ($estAuteur)
                <p class="text-sm text-slate-700">Vous êtes l’auteur de cette publication.
                    <a href="{{ route('espace.reponses.index', $p->id) }}" class="font-semibold text-brand hover:underline">Voir les réponses</a></p>
            @elseif ($dejaRepondu)
                <x-message>Vous avez déjà répondu à cette publication. L’auteur a été prévenu.</x-message>
            @else
                <form method="POST" action="{{ route('publications.repondre', $p->slug) }}" class="space-y-3">
                    @csrf
                    <x-zone-texte name="message" :label="$typeAccepte === TypeReponse::Proposition ? 'Votre message (tarif, disponibilités…)' : 'Votre message (facultatif)'" :max="1000" :lignes="3" />
                    @if (RepondrePublication::telephoneObligatoire($typeAccepte))
                        <p class="text-sm text-slate-600">Votre numéro sera transmis à l’auteur pour qu’il puisse vous joindre.</p>
                    @else
                        <x-case name="partager_telephone" label="Partager mon numéro avec l’auteur" :coche="true" />
                    @endif
                    <x-bouton class="w-full sm:w-auto">{{ $libelleAction }}</x-bouton>
                </form>
            @endif
        @endguest
    @endif
</section>
