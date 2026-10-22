<?php

namespace App\Actions\Veille;

use App\Enums\Modalite;
use App\Enums\ModeCandidature;
use App\Enums\ModeInscription;
use App\Enums\SourcePublication;
use App\Enums\SousTypeService;
use App\Enums\StatutLigneVeille;
use App\Enums\StatutPublication;
use App\Enums\TypePublication;
use App\Models\ImportVeille;
use App\Models\LigneImportVeille;
use App\Models\Parametre;
use App\Models\Publication;
use App\Models\SourceVeille;
use App\Services\CorrespondanceVeille;
use App\Services\FaitsCles;
use App\Support\IdentifiantsPublication;
use App\Support\LecteurCsvVeille;
use App\Support\UrlSource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Une ligne du fichier de la veille (CONTEXTE §12) : doublon écarté par l'empreinte de l'URL,
 * sinon publication en attente rattachée aux référentiels, avec un **résumé limité** et le lien
 * vers l'annonce d'origine (jamais le texte source complet). La mise en ligne éventuelle est
 * décidée après l'enrichissement IA (DeciderPublicationVeille).
 */
class ImporterLigneVeille
{
    /** Longueur maximale du résumé conservé. */
    public const int LONGUEUR_RESUME = 600;

    public const int LONGUEUR_TITRE = 150;

    public function __construct(
        private readonly CorrespondanceVeille $correspondance,
        private readonly FaitsCles $faitsCles,
    ) {}

    /**
     * @param  array<string, string>  $valeurs  colonnes lues par LecteurCsvVeille
     */
    public function executer(ImportVeille $import, int $numero, array $valeurs): LigneImportVeille
    {
        $intitule = Str::limit(Str::squish($valeurs['intitule'] ?? ''), self::LONGUEUR_TITRE, '');
        $lien = trim($valeurs['lien'] ?? '');
        $ligne = fn (StatutLigneVeille $statut, ?int $publicationId = null, ?string $raison = null): LigneImportVeille => LigneImportVeille::query()->create([
            'import_veille_id' => $import->id,
            'numero' => $numero,
            'statut' => $statut,
            'intitule' => $intitule !== '' ? $intitule : null,
            'lien' => $lien !== '' ? Str::limit($lien, 2048, '') : null,
            'publication_id' => $publicationId,
            'raison' => $raison,
        ]);

        $manquantes = array_filter(LecteurCsvVeille::OBLIGATOIRES, fn (string $cle): bool => trim($valeurs[$cle] ?? '') === '');
        if ($manquantes !== []) {
            return $ligne(StatutLigneVeille::Rejetee, raison: 'Colonne(s) vide(s) : '.implode(', ', $manquantes).'.');
        }

        $url = UrlSource::normaliser($lien);
        if ($url === null) {
            return $ligne(StatutLigneVeille::Rejetee, raison: 'Lien invalide.');
        }

        $type = $this->correspondance->type($valeurs['categorie']);
        if ($type === null) {
            return $ligne(StatutLigneVeille::Rejetee, raison: 'Catégorie inconnue : « '.$valeurs['categorie'].' ».');
        }

        $empreinte = UrlSource::empreinte($url);
        $existante = Publication::withTrashed()->where('source_empreinte', $empreinte)->first(['id', 'import_veille_id']);
        if ($existante !== null) {
            // Import rejoué (relance, nouvelle tentative) : l'annonce créée par ce même import reste « importée ».
            return $existante->import_veille_id === $import->id
                ? $ligne(StatutLigneVeille::Importee, $existante->id)
                : $ligne(StatutLigneVeille::Doublon, $existante->id, 'Annonce déjà importée.');
        }

        $source = $this->correspondance->source($url);
        if (! $source->actif) {
            return $ligne(StatutLigneVeille::Rejetee, raison: 'Source désactivée : '.$source->nom.'.');
        }

        try {
            return DB::transaction(function () use ($import, $valeurs, $type, $url, $lien, $source, $intitule, $ligne): LigneImportVeille {
                $publication = $this->creerPublication($import, $valeurs, $type, $lien, $url, $source, $intitule);
                $this->creerDetails($publication, $type, $valeurs, $lien);

                $publication->load(['detailEmploi', 'detailService', 'detailFormation']);
                $this->faitsCles->appliquer($publication);
                $publication->save();

                return $ligne(StatutLigneVeille::Importee, $publication->id);
            });
        } catch (UniqueConstraintViolationException) {
            // Deux imports simultanés de la même annonce : l'empreinte unique n'en laisse passer qu'un.
            return $ligne(StatutLigneVeille::Doublon, Publication::withTrashed()->where('source_empreinte', $empreinte)->value('id'), 'Annonce déjà importée.');
        }
    }

