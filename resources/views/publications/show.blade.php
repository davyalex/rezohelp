@extends('layouts.public')

@php
    use App\Enums\MotifSignalement;
    use App\Enums\SousTypeService;
    use App\Enums\StatutPublication;
    use App\Enums\TypePublication;
    use App\Support\Montant;

    $p = $publication;
    $referentiels = app(\App\Services\Referentiels::class);
    $url = route('publications.show', $p->slug);
    $emploi = $p->detailEmploi;
    $service = $p->detailService;
    $formation = $p->detailFormation;
    $aime = $etat->aime($p);
    $enregistree = $etat->estEnregistree($p);
@endphp

@section('titre', $p->titre)
@section('description', $p->extrait)

@push('entete')
    <link rel="canonical" href="{{ $url }}">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $p->titre }}">
    <meta property="og:description" content="{{ $p->extrait }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:image" content="{{ $p->image_url ?? asset('images/partage.png') }}">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary_large_image">
    @if ($jsonLd !== null)
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endif
@endpush

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('fil.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Retour au fil</a>

        <article class="mt-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" aria-labelledby="titre-publication">
            <header class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-1 font-semibold text-slate-900">
                        {{ $p->auteur_nom_affiche ?? 'Membre Rezohelp' }}
                        @if ($p->auteur_verifie)
                            <span class="text-sm font-normal text-brand">✔ Entreprise vérifiée</span>
                        @endif
                    </p>
                    <p class="text-sm text-slate-600">
                        Publiée {{ ($p->publiee_le ?? $p->cree_le)?->diffForHumans() }}@if ($commune) · {{ $commune }}@endif
                    </p>
                </div>
                <x-badge-type :type="$p->type" :sous-type="$p->sous_type" />
            </header>

            <h1 id="titre-publication" class="mt-4 text-2xl font-bold leading-tight text-slate-900">{{ $p->titre }}</h1>
            @if ($categorie)
                <p class="mt-1 text-slate-600">{{ $categorie }}</p>
            @endif

            @if ($p->statut === StatutPublication::Cloturee)
                <x-message type="info" class="mt-4">Cette publication est clôturée{{ $p->motif_cloture ? ' : '.$p->motif_cloture : '' }}.</x-message>
            @elseif ($p->statut === StatutPublication::Expiree)
                <x-message type="info" class="mt-4">Cette publication a expiré.</x-message>
            @elseif ($p->statut !== StatutPublication::Publiee)
                <x-message type="info" class="mt-4">Cette publication n’est pas visible dans le fil ({{ $p->statut->libelle() }}).</x-message>
            @endif

            @if ($p->type === TypePublication::Emploi)
                <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"><span aria-hidden="true">⚠️</span> {{ \App\Models\Parametre::texte('texte_bandeau_prevention') }}</p>
            @endif

            @if (! empty($p->faits_cles))
                <ul class="mt-4 flex flex-wrap gap-2" aria-label="Informations clés">
                    @foreach ($p->faits_cles as $fait)
                        <li class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-800">{{ $fait }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($p->image_url)
                <img src="{{ $p->image_url }}" alt="{{ $p->titre }}" width="{{ $p->image_largeur ?? 640 }}" height="{{ $p->image_hauteur ?? 360 }}" class="mt-4 h-auto w-full rounded-xl object-cover">
            @endif

            <div class="mt-4 space-y-3 text-slate-800">{!! nl2br(e($p->contenu)) !!}</div>

            {{-- Détails selon le type --}}
            <dl class="mt-6 grid gap-x-6 gap-y-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                @if ($emploi)
                    @if ($emploi->type_contrat)<div><dt class="text-sm text-slate-600">Contrat</dt><dd class="font-medium">{{ $emploi->type_contrat->libelle() }}</dd></div>@endif
                    <div><dt class="text-sm text-slate-600">Postes</dt><dd class="font-medium">{{ $emploi->nb_postes }}</dd></div>
                    <div><dt class="text-sm text-slate-600">Salaire</dt><dd class="font-medium">{{ $emploi->salaire_a_negocier ? 'À négocier' : (Montant::fourchette($emploi->salaire_min, $emploi->salaire_max) ?? 'Non précisé') }}</dd></div>
                    @if ($emploi->experience_requise)<div><dt class="text-sm text-slate-600">Expérience</dt><dd class="font-medium">{{ $emploi->experience_requise }}</dd></div>@endif
                    @if ($emploi->niveau_etude)<div><dt class="text-sm text-slate-600">Niveau d’études</dt><dd class="font-medium">{{ $emploi->niveau_etude }}</dd></div>@endif
                    @if ($emploi->date_limite)<div><dt class="text-sm text-slate-600">Date limite</dt><dd class="font-medium">{{ $emploi->date_limite->translatedFormat('j F Y') }}</dd></div>@endif
                    @if ($emploi->employeur_particulier)<div><dt class="text-sm text-slate-600">Employeur</dt><dd class="font-medium">Particulier</dd></div>@endif
                @elseif ($service)
                    @if ($service->metier)<div><dt class="text-sm text-slate-600">Métier</dt><dd class="font-medium">{{ $service->metier }}</dd></div>@endif
                    @if ($p->sous_type === SousTypeService::Demande)
                        <div><dt class="text-sm text-slate-600">Date souhaitée</dt><dd class="font-medium">{{ $service->date_souhaitee?->translatedFormat('j F Y') ?? 'Dès que possible' }}</dd></div>
                        @if ($service->budget)<div><dt class="text-sm text-slate-600">Budget</dt><dd class="font-medium">{{ Montant::fcfa($service->budget) }}</dd></div>@endif
                    @else
                        <div><dt class="text-sm text-slate-600">Tarif indicatif</dt><dd class="font-medium">{{ $service->tarif_indicatif ? Montant::fcfa($service->tarif_indicatif).' '.($service->unite_tarif?->libelle() ?? '') : 'Sur devis' }}</dd></div>
                        @if ($service->disponibilite)<div><dt class="text-sm text-slate-600">Disponibilité</dt><dd class="font-medium">{{ $service->disponibilite }}</dd></div>@endif
                        @if (! empty($service->zones_intervention))
                            <div class="sm:col-span-2"><dt class="text-sm text-slate-600">Zones d’intervention</dt>
                                <dd class="font-medium">{{ collect($service->zones_intervention)->map(fn ($id) => $referentiels->nomCommune((int) $id))->filter()->join(', ') }}</dd></div>
                        @endif
                    @endif
                @elseif ($formation)
                    @if ($formation->organisme)<div><dt class="text-sm text-slate-600">Organisme</dt><dd class="font-medium">{{ $formation->organisme }}</dd></div>@endif
                    <div><dt class="text-sm text-slate-600">Modalité</dt><dd class="font-medium">{{ $formation->modalite->libelle() }}</dd></div>
                    @if ($formation->duree)<div><dt class="text-sm text-slate-600">Durée</dt><dd class="font-medium">{{ $formation->duree }}</dd></div>@endif
                    @if ($formation->date_debut)<div><dt class="text-sm text-slate-600">Début</dt><dd class="font-medium">{{ $formation->date_debut->translatedFormat('j F Y') }}</dd></div>@endif
                    <div><dt class="text-sm text-slate-600">Prix</dt><dd class="font-medium">{{ $formation->prix === 0 ? 'Gratuit' : Montant::fcfa($formation->prix) }}</dd></div>
                    <div><dt class="text-sm text-slate-600">Certifiante</dt><dd class="font-medium">{{ $formation->certifiante ? 'Oui' : 'Non' }}</dd></div>
                    @if ($formation->places)<div><dt class="text-sm text-slate-600">Places</dt><dd class="font-medium">{{ $formation->places }}</dd></div>@endif
                @endif
            </dl>

            {{-- Répondre / contacter --}}
            @include('publications._repondre')

            {{-- Actions --}}
            <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4" x-data="reaction({{ $aime ? 'true' : 'false' }}, {{ $p->nb_jaime }})">
                <form method="POST" action="{{ route('publications.jaime', $p->id) }}" @submit.prevent="basculer($el)">
                    @csrf
                    <x-bouton variante="secondaire" aria-pressed="{{ $aime ? 'true' : 'false' }}" x-bind:aria-pressed="aime.toString()" class="aria-pressed:border-brand aria-pressed:text-brand">
                        👍 J’aime · <span x-text="nb">{{ $p->nb_jaime }}</span>
                    </x-bouton>
                </form>
                <form method="POST" action="{{ route('publications.enregistrer', $p->id) }}" x-data="bascule({{ $enregistree ? 'true' : 'false' }})" @submit.prevent="envoyer($el)">
                    @csrf
                    <x-bouton variante="secondaire" aria-pressed="{{ $enregistree ? 'true' : 'false' }}" x-bind:aria-pressed="actif.toString()" class="group">
                        🔖 <span class="group-aria-pressed:hidden">Enregistrer</span><span class="hidden group-aria-pressed:inline">Enregistré</span>
                    </x-bouton>
                </form>
                @if ($p->statut === StatutPublication::Publiee)
                    <x-bouton :href="route('publications.partager', $p->slug)" target="_blank" rel="noopener" variante="secondaire">↗ Partager sur WhatsApp</x-bouton>
                @endif
            </div>
        </article>

        {{-- Commentaires --}}
        <section id="commentaires" class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" aria-labelledby="titre-commentaires">
            <h2 id="titre-commentaires" class="text-lg font-bold">Commentaires ({{ $p->nb_commentaires }})</h2>

            @auth
                @if ($p->statut === StatutPublication::Publiee)
                    <form method="POST" action="{{ route('publications.commenter', $p->id) }}" class="mt-3 space-y-2">
                        @csrf
                        <label for="contenu" class="sr-only">Votre commentaire</label>
                        <textarea id="contenu" name="contenu" rows="2" maxlength="1000" required placeholder="Écrire un commentaire…"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base focus:border-brand focus:ring-2 focus:ring-brand/30">{{ old('contenu') }}</textarea>
                        @error('contenu')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        <x-bouton>Publier</x-bouton>
                    </form>
                @endif
            @else
                <p class="mt-3 text-sm text-slate-700"><a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">Connectez-vous</a> pour commenter.</p>
            @endauth

            <ul class="mt-4 space-y-4">
                @forelse ($p->commentaires as $commentaire)
                    <li id="commentaire-{{ $commentaire->id }}">
                        @include('publications._commentaire', ['commentaire' => $commentaire])
                        @if ($commentaire->sousCommentaires->isNotEmpty())
                            <ul class="ml-6 mt-3 space-y-3 border-l-2 border-slate-100 pl-4">
                                @foreach ($commentaire->sousCommentaires as $reponse)
                                    <li id="commentaire-{{ $reponse->id }}">@include('publications._commentaire', ['commentaire' => $reponse])</li>
                                @endforeach
                            </ul>
                        @endif
                        @auth
                            @if ($p->statut === StatutPublication::Publiee)
                                <details class="ml-6 mt-2">
                                    <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-semibold text-brand">Répondre</summary>
                                    <form method="POST" action="{{ route('publications.commenter', $p->id) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="hidden" name="parent_id" value="{{ $commentaire->id }}">
                                        <label for="reponse-{{ $commentaire->id }}" class="sr-only">Votre réponse</label>
                                        <textarea id="reponse-{{ $commentaire->id }}" name="contenu" rows="2" maxlength="1000" required
                                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
                                        <x-bouton variante="secondaire">Répondre</x-bouton>
                                    </form>
                                </details>
                            @endif
                        @endauth
                    </li>
                @empty
                    <li class="text-sm text-slate-600">Aucun commentaire pour le moment.</li>
                @endforelse
            </ul>
        </section>

        {{-- Signaler --}}
        <section id="signaler" class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" aria-labelledby="titre-signaler">
            <details>
                <summary id="titre-signaler" class="inline-flex min-h-11 cursor-pointer items-center font-semibold text-slate-800">🚩 Signaler cette publication</summary>
                @auth
                    <form method="POST" action="{{ route('publications.signaler', $p->id) }}" class="mt-3 space-y-3">
                        @csrf
                        <fieldset>
                            <legend class="text-sm font-medium text-slate-800">Motif</legend>
                            <div class="mt-2 space-y-1">
                                @foreach (MotifSignalement::cases() as $motif)
                                    <label class="flex min-h-11 items-center gap-2 text-sm">
                                        <input type="radio" name="motif" value="{{ $motif->value }}" required class="size-5 text-brand" @checked(old('motif') === $motif->value)>
                                        {{ $motif->libelle() }}
                                    </label>
                                @endforeach
                            </div>
                            @error('motif')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        </fieldset>
                        <div>
                            <label for="commentaire-signalement" class="block text-sm font-medium text-slate-800">Précisions (facultatif)</label>
                            <textarea id="commentaire-signalement" name="commentaire" rows="2" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-base"></textarea>
                        </div>
                        <x-bouton variante="danger">Envoyer le signalement</x-bouton>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-700"><a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">Connectez-vous</a> pour signaler une publication.</p>
                @endauth
            </details>
        </section>
    </div>
@endsection
