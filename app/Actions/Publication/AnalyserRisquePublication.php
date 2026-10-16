<?php

namespace App\Actions\Publication;

use App\Enums\SignalRisque;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Models\Parametre;
use Illuminate\Support\Str;

/**
 * Contrôles anti-arnaque synchrones, par règles simples (CONTEXTE §11.5).
 * Chaque règle déclenchée ajoute un signal et force la modération ;
 * la publication n'est jamais refusée automatiquement.
 */
class AnalyserRisquePublication
{
    /** Part de lettres en majuscules au-delà de laquelle le texte « crie ». */
    private const float SEUIL_MAJUSCULES = 0.5;

    /** En dessous, un texte est trop court pour juger de la casse. */
    private const int LETTRES_MINIMUM_MAJUSCULES = 20;

    private const array RACCOURCISSEURS = [
        'bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly', 'is.gd', 'cutt.ly', 'shorturl.at', 'rebrand.ly', 'tiny.cc', 'rb.gy', 'shorte.st',
    ];

    /**
     * @return list<string> codes SignalRisque
     */
    public function executer(
        TypePublication $type,
        string $titre,
        string $contenu,
        ?TypeContrat $typeContrat = null,
        ?int $salaireMaximum = null,
    ): array {
        $texte = $titre."\n".$contenu;
        $normalise = self::normaliser($texte);
        $signaux = [];

        if ($this->contientExpressionARisque($normalise)) {
            $signaux[] = SignalRisque::ExpressionARisque;
        }

        // Pour une offre d'emploi, le contact passe par le champ prévu, jamais par le texte.
        if ($type === TypePublication::Emploi) {
            if ($this->contientTelephone($texte)) {
                $signaux[] = SignalRisque::TelephoneDansTexte;
            }
            if (preg_match('/wa\.me\/|whatsapp\.com\/|chat\.whatsapp/i', $texte) === 1) {
                $signaux[] = SignalRisque::WhatsappDansTexte;
            }
        }

        if ($typeContrat !== null && $salaireMaximum !== null && $salaireMaximum > $this->plafond($typeContrat)) {
            $signaux[] = SignalRisque::SalaireHorsNorme;
        }

        if ($this->enMajuscules($texte)) {
            $signaux[] = SignalRisque::Majuscules;
        }

        if ($this->contientLienRaccourci($normalise)) {
            $signaux[] = SignalRisque::LienRaccourci;
        }

        return array_map(fn (SignalRisque $signal): string => $signal->value, $signaux);
    }

    /**
     * Minuscules, sans accents, apostrophes typographiques remplacées : « frais d’inscription » = « frais d'inscription ».
     */
    public static function normaliser(string $texte): string
    {
        return Str::lower(Str::ascii(str_replace(['’', '‘', '`'], "'", $texte)));
    }

    private function contientExpressionARisque(string $normalise): bool
    {
        foreach (Parametre::liste('mots_a_risque') as $expression) {
            if (! is_string($expression) || trim($expression) === '') {
                continue;
            }

            if (preg_match('/\b'.preg_quote(self::normaliser(trim($expression)), '/').'\b/u', $normalise) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Numéro international (+225, 00225…), national à 10 chiffres par paires
     * (07 08 09 10 11) ou suite de 9 chiffres et plus. Les dates et montants ne comptent pas.
     */
    private function contientTelephone(string $texte): bool
    {
        $motifs = [
            '/(?:\+|\b00)\s?\d{2,3}(?:[\s.\-]?\d){7,}/',
            '/\b0\d(?:[\s.\-]?\d{2}){4}\b/',
            '/\b\d{9,}\b/',
        ];

        foreach ($motifs as $motif) {
            if (preg_match($motif, $texte) === 1) {
                return true;
            }
        }

        return false;
    }

    private function plafond(TypeContrat $typeContrat): int
    {
        $plafonds = Parametre::liste('plafonds_salaire');
        $plafond = $plafonds[$typeContrat->value] ?? $plafonds['autre'] ?? null;

        return is_numeric($plafond) ? (int) $plafond : PHP_INT_MAX;
    }

    private function enMajuscules(string $texte): bool
    {
        $lettres = preg_replace('/[^\p{L}]/u', '', $texte) ?? '';
        $total = mb_strlen($lettres);

        if ($total < self::LETTRES_MINIMUM_MAJUSCULES) {
            return false;
        }

        $majuscules = mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $lettres) ?? '');

        return $majuscules / $total > self::SEUIL_MAJUSCULES;
    }

    private function contientLienRaccourci(string $normalise): bool
    {
        foreach (self::RACCOURCISSEURS as $domaine) {
            if (preg_match('/(?:^|[\s\/(])'.preg_quote($domaine, '/').'\//', $normalise) === 1) {
                return true;
            }
        }

        return false;
    }
}
