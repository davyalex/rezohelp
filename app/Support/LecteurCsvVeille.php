<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Lecture du fichier CSV déposé par la veille (CONTEXTE §12) : colonnes « Catégorie, Intitulé,
 * Entreprise/Organisme, Lieu, Contrat/Durée/Prix, Date publication, Domaine, Source, Lien, Résumé ».
 * Tolère un BOM, le séparateur « ; », des cellules sur plusieurs lignes et des en-têtes
 * sans accents ou dans un autre ordre.
 */
final class LecteurCsvVeille
{
    /** En-tête normalisé => clé de colonne. */
    private const array COLONNES = [
        'categorie' => 'categorie',
        'intitule' => 'intitule',
        'entreprise-organisme' => 'entreprise',
        'entreprise' => 'entreprise',
        'organisme' => 'entreprise',
        'lieu' => 'lieu',
        'contrat-duree-prix' => 'conditions',
        'date-publication' => 'date',
        'domaine' => 'domaine',
        'source' => 'source',
        'lien' => 'lien',
        'resume' => 'resume',
    ];

    /** Colonnes sans lesquelles une ligne ne peut pas être importée. */
    public const array OBLIGATOIRES = ['categorie', 'intitule', 'lien'];

    /**
     * @return list<array{numero: int, valeurs: array<string, string>}> numéro d'enregistrement (l'en-tête est le n° 1)
     */
    public static function lire(string $contenu): array
    {
        $contenu = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contenu);
        $premiereLigne = strtok($contenu, "\r\n");

        if ($premiereLigne === false || trim($premiereLigne) === '') {
            return [];
        }

        $separateur = substr_count($premiereLigne, ';') > substr_count($premiereLigne, ',') ? ';' : ',';
        $flux = fopen('php://temp', 'r+');

        if ($flux === false) {
            return [];
        }

        fwrite($flux, $contenu);
        rewind($flux);

        $entete = fgetcsv($flux, null, $separateur, '"', '') ?: [];
        // « Entreprise/Organisme » → « entreprise-organisme » (la barre oblique sépare deux mots).
        $colonnes = array_map(fn (?string $nom): ?string => self::COLONNES[Str::slug(str_replace(['/', '_'], ' ', (string) $nom))] ?? null, $entete);

        $resultat = [];
        $numero = 1;

        while (($cellules = fgetcsv($flux, null, $separateur, '"', '')) !== false) {
            $numero++;
            $valeurs = [];

            foreach ($colonnes as $rang => $cle) {
                if ($cle !== null && ! isset($valeurs[$cle])) {
                    $valeurs[$cle] = trim((string) ($cellules[$rang] ?? ''));
                }
            }

            if (implode('', $valeurs) !== '') {
                $resultat[] = ['numero' => $numero, 'valeurs' => $valeurs];
            }
        }

        fclose($flux);

        return $resultat;
    }
}
