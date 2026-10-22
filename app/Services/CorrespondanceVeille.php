<?php

namespace App\Services;

use App\Enums\NiveauConfiance;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Enums\UniteTarif;
use App\Models\SourceVeille;
use App\Support\UrlSource;
use Illuminate\Support\Str;

/**
 * Rattache une ligne de la veille aux référentiels (CONTEXTE §12) : type depuis la colonne
 * « Catégorie », catégorie fine depuis « Domaine », commune depuis « Lieu », source par domaine
 * (sous-domaines compris, sinon « Inconnue »), et lecture de « Contrat/Durée/Prix ».
 */
class CorrespondanceVeille
{
    public const string SOURCE_INCONNUE = 'Inconnue';

    /** Mots-outils et termes trop généraux pour désigner une catégorie. */
    private const array MOTS_IGNORES = [
        'les', 'des', 'aux', 'pour', 'par', 'sur', 'avec',
        'autre', 'autres', 'service', 'services', 'metier', 'metiers', 'petit', 'petits', 'stage',
        'reparation', 'reparations', 'installation', 'maintenance', 'direction', 'gestion', 'developpement',
        'conseil', 'entreprise', 'entreprises',
    ];

    /**
     * Synonymes des termes de la veille, par type : expression (en slug) => nom de la catégorie (en slug).
     *
     * @var array<string, array<string, string>>
     */
    private const array SYNONYMES = [
        'emploi' => [
            'it' => 'informatique-et-numerique', 'data' => 'informatique-et-numerique', 'reseaux' => 'informatique-et-numerique',
            'telecoms' => 'informatique-et-numerique', 'erp' => 'informatique-et-numerique', 'telematique' => 'informatique-et-numerique',
            'web' => 'informatique-et-numerique', 'digital' => 'informatique-et-numerique',
            'vente' => 'commerce-et-vente', 'b2b' => 'commerce-et-vente', 'prospection' => 'commerce-et-vente', 'distribution' => 'commerce-et-vente',
            'e-commerce' => 'commerce-et-vente', 'immobilier' => 'commerce-et-vente', 'televente' => 'commerce-et-vente',
            'telemarketing' => 'commerce-et-vente', 'centre-dappels' => 'commerce-et-vente', 'relation-client' => 'commerce-et-vente',
            'achats' => 'commerce-et-vente', 'cosmetiques' => 'commerce-et-vente',
            'comptabilite' => 'finance-et-comptabilite', 'audit' => 'finance-et-comptabilite', 'banque' => 'finance-et-comptabilite',
            'assurance' => 'finance-et-comptabilite', 'fiscalite' => 'finance-et-comptabilite', 'microfinance' => 'finance-et-comptabilite',
            'conformite' => 'finance-et-comptabilite', 'risques' => 'finance-et-comptabilite', 'controle-de-gestion' => 'finance-et-comptabilite',
            'mobile-money' => 'finance-et-comptabilite', 'statistique' => 'finance-et-comptabilite', 'economie' => 'finance-et-comptabilite',
            'fintech' => 'finance-et-comptabilite',
            'btp' => 'btp-et-genie-civil', 'travaux' => 'btp-et-genie-civil', 'marches-publics' => 'btp-et-genie-civil',
            'medical' => 'sante', 'pharmacie' => 'sante', 'infirmier' => 'sante',
            'langues' => 'education-et-enseignement', 'formateur' => 'education-et-enseignement',
            'tourisme' => 'hotellerie-et-restauration', 'voyages' => 'hotellerie-et-restauration', 'bien-etre' => 'hotellerie-et-restauration',
            'portuaire' => 'transport-et-logistique', 'stocks' => 'transport-et-logistique', 'chauffeur' => 'transport-et-logistique',
            'maritime' => 'transport-et-logistique',
            'rh' => 'administration-et-secretariat', 'ressources-humaines' => 'administration-et-secretariat', 'paie' => 'administration-et-secretariat',
            'assistanat' => 'administration-et-secretariat', 'juridique' => 'administration-et-secretariat', 'accueil' => 'administration-et-secretariat',
            'edition' => 'administration-et-secretariat',
            'design' => 'marketing-et-communication', 'graphisme' => 'marketing-et-communication', 'reseaux-sociaux' => 'marketing-et-communication',
            'evenementiel' => 'marketing-et-communication',
            'production' => 'industrie-et-production', 'qualite' => 'industrie-et-production', 'qhse' => 'industrie-et-production',
            'hse' => 'industrie-et-production', 'energie' => 'industrie-et-production', 'bureau-detudes' => 'industrie-et-production',
            'environnement' => 'industrie-et-production',
            'agroalimentaire' => 'agriculture-et-agroalimentaire', 'elevage' => 'agriculture-et-agroalimentaire',
            'surete' => 'securite-et-gardiennage',
        ],
        'service' => [
            'btp' => 'batiment-et-reparations', 'metallerie' => 'batiment-et-reparations', 'soudure' => 'batiment-et-reparations',
            'coffrage' => 'maconnerie',
            'froid' => 'climatisation',
            'garde-denfants' => 'nounou', 'petite-enfance' => 'nounou', 'baby-sitting' => 'nounou',
            'aide-a-la-personne' => 'maison-et-famille', 'conciergerie' => 'maison-et-famille',
            'entretien' => 'menage',
            'manutention' => 'demenagement',
            'patisserie' => 'cuisine-a-domicile', 'restauration' => 'traiteur',
            'blanchisserie' => 'lessive-et-repassage',
            'videosurveillance' => 'electronique-et-informatique', 'telecoms' => 'electronique-et-informatique',
            'reparation-informatique' => 'depannage-informatique',
            'courses' => 'livraison',
        ],
        'formation' => [
            'it' => 'informatique-et-numerique', 'data' => 'informatique-et-numerique', 'bureautique' => 'informatique-et-numerique',
            'numerique' => 'informatique-et-numerique',
            'btp' => 'metiers-techniques', 'conduite-dengins' => 'metiers-techniques', 'maritime' => 'metiers-techniques',
            'surete' => 'metiers-techniques', 'hse' => 'metiers-techniques', 'securite' => 'metiers-techniques',
            'electricite' => 'metiers-techniques', 'mecanique' => 'metiers-techniques', 'soudure' => 'metiers-techniques',
        ],
    ];

