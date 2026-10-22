@extends('admin.layout')

@section('titre', 'Veille IA')

@section('contenu')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Veille IA — imports</h1>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.veille.importer-maintenant') }}" data-confirmer="Chercher maintenant les nouveaux fichiers du dossier Drive ?">
                @csrf
                <x-bouton>Importer maintenant</x-bouton>
            </form>
            <form method="POST" action="{{ route('admin.veille.reevaluer') }}" data-confirmer="Réévaluer les annonces en attente selon les sources actuelles ? Celles autorisées passeront en ligne.">
                @csrf
                <x-bouton variante="secondaire">Réévaluer les annonces en attente</x-bouton>
            </form>
            <x-bouton :href="route('admin.veille.sources.index')" variante="secondaire">Sources de la veille</x-bouton>
        </div>
    </div>
    <p class="mb-4 text-sm text-slate-600">Fichiers déposés par la veille dans Google Drive et importés à 01h20, 09h20 et 17h20.</p>

    <x-admin.table libelle="Imports de la veille" :table="$table" :url="route('admin.veille.imports.index')" />

    <section aria-labelledby="titre-couts" class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
        <h2 id="titre-couts" class="font-semibold">Coût de l’API IA par mois</h2>
        @if ($couts->isEmpty())
            <p class="mt-2 text-sm text-slate-600">Aucun appel pour le moment.</p>
        @else
            <table class="mt-2 w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-600">
                    <tr><th scope="col" class="py-1">Mois</th><th scope="col">Appels</th><th scope="col">Jetons entrée</th><th scope="col">Jetons sortie</th><th scope="col">Coût estimé</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($couts as $mois)
                        <tr>
                            <td class="py-1">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $mois->mois)->translatedFormat('F Y') }}</td>
                            <td>{{ \App\Support\Montant::nombre((int) $mois->appels) }}</td>
                            <td>{{ \App\Support\Montant::nombre((int) $mois->entree) }}</td>
                            <td>{{ \App\Support\Montant::nombre((int) $mois->sortie) }}</td>
                            <td>{{ \App\Support\Montant::fcfa((int) $mois->cout) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
