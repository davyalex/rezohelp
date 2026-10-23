<?php

namespace App\Services;

use App\Enums\ModeCandidature;
use App\Enums\ModeInscription;
use App\Enums\ParcoursPublication;
use App\Enums\SourcePublication;
use App\Enums\StatutPublication;
use App\Jobs\RafraichirBadges;
use App\Models\Categorie;
use App\Models\DetailEmploi;
use App\Models\DetailFormation;
use App\Models\DetailService;
use App\Models\Entreprise;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\Utilisateur;
use App\Support\IdentifiantsPublication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Remplit une publication et ses détails à partir des données validées d'un parcours,
 * avec les champs dénormalisés du fil (extrait, auteur affiché, faits clés…).
 * Utilisé par PublierPublication, ModifierPublication et DupliquerPublication.
 */
class RemplissagePublication
{
    public function __construct(
        private readonly TelephoneService $telephones,
        private readonly FaitsCles $faitsCles,
    ) {}

    /**
     * Entreprise choisie, créée dans le formulaire, ou null (particulier / en son nom).
     *
     * @param  array<string, mixed>  $donnees
     */
    public function entreprise(Utilisateur $auteur, array $donnees): ?Entreprise
    {
        $choix = $donnees['employeur'] ?? $donnees['organisateur'] ?? null;

        if ($choix === 'entreprise' && isset($donnees['entreprise_id'])) {
            return $auteur->entreprises()->whereKey((int) $donnees['entreprise_id'])->firstOrFail();
        }

        if ($choix !== 'nouvelle') {
            return null;
        }

        $nom = trim((string) $donnees['entreprise_nom']);
        $entreprise = $auteur->entreprises()->create([
            'nom' => $nom,
            'slug' => Str::slug($nom).'-'.Str::lower(Str::random(5)),
            'secteur' => $donnees['entreprise_secteur'] ?? null,
            'commune_id' => $donnees['entreprise_commune_id'] ?? null,
        ]);

        if (($logo = $donnees['entreprise_logo'] ?? null) instanceof UploadedFile) {
            $entreprise->addMedia($logo)->toMediaCollection('logo');
        }

        return $entreprise;
    }