    /**
     * @param  array<string, string>  $valeurs
     */
    private function creerPublication(ImportVeille $import, array $valeurs, TypePublication $type, string $lien, string $url, SourceVeille $source, string $intitule): Publication
    {
        $entreprise = Str::limit(Str::squish($valeurs['entreprise'] ?? ''), 150, '');
        $nomSource = $source->domaine === null ? (trim($valeurs['source'] ?? '') ?: UrlSource::hote($url)) : $source->nom;
        $resume = $this->resume($valeurs, $entreprise);

        $publication = new Publication([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'sous_type' => $type === TypePublication::Service ? SousTypeService::Offre : null,
            'titre' => $intitule,
            'slug' => IdentifiantsPublication::slug($intitule),
            'contenu' => $resume,
            'extrait' => IdentifiantsPublication::extrait($resume),
            'categorie_id' => $this->correspondance->categorie($type, $valeurs['domaine'] ?? ''),
            'commune_id' => $this->correspondance->commune($valeurs['lieu'] ?? ''),
            'auteur_nom_affiche' => $entreprise !== '' ? $entreprise : $nomSource,
            'source' => SourcePublication::VeilleIa,
            'source_nom' => Str::limit($nomSource, 100, ''),
            'source_url' => Str::limit($lien, 2048, ''),
            'source_empreinte' => UrlSource::empreinte($url),
            'source_veille_id' => $source->id,
            'import_veille_id' => $import->id,
            'expire_le' => now()->addDays(Parametre::entier('jours_expiration_defaut')),
            'code_court' => IdentifiantsPublication::codeCourt(),
        ]);
        $publication->statut = StatutPublication::EnAttente;
        $publication->save();

        return $publication;
    }

    /**
     * Résumé fourni par la veille, limité ; à défaut, une phrase neutre qui renvoie à l'annonce d'origine.
     *
     * @param  array<string, string>  $valeurs
     */
    private function resume(array $valeurs, string $entreprise): string
    {
        $resume = Str::squish($valeurs['resume'] ?? '');

        if ($resume === '') {
            $resume = trim(implode(' · ', array_filter([$entreprise, $valeurs['lieu'] ?? '', $valeurs['conditions'] ?? '']))).'. Tous les détails sur l’annonce d’origine.';
        }

        return Str::limit($resume, self::LONGUEUR_RESUME);
    }

    /**
     * @param  array<string, string>  $valeurs
     */
    private function creerDetails(Publication $publication, TypePublication $type, array $valeurs, string $lien): void
    {
        $conditions = $valeurs['conditions'] ?? '';
        $contact = Str::limit($lien, 255, '');

        match ($type) {
            TypePublication::Emploi => $publication->detailEmploi()->create([
                'type_contrat' => $this->correspondance->typeContrat($conditions),
                'mode_candidature' => ModeCandidature::Lien,
                'contact_candidature' => $contact,
            ]),
            TypePublication::Formation => $publication->detailFormation()->create([
                'organisme' => Str::limit($valeurs['entreprise'] ?? '', 150, '') ?: null,
                'modalite' => str_contains(Str::slug(($valeurs['lieu'] ?? '').' '.$conditions), 'en-ligne') ? Modalite::EnLigne : Modalite::Presentiel,
                'duree' => Str::limit(trim((string) preg_replace('/\s*[-–,]?\s*(gratuit|\d[\d\s.]*\s*(fcfa|f|xof)\b).*/iu', '', $conditions)), 100, '') ?: null,
                'prix' => $this->correspondance->montant($conditions) ?? 0,
                'mode_inscription' => ModeInscription::Lien,
                'contact_inscription' => $contact,
            ]),
            TypePublication::Service => $publication->detailService()->create([
                'metier' => Str::limit($valeurs['domaine'] ?? '', 100, '') ?: null,
                'tarif_indicatif' => $this->correspondance->montant($conditions) ?: null,
                'unite_tarif' => $this->correspondance->uniteTarif($conditions),
                'zones_intervention' => array_filter([$publication->commune_id]),
                // Aucun numéro tiré d'une annonce externe : le contact passe par le lien d'origine.
                'afficher_telephone' => false,
            ]),
        };
    }
}
