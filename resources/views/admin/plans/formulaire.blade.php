@extends('admin.layout')

@section('titre', $plan->exists ? 'Plan '.$plan->nom : 'Nouveau plan')

@section('contenu')
    <a href="{{ route('admin.plans.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Plans</a>
    <h1 class="text-2xl font-bold">{{ $plan->exists ? 'Plan « '.$plan->nom.' »' : 'Nouveau plan' }}</h1>

    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="mt-4 grid gap-6 xl:grid-cols-2">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <section aria-labelledby="titre-plan" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
            <h2 id="titre-plan" class="font-semibold">Formule</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ name="code" label="Code" :valeur="$plan->code" maxlength="50" required />
                <x-champ name="nom" label="Nom" :valeur="$plan->nom" maxlength="80" required />
                <x-liste name="cible" label="Cible" :options="\App\Enums\CibleOffre::options()" :valeur="$plan->cible?->value" />
                <x-champ name="famille" label="Famille (un essai par famille)" :valeur="$plan->famille" maxlength="50" />
                <x-champ name="prix_fcfa" label="Prix TTC (FCFA)" type="number" min="0" :valeur="$plan->prix_fcfa" required />
                <x-champ name="duree_jours" label="Durée (jours)" type="number" min="0" :valeur="$plan->duree_jours" required />
                <x-champ name="duree_essai_jours" label="Essai gratuit (jours, 0 = aucun)" type="number" min="0" :valeur="$plan->duree_essai_jours" required />
                <x-champ name="ordre" label="Ordre sur /tarifs" type="number" min="0" :valeur="$plan->ordre" required />
            </div>
            <x-zone-texte name="description" label="Description" :valeur="$plan->description" :max="500" :lignes="3" />
            <div class="flex flex-wrap gap-x-6">
                <x-case name="actif" label="Actif (souscriptible)" :coche="$plan->actif" />
                <x-case name="visible" label="Visible sur /tarifs" :coche="$plan->visible" />
                <x-case name="est_defaut" label="Plan par défaut de la cible (gratuit)" :coche="$plan->est_defaut" />
            </div>
        </section>

        <section aria-labelledby="titre-limites" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
            <h2 id="titre-limites" class="font-semibold">Limites</h2>
            <p class="text-sm text-slate-600">Champ vide : la clé n’est pas fixée par ce plan (les autres plans du titulaire s’appliquent).</p>
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach (\App\Enums\CleDroit::cases() as $cle)
                    @php
                        $champ = \App\Http\Requests\Admin\PlanRequest::champ($cle);
                        $actuelle = old('limites.'.$champ, $limites[$cle->value] ?? '');
                        $illimite = old('illimite.'.$champ, ($limites[$cle->value] ?? null) === (string) \App\Enums\CleDroit::ILLIMITE);
                    @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <label for="limite-{{ $champ }}" class="text-sm">
                            {{ $cle->libelle() }} <code class="text-xs text-slate-500">{{ $cle->value }}</code>
                            @if ($cle->estQuota())<span class="text-xs text-amber-700">quota</span>@endif
                        </label>
                        @if ($cle->estBooleen())
                            <select id="limite-{{ $champ }}" name="limites[{{ $champ }}]" class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                                <option value="" @selected($actuelle === '')>Non fixé</option>
                                <option value="1" @selected($actuelle === '1')>Oui</option>
                                <option value="0" @selected($actuelle === '0')>Non</option>
                            </select>
                        @else
                            <span class="flex items-center gap-2">
                                <input id="limite-{{ $champ }}" type="number" min="0" name="limites[{{ $champ }}]" value="{{ $illimite ? '' : $actuelle }}" class="min-h-11 w-28 rounded-lg border border-slate-300 px-3 text-sm">
                                <label class="inline-flex items-center gap-1 text-sm">
                                    <input type="checkbox" name="illimite[{{ $champ }}]" value="1" @checked($illimite) class="size-5 rounded border-slate-300"> illimité
                                </label>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="xl:col-span-2">
            <x-bouton>Enregistrer le plan</x-bouton>
        </div>
    </form>

    @if ($plan->exists)
        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="mt-6" data-confirmer="Supprimer ce plan ? Impossible s’il a déjà été souscrit." data-danger="1">
            @csrf
            @method('DELETE')
            <x-bouton variante="lien" class="text-red-700">Supprimer le plan</x-bouton>
        </form>
    @endif
@endsection
