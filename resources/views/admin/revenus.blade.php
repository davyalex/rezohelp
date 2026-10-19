@extends('admin.layout')

@section('titre', 'Revenus')

@section('contenu')
    @php
        $taux = $revenus['essais_termines'] > 0 ? round($revenus['essais_convertis'] * 100 / $revenus['essais_termines']) : null;
    @endphp
    <h1 class="text-2xl font-bold">Revenus — {{ now()->translatedFormat('F Y') }}</h1>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.carte-stat libelle="Chiffre d’affaires du mois (TTC)" :valeur="\App\Support\Montant::fcfa($revenus['ca_mois'])"
                            :detail="'Plans '.\App\Support\Montant::fcfa($revenus['ca_plans']).' · Produits '.\App\Support\Montant::fcfa($revenus['ca_produits'])" />
        <x-admin.carte-stat libelle="Essais en cours" :valeur="$revenus['essais_en_cours']"
                            :detail="$taux === null ? 'Aucun essai terminé' : 'Conversion essai → payant : '.$taux.' % ('.$revenus['essais_convertis'].' / '.$revenus['essais_termines'].')'" />
        <x-admin.carte-stat libelle="Coût SMS du mois" :valeur="\App\Support\Montant::fcfa($revenus['cout_sms'])" />
        <x-admin.carte-stat libelle="Coût IA du mois" :valeur="\App\Support\Montant::fcfa($revenus['cout_ia'])" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section aria-labelledby="titre-actifs" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
            <h2 id="titre-actifs" class="font-semibold">Abonnements actifs par plan</h2>
            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                @forelse ($revenus['actifs_par_plan'] as $plan => $nombre)
                    <li class="flex justify-between py-2"><span>{{ $plan }}</span><strong>{{ $nombre }}</strong></li>
                @empty
                    <li class="py-2 text-slate-600">Aucun abonnement payé en cours.</li>
                @endforelse
            </ul>
        </section>

        <section aria-labelledby="titre-echeances" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
            <h2 id="titre-echeances" class="font-semibold">Échéances des 7 prochains jours</h2>
            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                @forelse ($echeances as $abonnement)
                    <li class="flex flex-wrap justify-between gap-2 py-2">
                        <span>{{ $abonnement->titulaire instanceof \App\Models\Entreprise ? $abonnement->titulaire->nom : ($abonnement->titulaire instanceof \App\Models\Utilisateur ? $abonnement->titulaire->nomAffiche() : '—') }} · {{ $abonnement->plan->nom }}</span>
                        <span>{{ $abonnement->statut->libelle() }} · {{ $abonnement->fin_le?->format('d/m/Y') }}</span>
                    </li>
                @empty
                    <li class="py-2 text-slate-600">Aucune.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
