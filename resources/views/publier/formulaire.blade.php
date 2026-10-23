@extends('layouts.public')

@php
    use App\Enums\Modalite;
    use App\Enums\ModeCandidature;
    use App\Enums\ModeInscription;
    use App\Enums\ParcoursPublication;
    use App\Enums\TypeContrat;
    use App\Enums\UniteTarif;

    $referentiels = app(\App\Services\Referentiels::class);
    $communes = collect($referentiels->communes())->mapWithKeys(fn ($c) => [$c['id'] => $c['nom']])->all();
    $estService = in_array($parcours, [ParcoursPublication::ServiceOffre, ParcoursPublication::ServiceDemande], true);
    $categories = collect($referentiels->categories())->where('type', $parcours->type()->value);
    $groupesMetiers = $estService
        ? $categories->whereNull('parent_id')->mapWithKeys(fn ($parent) => [
            $parent['nom'] => $categories->where('parent_id', $parent['id'])->mapWithKeys(fn ($c) => [$c['id'] => $c['nom']])->all(),
        ])->all()
        : null;
    $optionsCategories = $estService ? [] : $categories->whereNull('parent_id')->mapWithKeys(fn ($c) => [$c['id'] => $c['nom']])->all();
    $v = fn (string $cle, mixed $defaut = null) => $valeurs[$cle] ?? $defaut;
    $enModification = $publication !== null && $publication->statut !== \App\Enums\StatutPublication::Brouillon;
    $action = $publication !== null ? route('espace.publications.update', $publication->id) : route('publier.store', $parcours->value);
    $choixEmployeur = $parcours === ParcoursPublication::Emploi
        ? ['particulier' => 'Je recrute en tant que particulier', 'nouvelle' => 'Créer une entreprise'] : ['texte' => 'Indiquer simplement le nom de l’organisme', 'nouvelle' => 'Créer un organisme'];
    if ($entreprises->isNotEmpty()) {
        $choixEmployeur = ['entreprise' => 'Une de mes entreprises'] + $choixEmployeur;
    }
    $champEmployeur = $parcours === ParcoursPublication::Emploi ? 'employeur' : 'organisateur';
@endphp