    /**
     * Champs communs + dénormalisés. Ne touche jamais au statut.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function remplir(Publication $publication, ParcoursPublication $parcours, array $donnees, Utilisateur $auteur, ?Entreprise $entreprise): void
    {
        $titre = trim((string) $donnees['titre']);
        $contenu = trim((string) $donnees['contenu']);

        $publication->fill([
            'type' => $parcours->type(),
            'sous_type' => $parcours->sousType(),
            'titre' => $titre,
            'contenu' => $contenu,
            'extrait' => IdentifiantsPublication::extrait($contenu),
            'categorie_id' => (int) $donnees['categorie_id'],
            'commune_id' => isset($donnees['commune_id']) ? (int) $donnees['commune_id'] : null,
            'auteur_id' => $auteur->id,
            'entreprise_id' => $entreprise?->id,
            'auteur_nom_affiche' => $entreprise->nom ?? $auteur->nomAffiche(),
            'auteur_avatar_url' => $this->avatar($auteur, $entreprise),
            'auteur_verifie' => (bool) $entreprise?->verifiee,
            'auteur_badge' => RafraichirBadges::badge($entreprise ?? $auteur),
            'source' => SourcePublication::Utilisateur,
            // Une modification ne repousse jamais l'expiration : seule la prolongation le fait.
            'expire_le' => match (true) {
                isset($donnees['expire_le']) && (! $publication->exists || $publication->statut === StatutPublication::Brouillon) => Carbon::parse((string) $donnees['expire_le'])->endOfDay(),
                $publication->exists => $publication->expire_le,
                default => now()->addDays(Parametre::entier('jours_expiration_defaut')),
            },
        ]);

        if (! $publication->exists) {
            $publication->fill([
                'uuid' => (string) Str::uuid(),
                'slug' => IdentifiantsPublication::slug($titre),
                'code_court' => IdentifiantsPublication::codeCourt(),
            ]);
        }
    }

    /**
     * Crée ou met à jour la ligne details_* du parcours, puis recalcule les faits clés.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function details(Publication $publication, ParcoursPublication $parcours, array $donnees, Utilisateur $auteur): void
    {
        $pays = $auteur->pays_code;

        match ($parcours) {
            ParcoursPublication::Emploi => $publication->detailEmploi()->updateOrCreate(['publication_id' => $publication->id], [
                'employeur_particulier' => ($donnees['employeur'] ?? null) === 'particulier',
                'type_contrat' => $donnees['type_contrat'],
                'nb_postes' => (int) ($donnees['nb_postes'] ?? 1),
                'salaire_a_negocier' => (bool) ($donnees['salaire_a_negocier'] ?? false),
                'salaire_min' => ! empty($donnees['salaire_a_negocier']) ? null : ($donnees['salaire_min'] ?? null),
                'salaire_max' => ! empty($donnees['salaire_a_negocier']) ? null : ($donnees['salaire_max'] ?? null),
                'experience_requise' => $donnees['experience_requise'] ?? null,
                'niveau_etude' => $donnees['niveau_etude'] ?? null,
                'date_limite' => $donnees['date_limite'] ?? null,
                'mode_candidature' => $donnees['mode_candidature'],
                'contact_candidature' => $this->contact(ModeCandidature::from((string) $donnees['mode_candidature'])->value, $donnees['contact_candidature'] ?? null, $pays),
            ]),
            ParcoursPublication::ServiceOffre => $publication->detailService()->updateOrCreate(['publication_id' => $publication->id], [
                'metier' => $this->metier((int) $donnees['categorie_id']),
                'tarif_indicatif' => $donnees['tarif_indicatif'] ?? null,
                'unite_tarif' => $donnees['unite_tarif'],
                'zones_intervention' => array_values(array_map('intval', Arr::wrap($donnees['zones_intervention'] ?? []))),
                'disponibilite' => $donnees['disponibilite'] ?? null,
                'telephone_contact' => $this->telephones->normaliser((string) $donnees['telephone_contact'], $pays),
                'afficher_telephone' => (bool) ($donnees['afficher_telephone'] ?? false),
                'whatsapp' => ! empty($donnees['whatsapp_meme_numero'])
                    ? $this->telephones->normaliser((string) $donnees['telephone_contact'], $pays)
                    : $this->telephones->normaliser($donnees['whatsapp'] ?? null, $pays),
            ]),
            // Demande : numéro réservé aux prestataires qui ont répondu (D-071), même numéro pour WhatsApp.
            ParcoursPublication::ServiceDemande => $publication->detailService()->updateOrCreate(['publication_id' => $publication->id], [
                'metier' => $this->metier((int) $donnees['categorie_id']),
                'afficher_telephone' => $partage = ! empty($donnees['afficher_telephone']) && ! empty($donnees['telephone_contact']),
                'telephone_contact' => $partage ? $this->telephones->normaliser((string) $donnees['telephone_contact'], $pays) : null,
                'whatsapp' => $partage ? $this->telephones->normaliser((string) $donnees['telephone_contact'], $pays) : null,
                'date_souhaitee' => $this->dateSouhaitee($donnees),
                'budget' => $donnees['budget'] ?? null,
            ]),
            ParcoursPublication::Formation => $publication->detailFormation()->updateOrCreate(['publication_id' => $publication->id], [
                'organisme' => $publication->entreprise_id === null ? ($donnees['organisme'] ?? null) : null,
                'modalite' => $donnees['modalite'],
                'duree' => $donnees['duree'] ?? null,
                'date_debut' => $donnees['date_debut'] ?? null,
                'prix' => (int) ($donnees['prix'] ?? 0),
                'certifiante' => (bool) ($donnees['certifiante'] ?? false),
                'places' => $donnees['places'] ?? null,
                'mode_inscription' => $donnees['mode_inscription'],
                'contact_inscription' => $this->contact(ModeInscription::from((string) $donnees['mode_inscription'])->value, $donnees['contact_inscription'] ?? null, $pays),
            ]),
        };

        $publication->load(['detailEmploi', 'detailService', 'detailFormation']);
        $this->faitsCles->appliquer($publication);
        $publication->save();
    }

    /**
     * Publication non enregistrée pour l'aperçu : même carte que dans le fil, sans rien écrire en base.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function apercu(ParcoursPublication $parcours, array $donnees, Utilisateur $auteur): Publication
    {
        $entreprise = null;
        $choix = $donnees['employeur'] ?? $donnees['organisateur'] ?? null;

        if ($choix === 'entreprise' && isset($donnees['entreprise_id'])) {
            $entreprise = $auteur->entreprises()->whereKey((int) $donnees['entreprise_id'])->first();
        } elseif ($choix === 'nouvelle') {
            $entreprise = new Entreprise(['nom' => (string) $donnees['entreprise_nom']]);
        }

        $publication = new Publication;
        $this->remplir($publication, $parcours, $donnees, $auteur, $entreprise);
        $publication->publiee_le = now();
        $publication->tri_le = now();
        $publication->nb_jaime = 0;
        $publication->nb_commentaires = 0;

        $pays = $auteur->pays_code;
        $detail = match ($parcours) {
            ParcoursPublication::Emploi => new DetailEmploi([
                'type_contrat' => $donnees['type_contrat'],
                'nb_postes' => (int) ($donnees['nb_postes'] ?? 1),
                'salaire_a_negocier' => (bool) ($donnees['salaire_a_negocier'] ?? false),
                'salaire_min' => $donnees['salaire_min'] ?? null,
                'salaire_max' => $donnees['salaire_max'] ?? null,
                'date_limite' => $donnees['date_limite'] ?? null,
            ]),
            ParcoursPublication::ServiceOffre => new DetailService([
                'metier' => $this->metier((int) $donnees['categorie_id']),
                'tarif_indicatif' => $donnees['tarif_indicatif'] ?? null,
                'unite_tarif' => $donnees['unite_tarif'],
                'disponibilite' => $donnees['disponibilite'] ?? null,
                'telephone_contact' => $this->telephones->normaliser((string) $donnees['telephone_contact'], $pays),
            ]),
            ParcoursPublication::ServiceDemande => new DetailService([
                'metier' => $this->metier((int) $donnees['categorie_id']),
                'date_souhaitee' => $this->dateSouhaitee($donnees),
                'budget' => $donnees['budget'] ?? null,
            ]),
            ParcoursPublication::Formation => new DetailFormation([
                'modalite' => $donnees['modalite'],
                'duree' => $donnees['duree'] ?? null,
                'prix' => (int) ($donnees['prix'] ?? 0),
                'certifiante' => (bool) ($donnees['certifiante'] ?? false),
            ]),
        };

        $publication->faits_cles = $this->faitsCles->calculer($publication, $detail);

        return $publication;
    }

    /**
     * Valeurs d'une publication existante pour pré-remplir le formulaire de modification.
     *
     * @return array<string, mixed>
     */
    public function valeursFormulaire(Publication $publication): array
    {
        $publication->loadMissing(['detailEmploi', 'detailService', 'detailFormation']);
        $valeurs = $publication->only(['titre', 'categorie_id', 'commune_id', 'contenu']);
        $valeurs['expire_le'] = $publication->expire_le?->toDateString();

        if ($emploi = $publication->detailEmploi) {
            $valeurs += $emploi->only(['nb_postes', 'salaire_min', 'salaire_max', 'salaire_a_negocier', 'experience_requise', 'niveau_etude', 'contact_candidature']);
            $valeurs['employeur'] = $publication->entreprise_id !== null ? 'entreprise' : 'particulier';
            $valeurs['entreprise_id'] = $publication->entreprise_id;
            $valeurs['type_contrat'] = $emploi->type_contrat?->value;
            $valeurs['mode_candidature'] = $emploi->mode_candidature->value;
            $valeurs['date_limite'] = $emploi->date_limite?->toDateString();
        }

        if ($service = $publication->detailService) {
            $valeurs += $service->only(['tarif_indicatif', 'disponibilite', 'telephone_contact', 'afficher_telephone', 'whatsapp', 'budget']);
            $valeurs['unite_tarif'] = $service->unite_tarif?->value;
            $valeurs['zones_intervention'] = $service->zones_intervention ?? [];
            $valeurs['whatsapp_meme_numero'] = $service->whatsapp !== null && $service->whatsapp === $service->telephone_contact;
            $valeurs['date_souhaitee_choix'] = $service->date_souhaitee !== null ? 'date' : 'flexible';
            $valeurs['date_souhaitee'] = $service->date_souhaitee?->toDateString();
        }

        if ($formation = $publication->detailFormation) {
            $valeurs += $formation->only(['organisme', 'duree', 'prix', 'certifiante', 'places', 'contact_inscription']);
            $valeurs['organisateur'] = $publication->entreprise_id !== null ? 'entreprise' : 'texte';
            $valeurs['entreprise_id'] = $publication->entreprise_id;
            $valeurs['modalite'] = $formation->modalite->value;
            $valeurs['mode_inscription'] = $formation->mode_inscription->value;
            $valeurs['date_debut'] = $formation->date_debut?->toDateString();
        }

        return $valeurs;
    }

