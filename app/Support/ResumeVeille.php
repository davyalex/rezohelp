<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Lecture du résumé rédigé par la veille (D-067) :
 *
 * - **date de clôture** : « Clôture le 30/10 », « Jusqu'au 31/10 », « Candidatures jusqu'au 05/12 »,
 *   « avant le 02/11 », « Clôture immédiate (21/10) », « Expire aujourd'hui » ; l'année manquante est
 *   déduite de la date de publication de l'annonce ; une date incohérente (avant la publication,
 *   ou à plus de 6 mois) est ignorée ;
 * - **notes de la veille** (« Détail non ouvert ce passage, à vérifier », « Pas de date limite
 *   affichée », « (aussi sur Projobivoire) ») : retirées du texte public, gardées pour la modération ;
 * - les phrases qui ne font qu'annoncer la clôture sont retirées : la date s'affiche à part.
 */
final class ResumeVeille
{
    /** Expressions (minuscules, sans accents) qui signalent une note de la veille, pas un contenu de l'annonce. */
    private const array MARQUEURS_NOTE = [
        'a verifier', 'ce passage', 'pas de date limite', 'date limite affichee', 'date limite indiquee',
        'detail non ', 'details non ', 'incoheren', 'errone', 'texte de l\'annonce', 'aussi sur ',
    ];

    /** La veille signale une date douteuse : elle n'est pas retenue comme date de clôture. */
    private const array MARQUEURS_DATE_DOUTEUSE = ['incoheren', 'errone', 'anterieure'];

    /** Mot-clé suivi d'une date jj/mm[/aaaa], dans la même phrase. */
    private const string MOTIF_DATE = '/(?:cloture|date limite|jusqu\'?au|avant le|expire|candidatures?|inscriptions?)[^\d.]{0,30}?(\d{1,2})(?:-(\d{1,2}))?[\/.](\d{1,2})(?:[\/.](\d{2,4}))?/';

    /** Phrase qui ne contient que l'annonce de la clôture (« Clôture le 30/11 », « Candidatures ouvertes jusqu'au 30/11 »). */
    private const string MOTIF_PHRASE_CLOTURE = '/^(?:(?:candidatures?|inscriptions?)?\s*(?:ouvertes\s*)?(?:cloture(?: rapide| immediate| proche| imminente)?|date limite|jusqu\'?au|avant le)\b[^a-z]*(?:le|au|:)?\s*[\d\/.\s()-]*(?:environ)?|(?:expire|cloture) aujourd\'hui)\.?$/';

    /** Une date limite au-delà de cette durée après la publication est jugée incohérente. */
    private const int MOIS_MAXIMUM = 6;

    /** Tolérance pour une date limite légèrement antérieure à la date de publication déclarée. */
    private const int JOURS_TOLERANCE = 1;

    private function __construct(
        public readonly string $texte,
        public readonly ?string $notes,
        public readonly ?CarbonImmutable $dateLimite,
    ) {}

    /**
     * @param  string  $date  colonne « Date publication » (« 19/10/2026 », « 2026-10-15 », « 21-22/10/2026 »…)
     */
    public static function lire(string $resume, string $conditions = '', string $date = '', ?CarbonImmutable $aujourdhui = null): self
    {
        $aujourdhui ??= CarbonImmutable::today();
        $reference = self::datePublication($date) ?? $aujourdhui;

        $gardees = [];
        $notes = [];
        $dateLimite = null;

        foreach (self::phrases($resume) as $phrase) {
            $phrase = self::sansParenthesesDeNote($phrase, $notes);
            $normalisee = self::normaliser($phrase);

            // Une date que la veille juge elle-même douteuse n'est pas retenue.
            if (! Str::contains($normalisee, self::MARQUEURS_DATE_DOUTEUSE)) {
                $dateLimite ??= self::dateLimiteDans($normalisee, $reference);
            }

            if (self::estNote($normalisee)) {
                $notes[] = $phrase;
            } elseif (preg_match(self::MOTIF_PHRASE_CLOTURE, $normalisee) !== 1) {
                $gardees[] = $phrase;
            }
        }

        $dateLimite ??= self::dateLimiteDans(self::normaliser($conditions), $reference);

        return new self(
            texte: Str::squish(implode(' ', array_filter($gardees))),
            notes: $notes === [] ? null : implode(' ', $notes),
            dateLimite: $dateLimite,
        );
    }