    public function __construct(private readonly Referentiels $referentiels) {}

    /**
     * « Offre d'emploi » → emploi, « Home service / petits métiers » → service, « Formation » → formation.
     */
    public function type(string $categorie): ?TypePublication
    {
        $texte = Str::slug($categorie);

        return match (true) {
            str_contains($texte, 'emploi') || str_contains($texte, 'recrutement') => TypePublication::Emploi,
            str_contains($texte, 'formation') => TypePublication::Formation,
            str_contains($texte, 'service') || str_contains($texte, 'metier') => TypePublication::Service,
            default => null,
        };
    }

    /**
     * Catégorie active du type qui correspond le mieux au domaine (D-064). Le domaine de la veille
     * combine souvent plusieurs termes (« Commerce / Vente », « IT / Réseaux ») : chaque terme est
     * comparé mot à mot au nom des catégories et aux synonymes du type (points()). Le meilleur total
     * l'emporte ; à égalité, la sous-catégorie (plus précise), puis le terme cité en premier. Sans correspondance : la catégorie « Autres… » du
     * type, s'il y en a une, sinon aucune.
     */
    public function categorie(TypePublication $type, string $domaine): ?int
    {
        $termes = array_values(array_filter(array_map(
            fn (string $terme): string => Str::slug($terme),
            preg_split('#[/,;|+&]#u', $domaine) ?: [],
        )));

        if ($termes === []) {
            return null;
        }

        $synonymes = self::SYNONYMES[$type->value];
        $meilleure = null;
        $meilleurRang = null;

        foreach ($this->referentiels->categories() as $categorie) {
            if ($categorie['type'] !== $type->value) {
                continue;
            }

            $nom = Str::slug($categorie['nom']);
            $motsDuNom = self::motsSignificatifs($nom);
            $score = 0;
            $premierTerme = PHP_INT_MAX;

            foreach ($termes as $position => $terme) {
                $points = self::points($terme, $nom, $motsDuNom, $synonymes);

                if ($points > 0) {
                    $score += $points;
                    $premierTerme = min($premierTerme, $position);
                }
            }

            if ($score === 0) {
                continue;
            }

            $rang = [$score, $categorie['parent_id'] !== null ? 1 : 0, -$premierTerme];

            if ($meilleurRang === null || $rang > $meilleurRang) {
                $meilleurRang = $rang;
                $meilleure = $categorie['id'];
            }
        }

        return $meilleure ?? $this->categorieAutre($type);
    }