@section('titre', $parcours->libelle())

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <a href="{{ $publication ? route('espace.publications.index') : route('publier.choix') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Retour</a>
        <h1 class="text-2xl font-bold">{{ $enModification ? 'Modifier ma publication' : $parcours->libelle() }}</h1>
        <p class="mt-1 text-slate-600">{{ $parcours->description() }}</p>

        @if ($errors->any())
            <x-message type="erreur" class="mt-4">Merci de corriger les champs signalés ci-dessous.</x-message>
        @endif

        @if ($dernierQuota ?? false)
            <x-message type="info" class="mt-4">
                {{ $parcours === \App\Enums\ParcoursPublication::Emploi
                    ? 'Il vous reste 1 offre d’emploi gratuite ce mois-ci.'
                    : 'Votre formule permet encore 1 publication de ce type en ligne.' }}
                <a href="{{ route('espace.abonnement') }}" class="font-semibold underline">Voir les formules</a>
            </x-message>
        @endif

        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mt-4 space-y-4" novalidate
              x-data="formulairePublication(@js($publication ? null : 'rezohelp-brouillon-'.$parcours->value), @js(route('publier.apercu', $parcours->value)), @js($parcours === ParcoursPublication::ServiceOffre && $publication === null))"
              @submit="oublier()" @change="proposerTitre($event)">
            @csrf
            @if ($publication)
                @method('PUT')
            @endif

            <template x-if="restaure">
                <x-message type="info">
                    Nous avons retrouvé votre brouillon sur cet appareil.
                    <button type="button" class="ml-1 font-semibold underline" @click="effacer()">Recommencer à zéro</button>
                </x-message>
            </template>

            {{-- L'essentiel --}}
            <details open class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6">
                <summary class="cursor-pointer text-lg font-bold">L’essentiel</summary>
                <div class="mt-4 space-y-5">
                    @if ($estService)
                        <x-liste name="categorie_id" label="{{ $parcours === ParcoursPublication::ServiceOffre ? 'Votre métier' : 'Métier recherché' }}"
                                 :groupes="$groupesMetiers" :valeur="$v('categorie_id')" vide="Choisir un métier" />
                    @else
                        <x-liste name="categorie_id" label="Catégorie" :options="$optionsCategories" :valeur="$v('categorie_id')" vide="Choisir une catégorie" />
                    @endif

                    @if ($parcours === ParcoursPublication::Formation)
                        <x-liste name="modalite" label="Modalité" :options="Modalite::options()" :valeur="$v('modalite', Modalite::Presentiel->value)" />
                    @endif

                    <x-liste name="commune_id" label="{{ $parcours === ParcoursPublication::Formation ? 'Commune (sauf formation en ligne)' : 'Commune' }}"
                             :options="$communes" :valeur="$v('commune_id')" vide="Choisir une commune" />

                    <x-champ name="titre" label="Titre" :valeur="$v('titre')" :maxlength="\App\Http\Requests\Publication\PublierPublicationRequest::TITRE_MAX" required @input="titreModifie = true"
                             :aide="match ($parcours) {
                                 ParcoursPublication::Emploi => 'Ex. : Comptable confirmé (CDI).',
                                 ParcoursPublication::ServiceOffre => 'Ex. : Électricien disponible à Cocody.',
                                 ParcoursPublication::ServiceDemande => 'Ex. : Cherche plombier pour une fuite à Yopougon.',
                                 ParcoursPublication::Formation => 'Ex. : Bootcamp développeur web.',
                             }" />

                    <x-zone-texte name="contenu" label="{{ $parcours === ParcoursPublication::ServiceDemande ? 'Décrivez votre besoin' : 'Description' }}"
                                  :valeur="$v('contenu')" :lignes="8" aide="Écrivez librement : tâches, profil, horaires, conditions…" required />
                </div>
            </details>

            {{-- Employeur ou organisme --}}
            @if (in_array($parcours, [ParcoursPublication::Emploi, ParcoursPublication::Formation], true))
                <details open class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" x-data="{ choix: @js(old($champEmployeur, $v($champEmployeur, array_key_first($choixEmployeur)))) }">
                    <summary class="cursor-pointer text-lg font-bold">{{ $parcours === ParcoursPublication::Emploi ? 'Employeur' : 'Organisme' }}</summary>
                    <fieldset class="mt-4 space-y-1">
                        <legend class="sr-only">{{ $parcours === ParcoursPublication::Emploi ? 'Qui recrute ?' : 'Qui organise ?' }}</legend>
                        @foreach ($choixEmployeur as $valeur => $libelle)
                            <label class="flex min-h-11 items-center gap-3 text-sm">
                                <input type="radio" name="{{ $champEmployeur }}" value="{{ $valeur }}" x-model="choix" class="size-5 text-brand">
                                {{ $libelle }}
                            </label>
                        @endforeach
                        @error($champEmployeur)<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="mt-3 space-y-4" x-show="choix === 'entreprise'" x-cloak>
                        <x-liste name="entreprise_id" label="Entreprise" :options="$entreprises->pluck('nom', 'id')->all()" :valeur="$v('entreprise_id')" />
                    </div>
                    <div class="mt-3 space-y-4" x-show="choix === 'nouvelle'" x-cloak>
                        <x-champ name="entreprise_nom" label="Nom" :valeur="$v('entreprise_nom')" maxlength="150" />
                        <x-champ name="entreprise_secteur" label="Secteur d’activité (facultatif)" :valeur="$v('entreprise_secteur')" maxlength="100" />
                        <x-liste name="entreprise_commune_id" label="Commune (facultatif)" :options="$communes" vide="—" :valeur="$v('entreprise_commune_id')" />
                        <div class="space-y-1.5">
                            <label for="entreprise_logo" class="block text-sm font-medium text-slate-800">Logo (facultatif)</label>
                            <input id="entreprise_logo" name="entreprise_logo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
                            @error('entreprise_logo')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @if ($parcours === ParcoursPublication::Formation)
                        <div class="mt-3" x-show="choix === 'texte'" x-cloak>
                            <x-champ name="organisme" label="Nom de l’organisme" :valeur="$v('organisme')" maxlength="150" />
                        </div>
                    @endif
                </details>
            @endif

            {{-- Détails propres au parcours --}}
            <details open class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6">
                <summary class="cursor-pointer text-lg font-bold">Détails</summary>
                <div class="mt-4 space-y-5">
                    @switch($parcours)
                        @case(ParcoursPublication::Emploi)
                            <x-liste name="type_contrat" label="Type de contrat" :options="TypeContrat::options()" :valeur="$v('type_contrat')" vide="Choisir" />
                            <x-champ name="nb_postes" type="number" label="Nombre de postes" :valeur="$v('nb_postes', 1)" min="1" max="500" inputmode="numeric" />
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-champ name="salaire_min" type="number" label="Salaire minimum (FCFA / mois)" :valeur="$v('salaire_min')" min="0" inputmode="numeric" aide="Sans espace, ex. 150000" />
                                <x-champ name="salaire_max" type="number" label="Salaire maximum (FCFA / mois)" :valeur="$v('salaire_max')" min="0" inputmode="numeric" />
                            </div>
                            <x-case name="salaire_a_negocier" label="Salaire à négocier" :coche="$v('salaire_a_negocier')" />
                            <x-champ name="experience_requise" label="Expérience demandée (facultatif)" :valeur="$v('experience_requise')" maxlength="100" placeholder="Ex. 2 ans minimum" />
                            <x-champ name="niveau_etude" label="Niveau d’études (facultatif)" :valeur="$v('niveau_etude')" maxlength="100" placeholder="Ex. BTS" />
                            <x-champ name="date_limite" type="date" label="Date limite de candidature (facultatif)" :valeur="$v('date_limite')" />
                            @break

                        @case(ParcoursPublication::ServiceOffre)
                            <fieldset>
                                <legend class="text-sm font-medium text-slate-800">Zones d’intervention</legend>
                                <div class="mt-2 grid grid-cols-2 gap-x-4">
                                    @foreach ($communes as $id => $nom)
                                        <label class="flex min-h-11 items-center gap-2 text-sm">
                                            <input type="checkbox" name="zones_intervention[]" value="{{ $id }}" class="size-5 rounded text-brand"
                                                   @checked(in_array($id, array_map('intval', (array) old('zones_intervention', $v('zones_intervention', []))), true))>
                                            {{ $nom }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('zones_intervention.*')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                            </fieldset>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-champ name="tarif_indicatif" type="number" label="Tarif indicatif (FCFA, facultatif)" :valeur="$v('tarif_indicatif')" min="0" inputmode="numeric" />
                                <x-liste name="unite_tarif" label="Unité" :options="UniteTarif::options()" :valeur="$v('unite_tarif', UniteTarif::Prestation->value)" />
                            </div>
                            <x-champ name="disponibilite" label="Disponibilité (facultatif)" :valeur="$v('disponibilite')" maxlength="150" placeholder="Ex. Du lundi au samedi" />
                            <x-champ name="telephone_contact" type="tel" label="Téléphone de contact" :valeur="$v('telephone_contact')" inputmode="tel" autocomplete="tel-national" required />
                            <x-case name="afficher_telephone" label="Afficher mon numéro sur l’annonce" :coche="$v('afficher_telephone', true)" />
                            <div x-data="{ meme: @js((bool) old('whatsapp_meme_numero', $v('whatsapp_meme_numero', true))) }">
                                <label class="flex min-h-11 items-center gap-3 text-sm text-slate-800">
                                    <input type="hidden" name="whatsapp_meme_numero" value="0">
                                    <input type="checkbox" name="whatsapp_meme_numero" value="1" x-model="meme" class="size-5 rounded text-brand">
                                    WhatsApp : même numéro
                                </label>
                                <div x-show="! meme" x-cloak>
                                    <x-champ name="whatsapp" type="tel" label="Numéro WhatsApp (facultatif)" :valeur="$v('whatsapp')" inputmode="tel" />
                                </div>
                            </div>
                            @break

                        @case(ParcoursPublication::ServiceDemande)
                            <x-liste name="date_souhaitee_choix" label="Pour quand ?" :valeur="$v('date_souhaitee_choix', 'semaine')"
                                     :options="['aujourdhui' => 'Aujourd’hui', 'semaine' => 'Cette semaine', 'date' => 'À une date précise', 'flexible' => 'Dès que possible']" />
                            <x-champ name="date_souhaitee" type="date" label="Date précise (si besoin)" :valeur="$v('date_souhaitee')" />
                            <x-champ name="budget" type="number" label="Budget (FCFA, facultatif)" :valeur="$v('budget')" min="0" inputmode="numeric" />
                            <x-message type="info">Votre numéro ne sera jamais affiché. Les prestataires vous répondent sur Rezohelp et vous recevez leurs coordonnées.</x-message>
                            @break

                        @case(ParcoursPublication::Formation)
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-champ name="date_debut" type="date" label="Date de début (facultatif)" :valeur="$v('date_debut')" />
                                <x-champ name="duree" label="Durée (facultatif)" :valeur="$v('duree')" maxlength="100" placeholder="Ex. 3 mois" />
                            </div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-champ name="prix" type="number" label="Prix (FCFA, 0 = gratuit)" :valeur="$v('prix', 0)" min="0" inputmode="numeric" required />
                                <x-champ name="places" type="number" label="Places (facultatif)" :valeur="$v('places')" min="1" inputmode="numeric" />
                            </div>
                            <x-case name="certifiante" label="Formation certifiante" :coche="$v('certifiante')" />
                            @break
                    @endswitch
                </div>
            </details>

            {{-- Répondre --}}
            @if (in_array($parcours, [ParcoursPublication::Emploi, ParcoursPublication::Formation], true))
                @php($champMode = $parcours === ParcoursPublication::Emploi ? 'mode_candidature' : 'mode_inscription')
                @php($champContact = $parcours === ParcoursPublication::Emploi ? 'contact_candidature' : 'contact_inscription')
                <details open class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6"
                         x-data="{ mode: @js(old($champMode, $v($champMode, 'rezohelp'))) }">
                    <summary class="cursor-pointer text-lg font-bold">{{ $parcours === ParcoursPublication::Emploi ? 'Comment postuler ?' : 'Comment s’inscrire ?' }}</summary>
                    <div class="mt-4 space-y-5">
                        <x-liste :name="$champMode" label="Mode" x-model="mode" :valeur="$v($champMode, 'rezohelp')"
                                 :options="$parcours === ParcoursPublication::Emploi ? ModeCandidature::options() : ModeInscription::options()" />
                        <div x-show="mode !== 'rezohelp'" x-cloak>
                            <x-champ :name="$champContact" label="E-mail, lien ou numéro de téléphone" :valeur="$v($champContact)" maxlength="255"
                                     aide="Selon le mode choisi : adresse e-mail, lien commençant par https:// ou numéro de téléphone." />
                        </div>
                    </div>
                </details>
            @endif

            {{-- Photos et durée --}}
            <details class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70 sm:p-6" @if ($errors->has('photos*') || $errors->has('expire_le')) open @endif>
                <summary class="cursor-pointer text-lg font-bold">Photos et durée</summary>
                <div class="mt-4 space-y-5">
                    @if ($photosMax > 0)
                        <div class="space-y-1.5">
                            <label for="photos" class="block text-sm font-medium text-slate-800">Photos (facultatif, {{ $photosMax }} au maximum)</label>
                            <input id="photos" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm" aria-describedby="photos-aide">
                            <p id="photos-aide" class="text-sm text-slate-600">JPG, PNG ou WebP, 5 Mo au maximum chacune.@if ($parcours === ParcoursPublication::ServiceOffre) Des photos de vos réalisations rassurent les clients.@endif</p>
                            @error('photos')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                            @error('photos.*')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                    @endif
                    @if (! $enModification)
                        <x-champ name="expire_le" type="date" label="Visible jusqu’au (facultatif)" :valeur="$v('expire_le')"
                                 aide="Par défaut, {{ \App\Models\Parametre::entier('jours_expiration_defaut') }} jours." />
                    @endif
                </div>
            </details>

            {{-- Aperçu --}}
            <section aria-label="Aperçu" class="space-y-2">
                <button type="button" @click="apercu()" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 font-semibold text-slate-800 hover:bg-slate-50">
                    👁 Voir l’aperçu dans le fil
                </button>
                <p x-show="erreurApercu" x-cloak class="text-sm text-red-700" x-text="erreurApercu"></p>
                <div x-ref="apercu" aria-live="polite"></div>
            </section>

            <div class="flex flex-col gap-2 sm:flex-row-reverse">
                <x-bouton name="action" value="publier" class="sm:flex-1">{{ $enModification ? 'Enregistrer' : 'Publier' }}</x-bouton>
                @if (! $enModification)
                    <x-bouton name="action" value="brouillon" variante="secondaire" class="sm:flex-1">Enregistrer en brouillon</x-bouton>
                @endif
            </div>
        </form>
    </div>
@endsection