    /**
     * Enregistre les photos envoyées. En modification, de nouvelles photos remplacent
     * les anciennes : le total ne dépasse jamais publication.photos_max.
     *
     * @param  list<UploadedFile>  $photos
     */
    public function ajouterPhotos(Publication $publication, array $photos, bool $remplacer = false): void
    {
        if ($photos === []) {
            return;
        }

        if ($remplacer) {
            $publication->clearMediaCollection('photos');
            $publication->forceFill(['image_url' => null, 'image_largeur' => null, 'image_hauteur' => null])->save();
        }

        foreach ($photos as $photo) {
            $publication->addMedia($photo)->toMediaCollection('photos');
        }
    }

    private function avatar(Utilisateur $auteur, ?Entreprise $entreprise): ?string
    {
        $url = $entreprise !== null
            ? $entreprise->getFirstMediaUrl('logo', 'avatar')
            : $auteur->getFirstMediaUrl('photo', 'avatar');

        return $url !== '' ? $url : null;
    }

    private function metier(int $categorieId): ?string
    {
        return Categorie::query()->whereKey($categorieId)->value('nom');
    }

    /**
     * Contact normalisé : numéro en E.164 pour le mode « téléphone », sinon tel quel.
     */
    private function contact(string $mode, mixed $valeur, string $pays): ?string
    {
        if (! is_string($valeur) || trim($valeur) === '' || $mode === 'rezohelp') {
            return null;
        }

        return $mode === 'telephone' ? $this->telephones->normaliser($valeur, $pays) : trim($valeur);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function dateSouhaitee(array $donnees): ?string
    {
        return match ($donnees['date_souhaitee_choix'] ?? null) {
            'aujourdhui' => now()->toDateString(),
            'semaine' => now()->endOfWeek()->toDateString(),
            'date' => isset($donnees['date_souhaitee']) ? (string) $donnees['date_souhaitee'] : null,
            default => null,
        };
    }
}