    /**
     * Points d'un terme du domaine pour une catégorie : la meilleure des correspondances, sans cumul.
     * 3 : nom identique, terme d'un seul mot présent dans le nom (« Commerce »), ou terme qui est à
     * lui seul un synonyme (« IT », « RH ») ; 2 par mot commun sinon ; 1 si le terme contient un synonyme.
     *
     * @param  list<string>  $motsDuNom
     * @param  array<string, string>  $synonymes
     */
    private static function points(string $terme, string $nom, array $motsDuNom, array $synonymes): int
    {
        if ($terme === $nom) {
            return 3;
        }

        $mots = self::motsSignificatifs($terme);
        $communs = count(array_filter($mots, function (string $mot) use ($motsDuNom): bool {
            foreach ($motsDuNom as $motDuNom) {
                if (self::memeMot($mot, $motDuNom)) {
                    return true;
                }
            }

            return false;
        }));
        $points = count($mots) === 1 && $communs === 1 ? 3 : 2 * $communs;

        foreach ($synonymes as $expression => $cible) {
            if ($cible === $nom && $terme === $expression) {
                $points = max($points, 3);
            } elseif ($cible === $nom && str_contains('-'.$terme.'-', '-'.$expression.'-')) {
                $points = max($points, 1);
            }
        }

        return $points;
    }

    /**
     * Catégorie principale « Autres… » du type (emploi : « Autres métiers »).
     */
    private function categorieAutre(TypePublication $type): ?int
    {
        foreach ($this->referentiels->categories() as $categorie) {
            if ($categorie['type'] === $type->value && $categorie['parent_id'] === null && str_starts_with(Str::slug($categorie['nom']), 'autre')) {
                return $categorie['id'];
            }
        }

        return null;
    }

    /**
     * Mots porteurs de sens d'un texte déjà en slug : sans mots-outils ni termes trop généraux.
     *
     * @return list<string>
     */
    private static function motsSignificatifs(string $slug): array
    {
        return array_values(array_filter(
            explode('-', $slug),
            fn (string $mot): bool => mb_strlen($mot) >= 3 && ! in_array($mot, self::MOTS_IGNORES, true),
        ));
    }

    /**
     * Même mot au pluriel ou sous une autre forme : « commerce » / « commercial »,
     * « comptable » / « comptabilite », « auto » / « automobile » ; mais pas
     * « electricite » / « electronique » ni « menage » / « demenagement ».
     */
    private static function memeMot(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        [$court, $long] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];

        // Forme allongée (« hotel » / « hotellerie »), mais pas un mot composé (« video » / « videosurveillance »).
        if (strlen($court) >= 4 && strlen($long) - strlen($court) <= 6 && str_starts_with($long, $court)) {
            return true;
        }

        // Longueur du préfixe commun (les slugs sont en ASCII).
        $commun = 0;
        while ($commun < strlen($court) && $court[$commun] === $long[$commun]) {
            $commun++;
        }

