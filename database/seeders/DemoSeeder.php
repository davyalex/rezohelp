<?php

namespace Database\Seeders;

use App\Enums\FrequenceAlerte;
use App\Enums\Modalite;
use App\Enums\ModeCandidature;
use App\Enums\ModeInscription;
use App\Enums\MotifSignalement;
use App\Enums\SourcePublication;
use App\Enums\SousTypeService;
use App\Enums\StatutPublication;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Enums\UniteTarif;
use App\Models\Alerte;
use App\Models\Categorie;
use App\Models\Commentaire;
use App\Models\Commune;
use App\Models\Entreprise;
use App\Models\Publication;
use App\Models\Reaction;
use App\Models\Signalement;
use App\Models\SourceVeille;
use App\Models\Utilisateur;
use App\Services\FaitsCles;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Données de démonstration (local uniquement) : 20 membres, des entreprises,
 * 60 publications réparties sur les 3 types et les communes, des alertes et des réactions,
 * une file de 20 annonces à modérer et quelques signalements pour le back-office.
 *
 * php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: TypeContrat, 3: int}> titre, catégorie, contrat, salaire min */
    private const array EMPLOIS = [
        ['Comptable confirmé', 'Finance et comptabilité', TypeContrat::Cdi, 350000],
        ['Commerciaux terrain (10 postes)', 'Commerce et vente', TypeContrat::Cdd, 150000],
        ['Développeur web Laravel', 'Informatique et numérique', TypeContrat::Cdi, 600000],
        ['Stagiaire assistant(e) de direction', 'Administration et secrétariat', TypeContrat::Stage, 75000],
        ['Infirmier(ère) diplômé(e) d’État', 'Santé', TypeContrat::Cdd, 300000],
        ['Chef de chantier BTP', 'BTP et génie civil', TypeContrat::Cdi, 500000],
        ['Caissière pour supermarché', 'Commerce et vente', TypeContrat::Cdd, 120000],
        ['Chauffeur livreur permis C', 'Transport et logistique', TypeContrat::Cdi, 180000],
        ['Professeur de mathématiques (lycée)', 'Éducation et enseignement', TypeContrat::Cdd, 250000],
        ['Serveurs et serveuses', 'Hôtellerie et restauration', TypeContrat::Interim, 90000],
        ['Community manager', 'Marketing et communication', TypeContrat::Freelance, 200000],
        ['Agent de sécurité de nuit', 'Sécurité et gardiennage', TypeContrat::Cdi, 110000],
        ['Technicien de maintenance industrielle', 'Industrie et production', TypeContrat::Cdi, 280000],
        ['Responsable des ressources humaines', 'Administration et secrétariat', TypeContrat::Cdi, 700000],
        ['Téléconseillers bilingues', 'Commerce et vente', TypeContrat::Cdd, 160000],
        ['Analyste de données junior', 'Informatique et numérique', TypeContrat::Stage, 100000],
        ['Magasinier', 'Transport et logistique', TypeContrat::Cdd, 130000],
        ['Pharmacien assistant', 'Santé', TypeContrat::Cdi, 450000],
        ['Agronome pour plantation de cacao', 'Agriculture et agroalimentaire', TypeContrat::Cdd, 350000],
        ['Secrétaire comptable', 'Finance et comptabilité', TypeContrat::Cdi, 200000],
        ['Cuisinier(ère) pour restaurant', 'Hôtellerie et restauration', TypeContrat::Cdi, 150000],
        ['Électricien bâtiment', 'BTP et génie civil', TypeContrat::Interim, 170000],
        ['Nounou à domicile', 'Autres métiers', TypeContrat::Autre, 70000],
        ['Gestionnaire de stock', 'Transport et logistique', TypeContrat::Cdi, 250000],
        ['Graphiste', 'Marketing et communication', TypeContrat::Freelance, 220000],
    ];

    /** @var list<array{0: string, 1: string, 2: int, 3: UniteTarif}> métier, sous-catégorie, tarif, unité */
    private const array SERVICES = [
        ['Électricien', 'Électricité', 5000, UniteTarif::Prestation],
        ['Plombier', 'Plomberie', 5000, UniteTarif::Prestation],
        ['Ménagère', 'Ménage', 3000, UniteTarif::Jour],
        ['Nounou', 'Nounou', 60000, UniteTarif::Prestation],
        ['Chauffeur privé', 'Chauffeur', 15000, UniteTarif::Jour],
        ['Mécanicien auto', 'Mécanique', 10000, UniteTarif::Prestation],
        ['Couturier', 'Couture', 7000, UniteTarif::Prestation],
        ['Peintre en bâtiment', 'Peinture', 2500, UniteTarif::Heure],
        ['Menuisier', 'Menuiserie', 0, UniteTarif::Devis],
        ['Frigoriste (climatisation)', 'Climatisation', 15000, UniteTarif::Prestation],
        ['Coiffeuse à domicile', 'Coiffure', 5000, UniteTarif::Prestation],
        ['Réparateur de téléphones', 'Réparation de téléphones', 3000, UniteTarif::Prestation],
        ['Traiteur', 'Traiteur', 0, UniteTarif::Devis],
        ['Maçon', 'Maçonnerie', 10000, UniteTarif::Jour],
        ['Répétiteur primaire et collège', 'Répétiteur', 20000, UniteTarif::Prestation],
    ];

    /** @var list<array{0: string, 1: string}> besoin, sous-catégorie */
    private const array DEMANDES = [
        ['Cherche plombier pour fuite d’eau', 'Plomberie'],
        ['Cherche nounou pour deux enfants', 'Nounou'],
        ['Cherche électricien pour installation de prises', 'Électricité'],
        ['Cherche ménagère trois jours par semaine', 'Ménage'],
        ['Cherche mécanicien pour vidange à domicile', 'Mécanique'],
    ];

    /** @var list<array{0: string, 1: string, 2: int, 3: string}> titre, catégorie, prix, durée */
    private const array FORMATIONS = [
        ['Bootcamp développeur web', 'Informatique et numérique', 0, '3 mois'],
        ['Anglais professionnel', 'Langues', 75000, '2 mois'],
        ['Comptabilité SYSCOHADA', 'Gestion, comptabilité et finance', 150000, '6 semaines'],
        ['Électricité bâtiment (CAP)', 'Métiers techniques', 120000, '6 mois'],
        ['Aide-soignant(e)', 'Santé et social', 200000, '9 mois'],
        ['Marketing digital', 'Commerce et marketing', 50000, '1 mois'],
        ['Maraîchage moderne', 'Agriculture', 25000, '2 semaines'],
        ['Prise de parole en public', 'Développement personnel', 30000, '2 jours'],
        ['Excel avancé', 'Informatique et numérique', 40000, '1 semaine'],
        ['Création d’entreprise', 'Gestion, comptabilité et finance', 0, '3 jours'],
        ['Couture et stylisme', 'Métiers techniques', 90000, '4 mois'],
        ['Chinois débutant', 'Langues', 60000, '3 mois'],
        ['Cybersécurité : les bases', 'Informatique et numérique', 100000, '1 mois'],
        ['Secourisme (PSC1)', 'Santé et social', 15000, '1 jour'],
        ['Vente et négociation', 'Commerce et marketing', 45000, '1 semaine'],
    ];

    /** @var Collection<int, Commune> */
    private Collection $communes;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le DemoSeeder ne doit jamais être lancé en production.');
        }

        $this->communes = Commune::query()->get();

        if ($this->communes->isEmpty()) {
            throw new RuntimeException('Lancez d’abord php artisan migrate --seed (référentiels).');
        }

        $membres = Utilisateur::factory()->count(20)->create()
            ->each(fn (Utilisateur $membre) => $membre->update(['commune_id' => $this->communes->random()->id]));
        $membres->each(fn (Utilisateur $membre) => $membre->assignRole('membre'));

        $entreprises = $membres->take(8)->map(fn (Utilisateur $proprietaire): Entreprise => Entreprise::factory()->create([
            'proprietaire_id' => $proprietaire->id,
            'commune_id' => $this->communes->random()->id,
        ]));
        $entreprises->take(3)->each(fn (Entreprise $entreprise) => $entreprise->forceFill(['verifiee' => true])->save());

        $publications = collect();

        foreach (self::EMPLOIS as $i => [$titre, $categorie, $contrat, $salaire]) {
            $entreprise = $entreprises[$i % $entreprises->count()];
            $publication = $this->publier(TypePublication::Emploi, null, $titre, $categorie, $entreprise->proprietaire_id, $entreprise, $i);
            $publication->detailEmploi()->create([
                'type_contrat' => $contrat,
                'nb_postes' => str_contains($titre, '10 postes') ? 10 : fake()->numberBetween(1, 3),
                'salaire_min' => $salaire,
                'salaire_max' => $salaire + 50000,
                'salaire_a_negocier' => $i % 5 === 0,
                'experience_requise' => fake()->randomElement(['Débutant accepté', '1 à 2 ans', '3 à 5 ans']),
                'niveau_etude' => fake()->randomElement(['BEPC', 'BAC', 'BTS', 'Licence', 'Master']),
                'date_limite' => now()->addDays(fake()->numberBetween(7, 30)),
                'mode_candidature' => $i % 3 === 0 ? ModeCandidature::Email : ModeCandidature::Resohelph,
                'contact_candidature' => $i % 3 === 0 ? 'recrutement@'.Str::slug($entreprise->nom).'.ci' : null,
            ]);
            $publications->push($publication);
        }

        foreach (self::SERVICES as $i => [$metier, $categorie, $tarif, $unite]) {
            $artisan = $membres[8 + ($i % 8)];
            $commune = $this->communes[$i % $this->communes->count()];
            $publication = $this->publier(TypePublication::Service, SousTypeService::Offre, "{$metier} disponible à {$commune->nom}", $categorie, $artisan->id, null, $i, $commune);
            $publication->detailService()->create([
                'metier' => $metier,
                'tarif_indicatif' => $tarif ?: null,
                'unite_tarif' => $unite,
                'zones_intervention' => $this->communes->random(3)->pluck('id')->all(),
                'disponibilite' => fake()->randomElement(['Tous les jours', 'Du lundi au samedi', 'Le week-end']),
                'telephone_contact' => $artisan->telephone,
                'afficher_telephone' => $i % 4 !== 0,
                'whatsapp' => $artisan->telephone,
            ]);
            $artisan->assignRole('artisan');
            $publications->push($publication);
        }

        foreach (self::DEMANDES as $i => [$titre, $categorie]) {
            $client = $membres[16 + ($i % 4)];
            $commune = $this->communes->random();
            $publication = $this->publier(TypePublication::Service, SousTypeService::Demande, "{$titre} à {$commune->nom}", $categorie, $client->id, null, $i, $commune);
            $publication->detailService()->create([
                'metier' => $categorie,
                'afficher_telephone' => false,
                'date_souhaitee' => now()->addDays(fake()->numberBetween(0, 7)),
                'budget' => fake()->randomElement([null, 10000, 25000, 50000]),
            ]);
            $publications->push($publication);
        }

        $veille = SourceVeille::query()->where('domaine', 'orangedigitalcenters.com')->first();

        foreach (self::FORMATIONS as $i => [$titre, $categorie, $prix, $duree]) {
            $issueDeLaVeille = $i % 5 === 0;
            $entreprise = $entreprises[($i + 3) % $entreprises->count()];
            $publication = $this->publier(
                TypePublication::Formation, null, $titre, $categorie,
                $issueDeLaVeille ? null : $entreprise->proprietaire_id,
                $issueDeLaVeille ? null : $entreprise,
                $i,
                source: $issueDeLaVeille ? $veille : null,
            );
            $modalite = fake()->randomElement(Modalite::cases());
            $publication->detailFormation()->create([
                'organisme' => $issueDeLaVeille ? 'Orange Digital Center' : $entreprise->nom,
                'modalite' => $modalite,
                'duree' => $duree,
                'date_debut' => now()->addDays(fake()->numberBetween(5, 45)),
                'prix' => $prix,
                'certifiante' => $i % 2 === 0,
                'places' => fake()->randomElement([null, 15, 20, 30]),
                'mode_inscription' => $issueDeLaVeille ? ModeInscription::Lien : ModeInscription::Resohelph,
                'contact_inscription' => $issueDeLaVeille ? $publication->source_url : null,
            ]);
            if ($modalite === Modalite::EnLigne) {
                $publication->forceFill(['commune_id' => null])->save();
            }
            $publications->push($publication);
        }

        $publications = $publications->concat($this->fileDeModeration($membres));

        $this->denormaliser($publications);
        $this->interagir($publications, $membres);
        $this->creerAlertes($membres);
        $this->signaler($publications, $membres);
    }

    /**
     * 20 annonces de services en attente, avec des scores et des signaux variés, pour modérer
     * au clavier dans le back-office (critère d'acceptation du lot 7).
     *
     * @param  Collection<int, Utilisateur>  $membres
     * @return \Illuminate\Support\Collection<int, Publication>
     */
    private function fileDeModeration(Collection $membres): \Illuminate\Support\Collection
    {
        $signaux = [[], [], ['expression_a_risque'], ['telephone_dans_texte'], ['majuscules', 'lien_raccourci'], ['salaire_hors_norme']];

        return collect(range(0, 19))->map(function (int $i) use ($membres, $signaux): Publication {
            [$metier, $categorie, $tarif, $unite] = self::SERVICES[$i % count(self::SERVICES)];
            $commune = $this->communes[($i * 3) % $this->communes->count()];
            $auteur = $membres[$i % $membres->count()];

            $publication = $this->publier(TypePublication::Service, SousTypeService::Offre, "{$metier} à {$commune->nom} (n° ".($i + 1).')', $categorie, $auteur->id, null, 6, $commune);
            $publication->detailService()->create([
                'metier' => $metier,
                'tarif_indicatif' => $tarif ?: null,
                'unite_tarif' => $unite,
                'zones_intervention' => [$commune->id],
                'telephone_contact' => $auteur->telephone,
                'afficher_telephone' => true,
            ]);
            $publication->forceFill([
                'statut' => StatutPublication::EnAttente,
                'score_fiabilite' => $i % 5 === 0 ? null : fake()->numberBetween(15, 95),
                'signaux_risque' => $signaux[$i % count($signaux)],
            ])->save();

            return $publication;
        });
    }

    /**
     * Trois signalements « arnaque » sur une publication en ligne, un autre sur une deuxième.
     *
     * @param  \Illuminate\Support\Collection<int, Publication>  $publications
     * @param  Collection<int, Utilisateur>  $membres
     */
    private function signaler(\Illuminate\Support\Collection $publications, Collection $membres): void
    {
        $enLigne = $publications->where('statut', StatutPublication::Publiee)->values();

        foreach ($membres->take(3) as $membre) {
            Signalement::query()->create(['publication_id' => $enLigne[0]->id, 'utilisateur_id' => $membre->id, 'motif' => MotifSignalement::Arnaque, 'commentaire' => 'On me demande des frais avant l’entretien.']);
        }

        Signalement::query()->create(['publication_id' => $enLigne[1]->id, 'utilisateur_id' => $membres[3]->id, 'motif' => MotifSignalement::Expiree, 'commentaire' => null]);
    }

    private function publier(
        TypePublication $type,
        ?SousTypeService $sousType,
        string $titre,
        string $categorie,
        ?int $auteurId,
        ?Entreprise $entreprise,
        int $rang,
        ?Commune $commune = null,
        ?SourceVeille $source = null,
    ): Publication {
        $categorieId = Categorie::query()->where('type', $type->value)->where('nom', $categorie)->value('id');
        $publieeLe = Carbon::now()->subMinutes(fake()->numberBetween(5, 60 * 24 * 12));
        $contenu = $this->contenu($type, $sousType, $titre);
        $auteur = $auteurId !== null ? Utilisateur::query()->find($auteurId) : null;
        $url = $source !== null ? 'https://www.orangedigitalcenters.com/country/ci/formations/'.Str::slug($titre) : null;

        $publication = new Publication([
            'uuid' => (string) Str::uuid(),
            'type' => $type,
            'sous_type' => $sousType,
            'titre' => $titre,
            'slug' => Str::slug($titre).'-'.Str::lower(Str::random(6)),
            'contenu' => $contenu,
            'extrait' => Str::limit($contenu, 220),
            'categorie_id' => $categorieId,
            'commune_id' => ($commune ?? $this->communes->random())->id,
            'auteur_id' => $auteurId,
            'entreprise_id' => $entreprise?->id,
            'auteur_nom_affiche' => $entreprise->nom ?? $auteur?->nomAffiche() ?? $source?->nom,
            'source' => $source !== null ? SourcePublication::VeilleIa : SourcePublication::Utilisateur,
            'source_nom' => $source?->nom,
            'source_url' => $url,
            'source_empreinte' => $url !== null ? hash('sha256', $url) : null,
            'source_veille_id' => $source?->id,
            'expire_le' => $publieeLe->copy()->addDays(30),
            'code_court' => Str::lower(Str::random(6)),
        ]);

        // Quelques publications en attente de modération pour le back-office.
        $enAttente = $rang % 7 === 6;
        $publication->forceFill([
            'statut' => $enAttente ? StatutPublication::EnAttente : StatutPublication::Publiee,
            'publiee_le' => $enAttente ? null : $publieeLe,
            'tri_le' => $enAttente ? null : $publieeLe,
            'score_fiabilite' => $source !== null ? fake()->numberBetween(70, 95) : null,
            'cree_le' => $publieeLe,
        ])->save();

        return $publication;
    }

    private function contenu(TypePublication $type, ?SousTypeService $sousType, string $titre): string
    {
        return match (true) {
            $type === TypePublication::Emploi => "Nous recrutons : {$titre}. Vous êtes rigoureux, ponctuel et motivé. "
                .'Missions : accueil des clients, suivi des dossiers, reporting hebdomadaire. '
                .'Envoyez votre candidature avant la date limite. Aucun frais ne vous sera demandé.',
            $sousType === SousTypeService::Demande => "{$titre}. Intervention souhaitée rapidement, travail soigné attendu. "
                .'Merci de répondre si vous êtes disponible, en précisant votre tarif et vos disponibilités.',
            $type === TypePublication::Service => "{$titre}. Travail propre et garanti, plus de 5 ans d’expérience. "
                .'Déplacement dans plusieurs communes d’Abidjan. Devis gratuit, interventions rapides, 7 jours sur 7.',
            default => "Formation « {$titre} » : cours pratiques, formateurs expérimentés, petits groupes. "
                .'Attestation remise en fin de formation. Places limitées, inscrivez-vous dès maintenant.',
        };
    }

    /**
     * Champs lus par les cartes du fil : faits clés, type de contrat, entreprise vérifiée.
     *
     * @param  \Illuminate\Support\Collection<int, Publication>  $publications
     */
    private function denormaliser(\Illuminate\Support\Collection $publications): void
    {
        $faitsCles = app(FaitsCles::class);

        foreach ($publications as $publication) {
            $publication->load(['detailEmploi', 'detailService', 'detailFormation', 'entreprise']);
            $faitsCles->appliquer($publication);
            $publication->auteur_verifie = (bool) $publication->entreprise?->verifiee;
            $publication->save();
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Publication>  $publications
     * @param  Collection<int, Utilisateur>  $membres
     */
    private function interagir(\Illuminate\Support\Collection $publications, Collection $membres): void
    {
        foreach ($publications->where('statut', StatutPublication::Publiee) as $publication) {
            $fans = $membres->random(fake()->numberBetween(0, 8));
            foreach ($fans as $fan) {
                Reaction::factory()->create(['publication_id' => $publication->id, 'utilisateur_id' => $fan->id]);
            }

            $nbCommentaires = fake()->numberBetween(0, 3);
            for ($i = 0; $i < $nbCommentaires; $i++) {
                Commentaire::factory()->create([
                    'publication_id' => $publication->id,
                    'utilisateur_id' => $membres->random()->id,
                    'contenu' => fake()->randomElement([
                        'Toujours disponible ?',
                        'Intéressé, je vous écris.',
                        'Merci pour le partage !',
                        'Quel est le salaire exact ?',
                        'Je recommande, très sérieux.',
                    ]),
                ]);
            }

            $publication->forceFill([
                'nb_jaime' => $fans->count(),
                'nb_commentaires' => $nbCommentaires,
                'nb_vues' => fake()->numberBetween(10, 500),
                'nb_partages' => fake()->numberBetween(0, 20),
            ])->save();
        }
    }

    /**
     * @param  Collection<int, Utilisateur>  $membres
     */
    private function creerAlertes(Collection $membres): void
    {
        $cocody = $this->communes->firstWhere('slug', 'cocody');
        $electricite = Categorie::query()->where('type', TypePublication::Service->value)->where('nom', 'Électricité')->first();

        $membres->take(10)->each(function (Utilisateur $membre, int $i) use ($cocody, $electricite): void {
            $alerte = new Alerte([
                'nom' => $i === 0 ? 'Électricien · Cocody' : 'Alerte '.($i + 1),
                'type' => $i === 0 ? TypePublication::Service : fake()->randomElement([null, ...TypePublication::cases()]),
                'categorie_id' => $i === 0 ? $electricite?->id : null,
                'commune_id' => $i === 0 ? $cocody?->id : fake()->randomElement([null, $this->communes->random()->id]),
                'mots_cles' => $i % 3 === 0 && $i !== 0 ? fake()->randomElement(['comptable', 'stage', 'chauffeur']) : null,
                'frequence' => $i % 2 === 0 ? FrequenceAlerte::Instantanee : FrequenceAlerte::Quotidienne,
                'active' => true,
            ]);
            $membre->alertes()->save($alerte);
        });
    }
}
