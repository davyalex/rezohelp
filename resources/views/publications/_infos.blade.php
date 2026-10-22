{{-- Informations clés de la page détail, en tête de page : ce que le lecteur cherche avant de lire le texte. --}}
@php
    use App\Enums\SousTypeService;
    use App\Support\Montant;
    use Illuminate\Support\Carbon;

    $p = $publication;
    $emploi = $p->detailEmploi;
    $service = $p->detailService;
    $formation = $p->detailFormation;
    $referentiels = app(\App\Services\Referentiels::class);

    // Date limite de candidature : seule date de clôture réelle (expire_le n'est souvent qu'une durée de validité).
    $cloture = $emploi?->date_limite;
    $joursRestants = $cloture !== null ? (int) Carbon::today()->diffInDays($cloture, false) : null;
    $delai = match (true) {
        $joursRestants === null => null,
        $joursRestants < 0 => 'clôturée',
        $joursRestants === 0 => 'dernier jour',
        $joursRestants === 1 => 'encore 1 jour',
        default => 'encore '.$joursRestants.' jours',
    };

    /** @var list<array{libelle: string, valeur: string, large?: bool}> $infos */
    $infos = [];
    $ajouter = function (string $libelle, ?string $valeur, bool $large = false) use (&$infos): void {
        if ($valeur !== null && trim($valeur) !== '') {
            $infos[] = ['libelle' => $libelle, 'valeur' => $valeur, 'large' => $large];
        }
    };

    if ($emploi) {
        $ajouter('Contrat', $emploi->type_contrat?->libelle());
        $ajouter('Lieu', $commune);
        $ajouter('Salaire', $emploi->salaire_a_negocier ? 'À négocier' : Montant::fourchette($emploi->salaire_min, $emploi->salaire_max));
        $ajouter('Postes', $emploi->nb_postes > 1 ? (string) $emploi->nb_postes : null);
        $ajouter('Expérience', $emploi->experience_requise);
        $ajouter('Niveau d’études', $emploi->niveau_etude);
        $ajouter('Employeur', $emploi->employeur_particulier ? 'Particulier' : null);
    } elseif ($service) {
        $ajouter('Métier', $service->metier);
        $ajouter('Lieu', $commune);
        if ($p->sous_type === SousTypeService::Demande) {
            $ajouter('Date souhaitée', $service->date_souhaitee?->translatedFormat('j F Y') ?? 'Dès que possible');
            $ajouter('Budget', $service->budget ? Montant::fcfa($service->budget) : null);
        } else {
            $ajouter('Tarif indicatif', $service->tarif_indicatif ? trim(Montant::fcfa($service->tarif_indicatif).' '.($service->unite_tarif?->libelle() ?? '')) : 'Sur devis');
            $ajouter('Disponibilité', $service->disponibilite);
            $ajouter('Zones d’intervention', collect($service->zones_intervention ?? [])->map(fn ($id) => $referentiels->nomCommune((int) $id))->filter()->join(', '), true);
        }
    } elseif ($formation) {
        $ajouter('Organisme', $formation->organisme);
        $ajouter('Modalité', $formation->modalite->libelle());
        $ajouter('Lieu', $commune);
        $ajouter('Début', $formation->date_debut?->translatedFormat('j F Y'));
        $ajouter('Durée', $formation->duree);
        $ajouter('Prix', $formation->prix === 0 ? 'Gratuit' : Montant::fcfa($formation->prix));
        $ajouter('Certification', $formation->certifiante ? 'Formation certifiante' : null);
        $ajouter('Places', $formation->places ? (string) $formation->places : null);
    } else {
        $ajouter('Lieu', $commune);
    }
@endphp

@if ($cloture !== null || $infos !== [])
    <section aria-labelledby="titre-infos" class="mt-4 rounded-xl bg-fond p-4">
        <h2 id="titre-infos" class="sr-only">Informations clés</h2>

        @if ($cloture !== null)
            <p @class([
                'mb-3 flex flex-wrap items-baseline gap-x-2 rounded-lg px-3 py-2',
                'bg-white text-slate-900' => $joursRestants > 3,
                'bg-amber-100 text-amber-950' => $joursRestants >= 0 && $joursRestants <= 3,
                'bg-slate-200 text-slate-700' => $joursRestants < 0,
            ])>
                <span class="text-sm font-medium">Date limite de candidature</span>
                <span class="font-bold">{{ $cloture->translatedFormat('l j F Y') }}</span>
                <span class="text-sm">({{ $delai }})</span>
            </p>
        @endif

        @if ($infos !== [])
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                @foreach ($infos as $info)
                    <div @class(['min-w-0', 'col-span-2' => $info['large']])>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-600">{{ $info['libelle'] }}</dt>
                        <dd class="break-words font-semibold text-slate-900">{{ $info['valeur'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>
@endif