        return $commun >= 6 && $commun >= 0.75 * strlen($court);
    }

    /**
     * Catégorie active du type portant exactement ce nom (catégorie fine proposée par l'IA).
     */
    public function categorieParNom(TypePublication $type, string $nom): ?int
    {
        foreach ($this->referentiels->categories() as $categorie) {
            if ($categorie['type'] === $type->value && Str::slug($categorie['nom']) === Str::slug($nom)) {
                return $categorie['id'];
            }
        }

        return null;
    }

    /**
     * Première commune active citée dans le lieu (« Abidjan - Cocody, Riviera » → Cocody).
     */
    public function commune(string $lieu): ?int
    {
        $texte = '-'.Str::slug($lieu).'-';

        foreach ($this->referentiels->communes() as $commune) {
            if (str_contains($texte, '-'.$commune['slug'].'-')) {
                return $commune['id'];
            }
        }

        return null;
    }

    /**
     * Source de veille d'une URL normalisée : domaine exact ou sous-domaine, sinon « Inconnue » (confiance basse).
     */
    public function source(string $urlNormalisee): SourceVeille
    {
        $hote = UrlSource::hote($urlNormalisee);

        $source = SourceVeille::query()
            ->whereNotNull('domaine')
            ->get(['id', 'nom', 'domaine', 'niveau_confiance', 'publication_auto', 'actif'])
            ->filter(fn (SourceVeille $s): bool => $hote === $s->domaine || str_ends_with($hote, '.'.$s->domaine))
            ->sortByDesc(fn (SourceVeille $s): int => strlen((string) $s->domaine))
            ->first();

        return $source ?? SourceVeille::query()->firstOrCreate(
            ['nom' => self::SOURCE_INCONNUE, 'domaine' => null],
            ['niveau_confiance' => NiveauConfiance::Bas, 'publication_auto' => false, 'actif' => true],
        );
    }

    /**
     * Contrat cité dans « Contrat/Durée/Prix » ; le premier cité l'emporte (« CDD / CDI » → CDD).
     * Rien de reconnu (« Non précisé », « Temps plein ») : aucun contrat, plutôt qu'un « Autre » vide de sens.
     */
    public function typeContrat(string $conditions): ?TypeContrat
    {
        $texte = '-'.Str::slug($conditions).'-';
        $motsCles = [
            '-cdi-' => TypeContrat::Cdi,
            '-cdd-' => TypeContrat::Cdd,
            '-stage' => TypeContrat::Stage,
            '-interim' => TypeContrat::Interim,
            'freelance' => TypeContrat::Freelance,
            'consultan' => TypeContrat::Freelance,
            '-mission' => TypeContrat::Freelance,
            'independant' => TypeContrat::Freelance,
        ];

        $premier = null;
        $position = PHP_INT_MAX;

        foreach ($motsCles as $motCle => $contrat) {
            $trouve = strpos($texte, $motCle);

            if ($trouve !== false && $trouve < $position) {
                $position = $trouve;
                $premier = $contrat;
            }
        }

        return $premier;
    }

    /**
     * Salaire mensuel cité : « 200 000 à 300 000 F CFA » → [200 000, 300 000], « 150 000 F CFA » →
     * [150 000, 150 000], « moins de 150 000 F CFA » → [null, 150 000]. Une prime n'est pas un salaire.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public function salaire(string $conditions): array
    {
        $nombre = '(\d{1,3}(?:[\s.\x{00A0}\x{202F}]\d{3})+|\d+)';
        $devise = '\s*(?:f\s?cfa|fcfa|f\b|xof)';
        $entier = fn (string $texte): int => (int) preg_replace('/\D/', '', $texte);

        if (preg_match('/'.$nombre.'\s*(?:à|a|-|–)\s*'.$nombre.$devise.'/iu', $conditions, $plage) === 1) {
            return [$entier($plage[1]), $entier($plage[2])];
        }

        // Le montant commence par son premier groupe de chiffres (pas « 000 » de « 150 000 »).
        if (preg_match('/(?<![\d.])(?<!\d\s)'.$nombre.$devise.'/iu', $conditions, $seul, PREG_OFFSET_CAPTURE) !== 1) {
            return [null, null];
        }

        // Les quelques mots qui précèdent le montant : « prime mensuelle de », « moins de ».
        $avant = Str::slug(mb_substr(substr($conditions, 0, $seul[0][1]), -25));

        if (str_contains($avant, 'prime')) {
            return [null, null];
        }

        $montant = $entier($seul[1][0]);

        return str_contains($avant, 'moins-de') ? [null, $montant] : [$montant, $montant];
    }

    /**
     * Montant en FCFA cité dans le texte (« 5 000 FCFA », « 150.000 F ») ; 0 si « gratuit ».
     */
    public function montant(string $conditions): ?int
    {
        if (str_contains(Str::slug($conditions), 'gratuit')) {
            return 0;
        }

        if (preg_match('/(\d{1,3}(?:[\s.\x{00A0}\x{202F}]\d{3})+|\d+)\s*(?:fcfa|f\b|xof)/iu', $conditions, $resultat) === 1) {
            return (int) preg_replace('/\D/', '', $resultat[1]);
        }

        return null;
    }

    public function uniteTarif(string $conditions): ?UniteTarif
    {
        $texte = Str::slug($conditions);

        return match (true) {
            str_contains($texte, 'heure') => UniteTarif::Heure,
            str_contains($texte, 'jour') => UniteTarif::Jour,
            str_contains($texte, 'prestation') || str_contains($texte, 'intervention') => UniteTarif::Prestation,
            str_contains($texte, 'devis') => UniteTarif::Devis,
            default => null,
        };
    }
}