    /**
     * Phrases, puis propositions séparées par « ; » (une note peut suivre une information utile).
     *
     * @return list<string>
     */
    private static function phrases(string $texte): array
    {
        $morceaux = [];

        foreach (preg_split('/(?<=[.!?])\s+/u', Str::squish($texte)) ?: [] as $phrase) {
            $propositions = preg_split('/\s*;\s*/u', $phrase) ?: [];

            foreach ($propositions as $i => $proposition) {
                $proposition = trim($proposition);

                if ($proposition === '' || $proposition === '.') {
                    continue;
                }

                // Chaque proposition devient une phrase complète, avec majuscule et point.
                if ($i > 0 || count($propositions) > 1) {
                    $proposition = rtrim(Str::ucfirst($proposition), '.').'.';
                }

                $morceaux[] = $proposition;
            }
        }

        return $morceaux;
    }

    private static function normaliser(string $texte): string
    {
        return Str::lower(Str::ascii(str_replace(['’', '‘'], "'", $texte)));
    }

    private static function estNote(string $normalisee): bool
    {
        return Str::contains($normalisee, self::MARQUEURS_NOTE);
    }

    /**
     * Retire les parenthèses qui sont des notes (« (aussi sur Projobivoire) ») et les garde à part.
     *
     * @param  list<string>  $notes
     */
    private static function sansParenthesesDeNote(string $phrase, array &$notes): string
    {
        return (string) preg_replace_callback('/\s*\(([^)]*)\)/u', function (array $parenthese) use (&$notes): string {
            if (! self::estNote(self::normaliser($parenthese[1]))) {
                return $parenthese[0];
            }

            $notes[] = '('.$parenthese[1].')';

            return '';
        }, $phrase);
    }

    private static function dateLimiteDans(string $normalisee, CarbonImmutable $reference): ?CarbonImmutable
    {
        if (preg_match('/(?:cloture|expire|date limite)[^.]{0,20}aujourd\'hui/', $normalisee) === 1) {
            return $reference;
        }

        if (preg_match(self::MOTIF_DATE, $normalisee, $date) !== 1) {
            return null;
        }

        // « Clôture le 30-31/10 » : le dernier jour de la plage.
        $jour = $date[2] !== '' ? (int) $date[2] : (int) $date[1];

        return self::dateCoherente($jour, (int) $date[3], isset($date[4]) ? (int) $date[4] : null, $reference);
    }

    /**
     * Date réelle, entre la publication (à un jour près) et MOIS_MAXIMUM mois après ;
     * sans année, l'année de la publication, ou la suivante (« 15/01 » publié en décembre).
     */
    private static function dateCoherente(int $jour, int $mois, ?int $annee, CarbonImmutable $reference): ?CarbonImmutable
    {
        $annees = match (true) {
            $annee === null => [$reference->year, $reference->year + 1],
            $annee < 100 => [2000 + $annee],
            default => [$annee],
        };

        foreach ($annees as $candidate) {
            if (! checkdate($mois, $jour, $candidate)) {
                continue;
            }

            $dateLimite = CarbonImmutable::create($candidate, $mois, $jour)?->startOfDay();

            if ($dateLimite !== null
                && $dateLimite->greaterThanOrEqualTo($reference->subDays(self::JOURS_TOLERANCE))
                && $dateLimite->lessThanOrEqualTo($reference->addMonths(self::MOIS_MAXIMUM))) {
                return $dateLimite;
            }
        }

        return null;
    }

    /**
     * Première date de la colonne « Date publication ».
     */
    private static function datePublication(string $date): ?CarbonImmutable
    {
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $date, $iso) === 1 && checkdate((int) $iso[2], (int) $iso[3], (int) $iso[1])) {
            return CarbonImmutable::create((int) $iso[1], (int) $iso[2], (int) $iso[3]);
        }

        // « 21-22/10/2026 » : le premier jour d'une plage.
        if (preg_match('/(\d{1,2})(?:-\d{1,2})?\/(\d{1,2})\/(\d{4})/', $date, $fr) === 1 && checkdate((int) $fr[2], (int) $fr[1], (int) $fr[3])) {
            return CarbonImmutable::create((int) $fr[3], (int) $fr[2], (int) $fr[1]);
        }

        return null;
    }
}
