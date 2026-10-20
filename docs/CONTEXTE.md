# Rezohelp — Document de contexte

> Plateforme d'emploi, de home service / petits métiers et de formation, présentée comme un fil d'actualité (façon Facebook).
> Cible : Abidjan, puis Côte d'Ivoire et Afrique de l'Ouest francophone.
> Auteur : Alex Kouamelan — Ingénieur DASI · Version 0.1 · 16/10/2026

---

## 1. Vision

Un seul fil, simple et rapide sur mobile, où l'on trouve en quelques secondes :

- **💼 Offres d'emploi** : CDI, CDD, stages, missions.
- **🔧 Home service / petits métiers** : plombier, électricien, ménagère, nounou, chauffeur, mécanicien, couturier…
- **🎓 Formations** : formations, certifications, bootcamps, apprentissages.

Le fil est alimenté de deux façons :

1. automatiquement, par la **veille IA** (toutes les 8 h, déposée dans Google Drive puis importée) ;
2. directement, par les **recruteurs, artisans et organismes** inscrits.

**Principes produit**

- La consultation est **gratuite et sans compte**.
- Un compte (téléphone + mot de passe) sert à créer des **alertes**, réagir, commenter, postuler et publier.
- Le site doit être **utilisable sur un téléphone d'entrée de gamme en 3G/4G** : c'est la contrainte n° 1.

---

## 2. Utilisateurs et rôles

| Rôle | Peut faire |
|---|---|
| Visiteur (sans compte) | Consulter le fil, filtrer, ouvrir une publication, partager, ouvrir le lien source |
| Membre (candidat / client) | + créer des alertes, recevoir des notifications, aimer, commenter, enregistrer, postuler |
| Recruteur | + publier des offres d'emploi au nom d'une entreprise, voir les candidatures reçues |
| Artisan / prestataire | + publier ses services, être contacté |
| Organisme de formation | + publier des formations, recevoir des inscriptions |
| Modérateur | Valider ou rejeter les publications en attente, traiter les signalements |
| Administrateur | Tout, y compris utilisateurs, sources de veille, catégories, statistiques |

Les rôles sont gérés avec **spatie/laravel-permission**. Un utilisateur peut cumuler plusieurs rôles.

---

## 3. Périmètre

### MVP (V1)

- Fil infini (sans pagination visible), filtres par catégorie, commune et recherche texte.
- Page détail d'une publication, partage WhatsApp, lien vers la source.
- Inscription et connexion par **numéro de téléphone + mot de passe** (multi-pays).
- Espace membre (« Mon espace ») : alertes, notifications, publications enregistrées, profil.
- Alertes : création, modification, pause, suppression ; notification dans l'espace membre.
- Réactions (J'aime), commentaires, enregistrement.
- Publication par tout membre en 4 parcours (je recrute, je propose mes services, je cherche un prestataire, je propose une formation), avec aperçu, contrôles anti-arnaque, modération selon le niveau de confiance, gestion de « Mes publications » et réponses (candidature, contact, proposition, inscription). Détail au §11.
- Import automatique de la veille IA depuis Google Drive, avec score de fiabilité.
- Back-office maison (Blade + Tailwind) : tableau de bord, modération, publications, signalements, veille IA, utilisateurs et entreprises, référentiels et paramètres, journal d'audit (écriture). Détail au §13.
- PWA installable, responsive, accessible.

- **Moteur d'abonnements dynamique** (§14) : plans, limites, essais, produits ponctuels, paiement CinetPay, reçus, SMS d'alertes, remontées et mises en avant. Tout est développé, puis **activé progressivement depuis l'admin**.

### V2 (hors MVP)

- Notifications Web Push (PWA) et WhatsApp pour les alertes.
- Codes promo, accès CVthèque.
- Candidature en ligne avec CV (pour les offres natives).
- Avis et notes sur les artisans.
- Publications sponsorisées et abonnements recruteurs payés en Mobile Money (Orange, MTN, Moov, Wave).
- Messagerie interne.
- Appel direct de l'API Claude par le scheduler, sans passer par Drive.
- Back-office : statistiques et export CSV, modération des commentaires, vue des alertes, consultation du journal d'audit, jobs échoués et santé du système, publications épinglées.

---

## 4. Parcours clés

1. **Consulter** : arrivée sur `/` → fil chargé immédiatement → défilement → les publications suivantes arrivent toutes seules → tap sur une carte → détail → « Postuler », « Contacter » ou « Voir l'annonce ».
2. **Créer une alerte (sans compte)** : bouton « Créer une alerte » → redirection vers `/inscription`, avec les filtres courants conservés → compte créé → alerte pré-remplie → confirmation.
3. **Recevoir une alerte** : une nouvelle publication correspond aux critères → notification dans « Mon espace » (pastille sur la cloche) → tap → publication.
4. **Publier (artisan)** : « Mon espace » → « Publier » → formulaire court → statut « en attente » → validation par un modérateur → visible dans le fil.
5. **Import veille** : la tâche IA dépose un fichier dans Drive → le scheduler Laravel l'importe → les doublons sont écartés → selon le score, publication automatique ou file de modération.

---

## 5. Interface — le fil façon Facebook

**Structure mobile (prioritaire)**

- **Barre du haut fixe** : logo, recherche, cloche des notifications (membres) ou bouton « Se connecter ».
- **Onglets de catégorie** défilants : Tout · Emplois · Home service · Formations.
- **Puces de filtre** : commune (Cocody, Yopougon, Plateau, Abobo, Marcory, Koumassi, Treichville, Adjamé, Port-Bouët, Attécoubé, Bingerville, Songon, Anyama), type de contrat, « Aujourd'hui ».
- **Bloc « Créer une alerte »** inséré dans le fil après la 5e carte.
- **Carte de publication** :
  - en-tête : avatar (logo entreprise ou photo artisan), nom, « il y a 2 h · Cocody », badge de catégorie, menu « … » (enregistrer, signaler) ;
  - corps : titre en gras, 3–4 lignes de texte (« Voir plus »), informations clés en puces (contrat, salaire ou tarif, durée, prix) ;
  - image facultative (WebP, chargement différé) ;
  - compteurs : 👍 12 · 4 commentaires ;
  - actions : J'aime · Commenter · Partager (WhatsApp) · **bouton principal selon le type** (Postuler / Contacter / S'inscrire) ;
  - mention de la source pour les imports (« via Emploi.ci »).
- **Barre de navigation basse** (mobile) : Fil · Recherche · Publier · Alertes · Mon espace.

**Desktop** : trois colonnes. Gauche : catégories et filtres. Centre : le fil (max. 640 px). Droite : « Créer une alerte », communes populaires, formations à la une.

**Ton** : français simple et chaleureux, tutoiement évité (« vous »), aucun jargon.

---

## 6. Architecture technique

| Couche | Choix |
|---|---|
| Backend | **Laravel 13**, PHP 8.3+ (8.4 recommandé) |
| Vues | **Blade** + composants Blade (`<x-carte-publication>`), **pas de Livewire, pas de Filament** |
| CSS | **Tailwind CSS 4** via Vite |
| JS (site public) | **Alpine.js** pour les interactions légères, JS vanilla pour le fil infini (`IntersectionObserver`) |
| JS (back-office) | **jQuery + Yajra DataTables (toujours côté serveur) + Select2 + SweetAlert2**, chargés uniquement dans le bundle admin (`resources/js/admin.js`, entrée Vite séparée) |
| Base de données | MySQL 8 (utf8mb4) |
| Cache | **Memcached** (`CACHE_STORE=memcached`, extension `php-memcached`) |
| Sessions | **database** (pas Memcached : une éviction déconnecterait les utilisateurs) |
| Files d'attente | `database` (worker en cron `--stop-when-empty` sur mutualisé, Supervisor sur VPS) |
| Auth | **Laravel Fortify** (sans vues) configuré sur le champ `telephone` + vues Blade maison |
| Téléphone | **propaganistas/laravel-phone** (libphonenumber) |
| Rôles | **spatie/laravel-permission** |
| Médias | **spatie/laravel-medialibrary** (conversions WebP, miniatures) |
| Listes admin | **yajra/laravel-datatables** (mode serveur) |
| PWA | `vite-plugin-pwa` (Workbox) + `manifest.webmanifest` |
| Google Drive | `google/apiclient` avec un compte de service |
| IA (réécriture / score) | API Claude (modèle Haiku) via HTTP client Laravel |
| Paiement | **CinetPay** (Orange, MTN, Moov, Wave, carte) derrière l'interface `PasserellePaiement` (+ driver `fake`) |
| SMS | interface `FournisseurSms` (drivers `log`, `orange`, `twilio`) |
| Reçus PDF | `barryvdh/laravel-dompdf` |
| Tests et qualité | Pest, Larastan (niveau 6), Pint |

**Organisation du code**

```
app/
  Actions/            # une action métier = une classe (PublierPublication, CreerAlerte…)
  Enums/              # TypePublication, StatutPublication, TypeContrat, FrequenceAlerte…
  Http/Controllers/   # contrôleurs fins
  Http/Requests/      # validation (FormRequest)
  Jobs/               # CorrespondreAlertes, ImporterFichierVeille, EnrichirPublicationIA
  Models/             # Utilisateur, Publication, Alerte, Entreprise, Commune…
  Observers/          # invalidation du cache (PublicationObserver…)
  Services/           # FilService, CacheFil, GoogleDriveVeille, ClaudeClient, TelephoneService
  View/Components/    # composants Blade
resources/views/
  fil/                # index.blade.php, _cartes.blade.php (fragment renvoyé au défilement)
  publications/  espace/  auth/  admin/  components/
```

---

## 7. Modèle de données (tables et colonnes en français)

> Les tables techniques du framework gardent leur nom anglais : `migrations`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `cache`, `notifications`, ainsi que les tables Spatie (`roles`, `permissions`, `model_has_roles`…, `media`).
> Toutes les autres tables sont en français. **Chaque modèle déclare explicitement `$table`.**

### utilisateurs
`id`, `nom`, `prenoms`, `telephone` (E.164, unique, ex. `+2250708091011`), `pays_code` (ISO 3166-1 alpha-2, ex. `CI`), `telephone_verifie_le` (nullable), `email` (nullable), `mot_de_passe`, `photo` (via medialibrary), `commune_id` (nullable), `confiance` (nouveau | fiable | restreint, défaut nouveau — §11.6), `derniere_connexion_le`, `remember_token`, `cree_le`, `modifie_le`, `supprime_le`

### pays
`id`, `code` (CI, SN, ML, BF, BJ, TG, GN, NE, CM, FR…), `nom`, `indicatif` (+225…), `actif`, `ordre`

### communes
`id`, `pays_code`, `ville` (Abidjan…), `nom` (Cocody…), `slug`, `actif`

### categories
`id`, `type` (emploi | service | formation), `nom`, `slug`, `icone`, `parent_id` (nullable), `ordre`
*Exemples : emploi → Informatique, Commerce, BTP, Santé… ; service → Plomberie, Électricité, Ménage…*

### entreprises
`id`, `nom`, `slug`, `secteur`, `site_web`, `telephone`, `commune_id`, `verifiee` (bool), `logo` (medialibrary), `proprietaire_id` → utilisateurs

### publications (table centrale du fil, dénormalisée pour la lecture)
`id`, `uuid`, `type` (emploi | service | formation), `sous_type` (nullable ; pour service : offre | demande), `statut` (brouillon | en_attente | publiee | rejetee | expiree | cloturee), `titre`, `slug`, `contenu`, `extrait` (≈ 220 caractères, pré-calculé), `categorie_id`, `commune_id`, `auteur_id` (nullable), `entreprise_id` (nullable),
`auteur_nom_affiche`, `auteur_avatar_url` (dénormalisés pour le fil),
`source` (utilisateur | veille_ia), `source_nom` (Emploi.ci…), `source_url`, `source_empreinte` (sha256 de l'URL normalisée, **unique**),
`score_fiabilite` (0–100, nullable), `motif_rejet`,
`nb_jaime`, `nb_commentaires`, `nb_partages`, `nb_vues`, `nb_reponses` (compteurs dénormalisés),
`nb_prolongations`, `motif_cloture` (nullable), `publiee_le`, `expire_le`, `cree_le`, `modifie_le`, `supprime_le`

`tri_le` (date de tri du fil = `publiee_le`, mise à `now()` par une remontée — §14.9), `code_court` (6 caractères, unique, pour les liens SMS `/p/{code}`).

**Index** : `(statut, tri_le, id)`, `(type, statut, tri_le, id)`, `(commune_id, statut, tri_le, id)`, FULLTEXT `(titre, contenu)`, unique `source_empreinte`, unique `slug`, unique `code_court`.

### details_emploi (1–1 avec publications)
`publication_id`, `employeur_particulier` (bool), `type_contrat` (cdi | cdd | stage | interim | freelance | autre), `nb_postes` (défaut 1), `salaire_min`, `salaire_max`, `salaire_a_negocier` (bool), `devise` (XOF), `experience_requise`, `niveau_etude`, `date_limite`, `mode_candidature` (rezohelp | email | lien | telephone), `contact_candidature` (nullable)

### details_service
`publication_id`, `metier`, `tarif_indicatif`, `unite_tarif` (heure | jour | prestation | devis), `zones_intervention` (JSON d'ids de communes), `disponibilite`, `telephone_contact` (E.164), `afficher_telephone` (bool), `whatsapp` (E.164, nullable),
*pour une demande :* `date_souhaitee` (nullable), `budget` (nullable)

### details_formation
`publication_id`, `organisme` (texte de secours si pas d'entreprise liée), `modalite` (presentiel | en_ligne | hybride), `duree`, `date_debut`, `prix` (XOF, 0 = gratuit), `certifiante` (bool), `places` (nullable), `mode_inscription` (rezohelp | lien | telephone), `contact_inscription` (nullable)

### alertes
`id`, `utilisateur_id`, `nom`, `type` (nullable = toutes catégories), `categorie_id` (nullable), `commune_id` (nullable), `type_contrat` (nullable), `mots_cles` (nullable), `frequence` (instantanee | quotidienne), `active` (bool), `derniere_notification_le`, `cree_le`, `modifie_le`
*Limite : clé de droit `alertes.max` (10 dans le plan gratuit, §14).*

### alertes_publications (journal des correspondances, anti-doublon)
`alerte_id`, `publication_id`, `notifie_le` — clé primaire `(alerte_id, publication_id)`

### reactions
`id`, `publication_id`, `utilisateur_id`, `type` (jaime), `cree_le` — unique `(publication_id, utilisateur_id)`

### commentaires
`id`, `publication_id`, `utilisateur_id`, `parent_id` (nullable), `contenu`, `statut` (visible | masque), `cree_le`, `modifie_le`, `supprime_le`

### enregistrements (publications sauvegardées)
`utilisateur_id`, `publication_id`, `cree_le` — clé primaire composée

### reponses (candidatures, inscriptions, propositions, contacts — §11.8)
`id`, `publication_id`, `utilisateur_id`, `type` (candidature | inscription | proposition | contact), `message` (nullable), `telephone_partage` (bool), `statut` (envoyee | vue | retenue | refusee), `cree_le`, `modifie_le` — unique `(publication_id, utilisateur_id)`

### signalements
`id`, `publication_id`, `utilisateur_id` (nullable), `motif` (arnaque | doublon | expiree | inapproprie | autre), `commentaire`, `traite_le`, `traite_par`

### imports_veille
`id`, `fichier_drive_id` (unique), `nom_fichier`, `statut` (en_cours | termine | erreur), `nb_lignes`, `nb_importees`, `nb_doublons`, `nb_rejetees`, `erreur`, `traite_le`

### sources_veille
`id`, `nom` (Emploi.ci…), `domaine`, `niveau_confiance` (haut | moyen | bas), `publication_auto` (bool), `actif`

### parametres
`cle` (clé primaire, ex. `seuil_publication_auto`), `valeur` (texte), `type` (int | bool | texte | html), `libelle`, `modifie_le`, `modifie_par`

### journal_activites (audit)
`id`, `utilisateur_id` (auteur de l'action), `action` (ex. `publication.validee`, `utilisateur.suspendu`), `sujet_type`, `sujet_id` (morph), `donnees` (JSON : avant / après, motif), `ip`, `cree_le`
*Index : `(sujet_type, sujet_id)`, `(utilisateur_id, cree_le)`.*

### recherches
`id`, `terme` (normalisé en minuscules), `type` (nullable), `commune_id` (nullable), `nb_resultats`, `utilisateur_id` (nullable), `cree_le`
*Écriture en file d'attente (job léger) pour ne pas ralentir la recherche ; purge après 180 jours.*

### appels_ia
`id`, `publication_id` (nullable), `import_veille_id` (nullable), `modele`, `tokens_entree`, `tokens_sortie`, `cout_estime_fcfa`, `duree_ms`, `statut`, `cree_le`

### Monétisation (§14)

#### plans
`id`, `code` (unique), `nom`, `description`, `cible` (membre | recruteur | artisan | organisme), `famille` (ex. `alertes_sms`, `entreprise`, `artisan_pro`), `prix_fcfa` (int, TTC), `duree_jours`, `duree_essai_jours` (0 = aucun), `est_defaut` (bool, un seul par cible), `actif`, `visible`, `ordre`, `cree_le`, `modifie_le`

#### plan_limites
`plan_id`, `cle` (valeur de l'enum `CleDroit`), `valeur` (chaîne : entier, `-1`, `0`/`1`) — unique `(plan_id, cle)`

#### produits
`id`, `code` (unique), `nom`, `cible`, `type` (offre_supplementaire | remontee | une_commune | sponsorise_fil), `prix_fcfa`, `duree_jours` (nullable), `actif`, `ordre`, `cree_le`, `modifie_le`

#### abonnements
`id`, `titulaire_type`, `titulaire_id` (utilisateur | entreprise), `plan_id`, `statut` (essai | actif | grace | expire | resilie), `origine` (essai | paiement | offert_admin), `debut_le`, `fin_le`, `commande_id` (nullable), `motif` (nullable), `cree_le`, `modifie_le`
*Index : `(titulaire_type, titulaire_id, statut, fin_le)`, `(statut, fin_le)` pour les relances et les expirations.*

#### essais_utilises
`empreinte_telephone` (SHA-256 du numéro E.164), `famille`, `utilise_le` — clé primaire `(empreinte_telephone, famille)`

#### commandes
`id`, `numero` (`CMD-2026-000123`, unique), `utilisateur_id` (acheteur), `titulaire_type`, `titulaire_id`, `article_type` (plan | produit), `article_id`, `publication_id` (nullable, pour un produit), `commune_id` (nullable, pour `une_commune`), `montant_ttc_fcfa`, `taux_tva`, `montant_ht_fcfa`, `statut` (en_attente | payee | echouee | annulee | remboursee), `passerelle` (cinetpay | fake | admin), `reference_passerelle` (unique, nullable), `moyen_paiement` (orange | mtn | moov | wave | carte…), `payee_le`, `numero_recu` (unique, nullable), `donnees_passerelle` (JSON), `cree_le`, `modifie_le`

#### credits
`id`, `titulaire_type`, `titulaire_id`, `cle` (ex. `emploi.offres_mois`), `quantite`, `consommee`, `expire_le`, `commande_id` — crédits issus des produits `offre_supplementaire`

#### notifications_paiement (journal des webhooks)
`id`, `passerelle`, `reference`, `charge_utile` (JSON), `signature_valide` (bool), `traitee_le` (nullable), `erreur` (nullable), `cree_le`

#### mises_en_avant
`id`, `publication_id`, `commande_id` (nullable si incluse dans un abonnement), `type` (remontee | une_commune | sponsorise_fil), `commune_id` (nullable), `debut_le`, `fin_le`, `suspendue_le` (nullable), `nb_impressions`, `nb_clics`, `cree_le`

#### sms_envoyes
`id`, `utilisateur_id` (nullable), `telephone`, `message`, `motif` (alerte | recap | otp | essai | relance), `fournisseur`, `statut` (en_file | envoye | echoue), `reference`, `cout_fcfa`, `envoye_le`, `cree_le`

> **Horodatages en français** : `const CREATED_AT = 'cree_le'; const UPDATED_AT = 'modifie_le';` et `DELETED_AT = 'supprime_le'` dans un trait `HorodatageFrancais` commun à tous les modèles métier.

---

## 8. Fil infini et performance

**Objectif** : TTFB < 200 ms en cache, LCP < 2,5 s en 4G sur un mobile d'entrée de gamme, moins de 50 Ko de JS.

### Défilement sans pagination visible

- **Pagination par curseur** côté serveur (`cursorPaginate(12)`), triée par `tri_le DESC, id DESC` (`tri_le` = date de publication, ou de la dernière remontée payée — §14.9). Aucun `OFFSET`, donc les performances restent constantes même au millionième élément, sans doublon ni trou quand de nouvelles publications arrivent.
- La **première salve est rendue en HTML par le serveur** dans la page (SEO + affichage instantané).
- Les suivantes : un `IntersectionObserver` sur une sentinelle (≈ 800 px avant la fin) appelle `GET /fil/suite?curseur=…&type=…&commune=…`, qui renvoie **un fragment HTML** (`_cartes.blade.php`) et le prochain curseur dans un en-tête `X-Curseur-Suivant`. Le JS ajoute le fragment et s'arrête quand l'en-tête est vide.
- Squelettes de chargement (cartes grises animées), message « Vous êtes à jour 🎉 » en fin de fil.
- Accessibilité : la zone du fil est `aria-live="polite"` et un bouton « Charger plus » reste disponible en secours (sans JS, ou pour la navigation au clavier).
- Les nouvelles publications parues pendant la lecture affichent un bandeau « 3 nouvelles publications ↑ » plutôt qu'un saut de contenu.

### Stratégie de cache (Memcached)

| Élément | Clé | Durée | Invalidation |
|---|---|---|---|
| Salve du fil (ids + curseur suivant) | `fil:v{version}:{type}:{commune}:{curseur}` | 5 min | incrément de `fil:version` par `PublicationObserver` (publication, dépublication, suppression) |
| HTML d'une carte | `carte:{id}:{modifie_le_timestamp}` | 24 h | automatique (la clé change avec `modifie_le`) |
| Référentiels (catégories, communes, pays) | `ref:categories`, `ref:communes`… | 24 h | observers des modèles concernés |
| Compteurs par catégorie | `compteurs:types` | 10 min | expiration |
| Profil public entreprise | `entreprise:{id}:{modifie_le}` | 24 h | automatique |

**Règles**

- **Clés versionnées plutôt que tags** : plus fiables avec Memcached (pas d'éviction partielle de groupe).
- Invalidation **uniquement dans les observers** (jamais dans les contrôleurs), via le service `CacheFil`.
- Les **données propres à l'utilisateur** (« j'ai aimé », « enregistré ») ne sont jamais dans le cache partagé. Elles sont chargées en **une seule requête** pour les ids visibles, puis injectées dans les cartes.
- Les clics « J'aime » mettent à jour les compteurs avec `increment()` et n'invalident pas la carte : l'interface est optimiste (Alpine) et le compteur affiché se rafraîchit au prochain rendu.
- Le verrou `Cache::lock()` évite l'effet « ruée » quand une clé populaire expire.
- Les vues sont précompilées (`view:cache`), ainsi que la config, les routes et les événements, en production.

### Autres optimisations

- Eager loading systématique et `select()` des seules colonnes utiles. `Model::preventLazyLoading()` est activé hors production.
- Images : conversions WebP (miniature 96 px pour les avatars, 640 px pour le fil), `loading="lazy"`, `width`/`height` déclarés (pas de décalage de mise en page).
- Polices système (aucune police web bloquante), CSS critique inline facultatif.
- En-têtes HTTP : `Cache-Control: public, max-age=60` sur `/fil/suite` pour les visiteurs non connectés, assets versionnés `immutable`.
- Compression gzip/brotli côté serveur, HTTP/2.
- Requêtes lentes surveillées (`DB::whenQueryingForLongerThan`) et Laravel Telescope en local uniquement.

---

## 9. Authentification par téléphone

- **Identifiant = numéro de téléphone + mot de passe** (email facultatif).
- Formulaire : **sélecteur de pays** (drapeau + indicatif, Côte d'Ivoire par défaut) + champ numéro au format national.
- Validation : règle `phone:pays_code,mobile` de `propaganistas/laravel-phone` (libphonenumber), qui gère les formats de chaque pays (CI : 10 chiffres depuis 2021, ex. `07 08 09 10 11`).
- **Stockage normalisé en E.164** (`+2250708091011`) ; affichage au format national ou international selon le contexte. Un `cast` Eloquent fait la conversion.
- Connexion : on normalise la saisie avec le pays choisi avant la recherche, donc `0708091011`, `07 08 09 10 11` et `+225 07 08 09 10 11` mènent au même compte.
- Limitation de tentatives : 5 essais par minute par téléphone + IP (`RateLimiter`).
- Mot de passe : 8 caractères minimum, vérifié contre les fuites connues (`Password::min(8)->uncompromised()`).
- **Vérification du numéro et mot de passe oublié** : code OTP par SMS (fournisseur à choisir, voir §18). Avant l'intégration SMS, la réinitialisation se fait par l'administrateur.

---

## 10. Alertes

- Création depuis le fil (pré-remplie avec les filtres actifs) ou depuis « Mon espace → Alertes ».
- Critères : type, catégorie, commune, type de contrat, mots-clés.
- Fréquence : **instantanée** (dès la publication) ou **quotidienne** (récapitulatif à 7 h).
- Fonctionnement : quand une publication passe en `publiee`, l'événement `PublicationPubliee` déclenche le job en file `CorrespondreAlertes`. Le job cherche les alertes actives qui correspondent (requête SQL sur les critères + mots-clés en FULLTEXT ou LIKE), écarte les paires déjà notifiées (`alertes_publications`) et crée des **notifications Laravel (canal `database`)**.
- Affichage : cloche avec pastille dans l'en-tête, page « Mon espace → Notifications », marquage lu / non lu.
- Canal **SMS** si le titulaire y a droit (`alertes.canal_sms` — essai 14 j puis pass, §14.8). Le nombre d'alertes est limité par `alertes.max`.
- V2 : canaux Web Push et WhatsApp ajoutés à la même notification (`via()`).

---

## 11. Publication par les utilisateurs

### 11.1 Qui peut publier

- **Tout membre connecté peut publier.** Il n'y a pas de rôle à demander avant : le rôle se déduit de la première publication de chaque type et est attribué automatiquement (action `AttribuerRoleAuteur`) :
  - offre d'emploi → `recruteur` ;
  - service proposé → `artisan` ;
  - formation → `organisme`.
- Un visiteur qui clique sur **Publier** est envoyé vers `/connexion` (ou `/inscription`) puis **ramené au formulaire** (`redirect()->intended()`). Le type choisi est conservé.
- Un compte `suspendu` ou au niveau de confiance `restreint` ne peut pas publier : le bouton affiche la raison.

### 11.2 Les quatre types de publication

| Choix à l'écran | `type` | `sous_type` | Exemple |
|---|---|---|---|
| « Je recrute » | emploi | — | Comptable CDI, 10 commerciaux |
| « Je propose mes services » | service | offre | Électricien disponible à Cocody |
| « Je cherche un prestataire » | service | demande | Cherche plombier pour fuite à Yopougon |
| « Je propose une formation » | formation | — | Bootcamp développeur web |

La **demande de service** est essentielle pour le home service : c'est souvent le client qui publie son besoin (« cherche nounou à Angré »), et les artisans y répondent.

### 11.3 Parcours (mobile d'abord, 3 étapes)

1. **Choix du type** (`GET /publier`) : quatre grandes cartes illustrées (icône + une phrase).
2. **Formulaire** (`GET /publier/{type}`) : court, une seule colonne, champs regroupés par blocs repliables, compteur de caractères, aide sous chaque champ.
   - Le brouillon est **sauvegardé automatiquement** dans le navigateur (Alpine + `localStorage`, toutes les 5 s) pour ne rien perdre si la connexion coupe.
   - Un bouton « Enregistrer en brouillon » crée la publication côté serveur avec le statut `brouillon`.
3. **Aperçu puis publication** : `POST /publier/{type}/apercu` renvoie la carte rendue par **le vrai composant `x-carte-publication`**, donc l'aperçu est identique au fil. Ensuite, `POST /publier/{type}` enregistre la publication.

Écran de confirmation, selon la décision (§11.6) :
- « Votre offre est en ligne » + bouton « Partager sur WhatsApp » ;
- ou « Votre offre sera visible après vérification, généralement en moins de 2 h. Vous serez notifié. »

### 11.4 Champs par type

**Communs à tous** : `titre` (10–100 caractères), `categorie_id`, `commune_id`, `contenu` (80–3 000 caractères), photos (0 à `publication.photos_max` selon le plan ; JPG, PNG ou WebP ; 5 Mo maximum chacune ; conversions WebP via medialibrary), `expire_le` (défaut +30 j, maximum `publication.duree_max_jours`).

**Quotas** : avant d'afficher le formulaire, `Droits` vérifie le quota du type (`emploi.offres_mois`, `service.offres_actives`, `formation.offres_actives`). S'il est atteint, l'écran « Quota atteint » s'affiche (§14.11).

**Je recrute (emploi)**
- **Employeur** :
  - choisir une de mes entreprises ;
  - ou en créer une **directement dans le formulaire** : nom, secteur, commune, logo facultatif ;
  - ou cocher « Particulier » (`employeur_particulier`), par exemple pour un chauffeur ou une nounou à domicile.
- `type_contrat`, `nb_postes` (défaut 1), salaire (`salaire_min`, `salaire_max` ou case « à négocier »), `experience_requise`, `niveau_etude`, `date_limite`.
- **Mode de candidature** (`mode_candidature`) :
  - `rezohelp` : le candidat postule sur le site (§11.8) ;
  - `email` ;
  - `lien` (site carrière) ;
  - `telephone`.
  
  `contact_candidature` reçoit l'email, le lien ou le numéro correspondant.

**Je propose mes services (service / offre)**
- Métier (= sous-catégorie : Électricité, Plomberie…). Le titre est **pré-rempli** (« Électricien disponible à Cocody ») et modifiable.
- `zones_intervention` : plusieurs communes (cases à cocher), `tarif_indicatif` + `unite_tarif` (heure / jour / prestation) ou « sur devis », `disponibilite`.
- `telephone_contact` (pré-rempli avec le numéro du compte) + case **`afficher_telephone`** (cochée par défaut), `whatsapp` (case « même numéro »).
- Photos de réalisations conseillées.

**Je cherche un prestataire (service / demande)**
- Métier recherché, commune, `date_souhaitee` (aujourd'hui / cette semaine / date précise), `budget` facultatif, description du besoin.
- **Le numéro du demandeur n'est jamais affiché.** Les artisans répondent avec « Je suis disponible » (§11.8) et le demandeur reçoit leurs coordonnées.

**Je propose une formation**
- Organisme : une de mes entreprises (type organisme) ou création en ligne.
- `modalite` (présentiel / en ligne / hybride ; commune obligatoire sauf en ligne), `date_debut`, `duree`, `prix` (0 = gratuit), `certifiante`, `places` (facultatif).
- **Mode d'inscription** (`mode_inscription`) : `rezohelp`, `lien` ou `telephone`, avec `contact_inscription`.

Validation par une FormRequest par type :
- `PublierEmploiRequest`
- `PublierServiceOffreRequest`
- `PublierServiceDemandeRequest`
- `PublierFormationRequest`

Les numéros saisis sont normalisés en E.164 par `TelephoneService`, comme pour les comptes. Les montants sont saisis en FCFA sans séparateur, puis affichés au format `150 000 FCFA`.

### 11.5 Contrôles anti-arnaque et anti-spam

**À l'envoi, en synchrone** (`AnalyserRisquePublication`, règles simples, sans IA) :
- mots et expressions à risque dans le titre ou le contenu : « frais de dossier », « frais d'inscription » (pour un emploi), « payer », « Western Union », « transfert », « mandat »… ;
- numéro de téléphone ou lien WhatsApp **dans le texte** d'une offre d'emploi (il doit passer par le champ prévu) ;
- salaire hors norme (au-dessus d'un plafond par type de contrat, paramétrable) ;
- texte en majuscules à plus de 50 %, liens raccourcis (bit.ly…).

Chaque règle déclenchée ajoute un code dans `signaux_risque` et **force la modération**. La publication n'est jamais refusée automatiquement.

**Après l'envoi, en file** : le job `EnrichirPublicationIA` (le même que pour la veille) calcule `score_fiabilite` et complète `signaux_risque`.

**Anti-spam** :
- au maximum `max_publications_par_jour` publications (paramètre, 5 par défaut) par compte ;
- rate limit de 10 envois par heure ;
- **doublon** : même auteur + titre très proche (similarité ≥ 90 %) dans les 7 jours → l'envoi est bloqué et l'auteur est invité à prolonger son ancienne publication.

### 11.6 Niveau de confiance et décision de publication

La colonne `utilisateurs.confiance` prend les valeurs `nouveau | fiable | restreint`. Elle est mise à jour par `PublicationObserver` et `SignalementObserver`.

| Situation | Décision |
|---|---|
| Première publication du compte (quel que soit le type) | `en_attente` |
| Au moins un signal de risque (§11.5) | `en_attente` |
| Auteur `fiable` (au moins 3 publications validées, aucun signalement retenu) **et** score ≥ seuil | `publiee` immédiatement |
| Entreprise `verifiee` **et** aucun signal | `publiee` immédiatement |
| Tous les autres cas | `en_attente` |
| Auteur `restreint` (2 publications rejetées pour arnaque, ou signalement retenu) | publication impossible ; seul l'admin peut lever la restriction |

Tant que le score IA n'est pas encore calculé, la décision se prend sans lui. Le job peut ensuite **repasser une publication `publiee` en `en_attente`** si le score tombe sous 40 : la publication est retirée du fil et l'auteur est notifié.

### 11.7 Après la publication : « Mon espace → Mes publications »

- Liste avec un badge de statut : brouillon, en attente, publiée, **rejetée (motif affiché)**, expirée, clôturée.
- Statistiques par publication : vues, j'aime, partages, réponses.
- Actions :
  - **Modifier**. Si l'auteur n'est pas `fiable`, une modification du titre, du contenu ou des contacts renvoie la publication en `en_attente`. Pour un auteur `fiable`, elle reste en ligne et l'analyse de risque est relancée.
  - **Clôturer** (« Poste pourvu », « Plus disponible », « Complet ») → statut `cloturee`. La publication disparaît du fil mais sa page reste visible, avec un bandeau.
  - **Prolonger** de 30 j (3 fois au maximum, `nb_prolongations`).
  - **Dupliquer** pour une offre similaire.
  - **Supprimer** (soft delete).
  - **Partager**.
- Une publication rejetée peut être corrigée et renvoyée : elle repasse en `en_attente`.

**Notifications envoyées à l'auteur** (canal `database`) :
- publication validée ;
- publication rejetée, avec le motif ;
- nouvelle réponse ;
- expiration dans 3 jours ;
- publication remise en attente.

### 11.8 Les réponses (table `reponses`)

Le bouton principal de la carte change selon le type et le mode choisi par l'auteur :

| Publication | Bouton | Effet |
|---|---|---|
| Emploi, `mode_candidature = rezohelp` | **Postuler** | formulaire court : message (facultatif) + case « partager mon numéro » → `reponses` (type `candidature`) |
| Emploi, autre mode | **Postuler** | ouvre le lien, l'email ou le numéro (le clic est compté) |
| Service / offre | **Contacter** | appel ou WhatsApp direct si `afficher_telephone`, sinon formulaire → `reponses` (type `contact`) |
| Service / demande | **Je suis disponible** | réservé aux comptes connectés : message + numéro partagé → `reponses` (type `proposition`) ; le demandeur est notifié |
| Formation, `mode_inscription = rezohelp` | **S'inscrire** | → `reponses` (type `inscription`) |

- L'auteur voit les réponses dans « Mes publications → Réponses » et peut les marquer **vue**, **retenue** ou **refusée** ; le répondant est notifié.
- Une seule réponse par personne et par publication.
- `publications.nb_reponses` est incrémenté.
- **V2** : CV en PDF joint à la candidature (medialibrary, 2 Mo maximum) et messagerie interne.

### 11.9 Routes, contrôleurs et actions

| Méthode | URI | Nom | Contrôleur |
|---|---|---|---|
| GET | `/publier` | `publier.choix` | `PublierController@choix` |
| GET | `/publier/{type}` | `publier.formulaire` | `PublierController@formulaire` |
| POST | `/publier/{type}/apercu` | `publier.apercu` | `PublierController@apercu` |
| POST | `/publier/{type}` | `publier.store` | `PublierController@store` |
| GET | `/mon-espace/publications` | `espace.publications.index` | `Espace\PublicationController@index` |
| GET / PUT | `/mon-espace/publications/{publication}/modifier` | `espace.publications.edit` / `.update` | idem |
| POST | `/mon-espace/publications/{publication}/cloturer` | `espace.publications.cloturer` | idem |
| POST | `/mon-espace/publications/{publication}/prolonger` | `espace.publications.prolonger` | idem |
| POST | `/mon-espace/publications/{publication}/dupliquer` | `espace.publications.dupliquer` | idem |
| DELETE | `/mon-espace/publications/{publication}` | `espace.publications.destroy` | idem |
| GET | `/mon-espace/publications/{publication}/reponses` | `espace.reponses.index` | `Espace\ReponseController@index` |
| PATCH | `/mon-espace/reponses/{reponse}` | `espace.reponses.update` | idem (statut) |
| POST | `/publication/{publication:slug}/repondre` | `publications.repondre` | `ReponseController@store` |

`{type}` est contraint par `whereIn('type', ['emploi', 'service-offre', 'service-demande', 'formation'])`.

**Actions** (`app/Actions/Publication/*`) :
- `PublierPublication` : crée la publication et ses détails dans une transaction, calcule `extrait`, `slug`, `auteur_nom_affiche` et `auteur_avatar_url`, appelle `AnalyserRisquePublication` puis `DeciderStatutPublication`, et déclenche `PublicationPubliee` si la publication est publiée.
- `ModifierPublication`
- `CloturerPublication`
- `ProlongerPublication`
- `DupliquerPublication`
- `RepondrePublication`
- `AttribuerRoleAuteur`

**Policy** `PublicationPolicy` :
- `create` : non suspendu, non restreint, quota du jour non atteint ;
- `update`, `delete`, `cloturer` : auteur uniquement ;
- `repondre` : connecté, pas l'auteur, publication `publiee`.

---

## 12. Import de la veille IA (Google Drive → site)

1. Une tâche Claude planifiée (toutes les 8 h, à 00h51, 08h51 et 16h51 heure d'Abidjan) dépose un Google Sheet dans le dossier Drive **« Offres Emploi Abidjan »**. Colonnes : `Catégorie, Intitulé, Entreprise/Organisme, Lieu, Contrat/Durée/Prix, Date publication, Domaine, Source, Lien, Résumé`.
2. La commande `php artisan veille:importer` est planifiée à **01h20, 09h20 et 17h20**.
   - Elle liste les fichiers du dossier via un compte de service Google (le dossier est partagé avec l'email du compte de service).
   - Elle ignore les fichiers déjà présents dans `imports_veille`.
   - Elle exporte chaque Sheet en CSV, puis dispatche un job `ImporterFichierVeille`.
3. Pour chaque ligne :
   - normalisation de l'URL → `source_empreinte` → doublon ignoré ;
   - correspondance catégorie (« Offre d'emploi » → `emploi`, « Home service / petits métiers » → `service`, « Formation » → `formation`) et commune (recherche dans `communes`) ;
   - **enrichissement IA facultatif** (`EnrichirPublicationIA`, API Claude Haiku) : texte de carte accrocheur, catégorie fine et **score de fiabilité** (signaux d'arnaque : frais demandés, contact uniquement WhatsApp, salaire irréaliste…).
4. Décision de publication :
   - source `niveau_confiance = haut` **et** score ≥ 70 → `publiee` ;
   - sinon → `en_attente` (file de modération du back-office).
5. Le site affiche **un résumé + un lien vers l'annonce d'origine**, jamais le texte copié intégralement.

---

## 13. Back-office (admin)

### 13.1 Principes techniques

- **Préfixe** `/admin`, noms de routes `admin.*`, middleware `['auth', 'role:administrateur|moderateur']`, puis `can:<permission>` sur chaque groupe.
- **Layout dédié** `resources/views/admin/layout.blade.php` : barre latérale (repliable sur mobile), en-tête avec compteurs (en attente, signalements), zone de contenu. Même Tailwind, même palette que le site.
- **Contrôleurs** dans `App\Http\Controllers\Admin\*`, un contrôleur par module. Logique métier dans les Actions (`app/Actions/Admin/*`), jamais dans les contrôleurs.
- **JS du back-office** : jQuery, Yajra DataTables, Select2 et SweetAlert2 sont **autorisés et recommandés ici**. Ils sont chargés via l'entrée Vite `resources/js/admin.js` et `resources/css/admin.css`, **jamais sur le site public** (budget JS < 50 Ko côté public).
- **Listes** : **Yajra DataTables, toujours en mode serveur** (`serverSide: true`), une classe DataTable par liste dans `App\DataTables\*` (`PublicationsDataTable`, `UtilisateursDataTable`…). Filtres personnalisés transmis à l'appel Ajax ; recherche, tri et pagination gérés par DataTables (la règle « pas de pagination » ne vaut que pour le fil public). Style DataTables adapté à la palette Tailwind.
- **Listes déroulantes** : **Select2** pour les sélections avec recherche ou multiples (catégories, communes, utilisateurs, rôles). Recherche Ajax (`/admin/recherche/utilisateurs`…) pour les grandes listes.
- **Confirmations et retours** : **SweetAlert2** pour toute action destructive (suppression, bannissement, rejet groupé) et pour les notifications de succès ou d'erreur (toasts).
- **Composants Blade admin** : `x-admin.filtres`, `x-admin.badge-statut`, `x-admin.carte-stat`, `x-admin.flash` ; les colonnes d'actions des DataTables sont rendues par des vues Blade partielles (`admin/*/_actions.blade.php`).
- **Journal d'audit** : chaque action admin est tracée dans `journal_activites` via le trait `JournaliseActivite` sur les Actions admin.
- Les pages admin ne sont **jamais mises en cache** (ni Memcached, ni service worker).

### 13.2 Rôles et permissions (spatie/laravel-permission)

| Permission | Administrateur | Modérateur |
|---|---|---|
| `tableau_de_bord.voir` | ✅ | ✅ |
| `publications.moderer` | ✅ | ✅ |
| `publications.gerer` (modifier, dépublier, supprimer) | ✅ | ✅ |
| `signalements.traiter` | ✅ | ✅ |
| `commentaires.moderer` | ✅ | ✅ |
| `utilisateurs.voir` | ✅ | ✅ |
| `utilisateurs.suspendre` | ✅ | ✅ |
| `utilisateurs.gerer` (rôles, suppression, mot de passe) | ✅ | ❌ |
| `entreprises.verifier` | ✅ | ❌ |
| `veille.gerer` | ✅ | ❌ |
| `referentiels.gerer` | ✅ | ❌ |
| `parametres.gerer` | ✅ | ❌ |
| `statistiques.voir` | ✅ | ✅ |
| `systeme.voir` | ✅ | ❌ |
| `abonnements.gerer` (plans, produits, abonnements) | ✅ | ❌ |
| `paiements.voir` (commandes, revenus) | ✅ | ❌ |

Les rôles et permissions sont créés par `RolesEtPermissionsSeeder`. Dans les vues : `@can('...')`, jamais `@role`.

### 13.3 Modules

#### A. Tableau de bord — MVP
**Route** : `GET /admin` → `admin.tableau-de-bord` (`TableauDeBordController@index`)

- **Cartes de compteurs** : publications publiées aujourd'hui (par type), **en attente de modération**, signalements ouverts, nouveaux inscrits (jour / 7 j), alertes actives, notifications envoyées aujourd'hui.
- **Dernier import de la veille** : heure, nb importées / doublons / rejetées, statut. **Bandeau rouge** si aucun import réussi depuis plus de 9 h.
- **Graphique 30 jours** : publications et inscriptions par jour (SVG ou Chart.js léger, chargé uniquement sur cette page).
- **Raccourcis** : « Modérer (N) », « Signalements (N) ».
- Compteurs calculés par `StatistiquesService`, mis en cache 5 min (`admin:compteurs`). Exception tolérée à la règle « pas de cache admin », car ce sont des agrégats non sensibles.

#### B. Modération — MVP (module prioritaire)
**Routes** :
- `GET /admin/moderation` → liste des `en_attente`
- `GET /admin/moderation/{publication}` → détail
- `POST /admin/moderation/{publication}/valider`
- `POST /admin/moderation/{publication}/rejeter` (champ `motif_rejet` obligatoire)
- `POST /admin/moderation/{publication}/doublon` (champ `publication_originale_id`)
- `POST /admin/moderation/lot` (ids[] + action `valider|rejeter`)

**Écran liste** : tri par défaut `score_fiabilite ASC` (les plus risquées d'abord). Colonnes : type, titre, auteur / source, commune, score (badge rouge < 40, orange 40–69, vert ≥ 70), date. Cases à cocher pour les actions groupées.

**Écran détail** : aperçu exact de la carte telle qu'elle apparaîtra dans le fil ; **signaux de risque surlignés** (stockés en JSON dans `publications.signaux_risque` par l'enrichissement IA, ex. `["frais_demandes", "contact_whatsapp_uniquement"]`) ; lien vers l'annonce source ; historique de l'auteur (nb publications, nb rejets) ; publications similaires (même `source_empreinte` proche ou même titre + entreprise).

**Raccourcis clavier** (Alpine) : `V` valider, `R` rejeter (ouvre le champ motif), `D` doublon, `→` suivante. Après une action, on passe automatiquement à la suivante.

**Règles métier** :
- Valider → `statut = publiee`, `publiee_le = now()`, événement `PublicationPubliee` (alertes + cache).
- Rejeter → `statut = rejetee`, notification à l'auteur (si publication native) avec le motif.
- La **première publication de chaque compte** arrive toujours ici, quel que soit le rôle.
- Motifs de rejet prédéfinis (enum `MotifRejet`) : arnaque_suspectee, doublon, incomplete, hors_zone, contenu_inapproprie, autre (+ texte libre).

#### C. Publications — MVP
**Routes** : resource `admin/publications` (index, edit, update, destroy) + `POST {publication}/depublier`, `POST {publication}/prolonger` (+30 j), `POST {publication}/epingler` (V2).

- Filtres : type, statut, source (`veille_ia` / `utilisateur`), commune, catégorie, auteur, période, texte.
- Édition : mêmes champs que le formulaire public + `statut`, `expire_le`, `categorie_id`.
- **Expiration automatique** : commande `publications:expirer` planifiée chaque jour à 02h00 (`expire_le` dépassé, ou 30 j après `publiee_le` si `expire_le` nul) → `statut = expiree`, incrément de `fil:version`.
- Suppression = soft delete. Purge définitive des publications supprimées depuis plus de 90 j (commande planifiée hebdomadaire).

#### D. Signalements — MVP
**Routes** : `GET /admin/signalements`, `POST /admin/signalements/{publication}/decider` (décision : `retablir | supprimer | supprimer_et_suspendre`).

- Liste **groupée par publication** : nb de signalements, motifs, date du premier.
- **Masquage automatique** : à 3 signalements « arnaque » non traités, l'observer `SignalementObserver` passe la publication en `en_attente` et notifie les modérateurs.
- Toute décision marque les signalements liés `traite_le`, `traite_par`.

#### E. Veille IA et imports — MVP
**Routes** :
- `GET /admin/veille/imports`, `GET /admin/veille/imports/{import}`, `POST /admin/veille/imports/{import}/relancer`, `POST /admin/veille/importer-maintenant`
- resource `admin/veille/sources` (index, create, store, edit, update)

- **Historique des imports** : fichier Drive (lien), date, lignes, importées, doublons, rejetées, statut, message d'erreur. Le détail liste chaque ligne et son sort (importée → lien vers la publication, doublon → lien vers l'existante, rejetée → raison).
- **Sources** : nom, domaine, `niveau_confiance` (haut / moyen / bas), `publication_auto`, actif, et pour chacune le nb d'offres importées et le **taux de rejet** sur 30 j.
- Une ligne dont le domaine n'est dans aucune source est rattachée automatiquement à une source « Inconnue » de confiance basse.
- Le **coût de l'API IA** (nb d'appels, tokens, estimation en FCFA) est affiché par mois, depuis `imports_veille` + `appels_ia`.

#### F. Utilisateurs — MVP
**Routes** : resource `admin/utilisateurs` (index, show, edit, update, destroy) + `POST {utilisateur}/suspendre`, `POST {utilisateur}/reactiver`, `POST {utilisateur}/reinitialiser-mot-de-passe`, `PUT {utilisateur}/roles`.

- **Recherche par téléphone normalisée** (via `TelephoneService`) : `0708091011`, `07 08 09 10 11` et `+225…` trouvent le même compte. Recherche aussi par nom.
- Fiche : infos, rôles, date d'inscription, dernière connexion, publications, alertes, signalements reçus, historique des sanctions.
- **Suspension** : `suspendu_le`, `motif_suspension`, `suspendu_jusqu_au` (nullable = définitif). Un compte suspendu ne peut plus se connecter ni publier ; ses publications sont dépubliées.
- **Réinitialisation du mot de passe** (avant l'OTP SMS) : génère un mot de passe temporaire affiché **une seule fois** à l'admin + `doit_changer_mot_de_passe = true`.
- **Suppression (ARTCI)** : anonymisation (nom → « Utilisateur supprimé », téléphone effacé), suppression des alertes et notifications, conservation des publications anonymisées.
- Un admin ne peut ni se suspendre ni retirer son propre rôle d'administrateur.

**Entreprises** (`admin/entreprises`) : liste, badge `verifiee` (permission `entreprises.verifier`), fusion de deux fiches en doublon (les publications sont rattachées à la fiche conservée).

#### G. Référentiels et paramètres — MVP
**Routes** : resources `admin/categories`, `admin/communes`, `admin/pays` ; `GET/PUT /admin/parametres` ; `POST /admin/cache/vider-fil`.

- **Catégories** : arbre type → catégorie → sous-catégorie, icône, ordre (glisser-déposer simple via flèches haut/bas), activation.
- **Communes et pays** : activation, ordre ; un pays inactif disparaît du sélecteur d'inscription.
- **Paramètres** (table `parametres` clé/valeur, cache `ref:parametres`) :
  - `seuil_publication_auto` (70)
  - `jours_expiration_defaut` (30)
  - `seuil_masquage_signalements` (3)
  - `max_publications_par_jour` (5)
  - `plafonds_salaire` (JSON par type de contrat, pour l'anti-arnaque §11.5)
  - `mots_a_risque` (liste éditable des expressions à risque, §11.5)
  - monétisation (§14) : `quotas_actifs` (false au lancement), `jours_grace` (3), `taux_tva` (18), `frequence_sponsorise` (8), `mentions_legales_recu` (raison sociale, RCCM, NCC, adresse)
  - *Les limites par utilisateur (nombre d'alertes, photos, offres…) ne sont **pas** des paramètres : ce sont des limites de plans (§14.3).*
  - `texte_bandeau_prevention`
  - `contenu_cgu`, `contenu_confidentialite`
- **Cache** : bouton « Vider le cache du fil » (incrémente `fil:version`) et « Vider les référentiels ».
- Toute modification d'un référentiel ou d'un paramètre invalide sa clé `ref:*` via son observer.

#### H. Statistiques — V2
**Route** : `GET /admin/statistiques` (+ `?export=csv`)

- Publications par type, catégorie, commune, source, sur une période.
- **Métiers et domaines les plus demandés**, déduits des alertes actives (la vraie demande).
- **Recherches sans résultat** (table `recherches`) : les termes les plus fréquents, donc quoi aller chercher.
- Sources les plus productives et taux de rejet.
- Export CSV de chaque tableau.

#### I. Commentaires et alertes — V2
- **Commentaires** (`admin/commentaires`) : liste filtrable, masquer / rétablir / supprimer, et suspendre l'auteur depuis la ligne.
- **Alertes** (`admin/alertes`, lecture seule) : nb d'alertes actives par critère, notifications envoyées par jour.

#### J. Système et journal d'audit — V2 (journal dès le MVP)
- **Journal d'audit** (`admin/journal`) : qui, quoi, sur quel objet, quand, IP ; filtres par utilisateur, action et période. **L'écriture est active dès le MVP**, seul l'écran de consultation est en V2.
- **Jobs échoués** : liste `failed_jobs` avec bouton relancer ou supprimer.
- **Santé** : dernière exécution du scheduler (horodatage écrit par une tâche planifiée toutes les minutes), connexion Memcached et taux de succès, taille de la file `jobs`.

#### K. Plans et paiements — MVP
Voir §14.10 (plans, produits, limites, abonnements, commandes, revenus).

### 13.4 Données ajoutées par le back-office

Colonnes ajoutées :
- `publications.signaux_risque` (JSON, nullable)
- `publications.epinglee_jusqu_au` (V2)
- `utilisateurs.suspendu_le`, `suspendu_jusqu_au`, `motif_suspension`, `doit_changer_mot_de_passe`

Nouvelles tables : voir §7 (`parametres`, `journal_activites`, `recherches`, `appels_ia`).

---

## 14. Modèle économique et abonnements dynamiques

### 14.1 Principes

1. **L'essentiel reste gratuit** : consulter, créer un compte, créer des alertes reçues dans « Mon espace », postuler, publier dans les quotas. On fait payer **le canal** (SMS), **le volume** (au-delà des quotas) et **la visibilité** (remontée, mise en avant), jamais l'accès à l'emploi.
2. **Tout est dynamique** : plans, prix, durées, essais, quotas et limites se règlent **dans l'admin, sans toucher au code**. Le code ne connaît que des **clés de droits** (§14.3) ; leurs valeurs viennent de la base.
3. **Payer ne dispense jamais de la modération.** Une publication boostée suit les mêmes règles (§11.6).
4. **Tout ce qui est payé est libellé « Sponsorisé » ou « À la une ».**
5. **Prépayé** : le Mobile Money ne permet pas le prélèvement automatique, donc les abonnements sont des **pass à durée fixe**, rechargés par l'utilisateur après des relances. Il n'y a jamais de débit automatique.
6. **Activation progressive** : tout est développé dès le MVP, mais chaque plan et chaque produit a un interrupteur `actif`, et le paramètre global `quotas_actifs` coupe tous les quotas (calendrier en §14.12).

### 14.2 Notions

| Notion | Définition |
|---|---|
| **Titulaire** | Celui qui détient les droits : un **utilisateur** (membre, artisan) ou une **entreprise** (recruteur, organisme). Relation polymorphe `titulaire_type` / `titulaire_id`. |
| **Plan** | Une formule d'abonnement : prix, durée, essai, cible, limites. Chaque cible a un **plan par défaut gratuit** (`est_defaut`), appliqué sans abonnement. |
| **Limite** | La valeur d'une clé de droit dans un plan (`plan_limites`). |
| **Abonnement** | La souscription d'un titulaire à un plan pour une période (`debut_le` → `fin_le`), au statut `essai`, `actif`, `grace`, `expire` ou `resilie`. |
| **Produit** | Un achat ponctuel lié en général à une publication : offre supplémentaire, remontée, mise en avant. |
| **Commande** | Un achat (plan ou produit) et son paiement. Elle porte le numéro de reçu. |

### 14.3 Catalogue des clés de droits (Enum `CleDroit`)

Les **clés** sont définies dans le code (le comportement en dépend) ; leurs **valeurs** sont en base. Pour un entier, `-1` signifie illimité.

| Clé | Type | Effet |
|---|---|---|
| `alertes.max` | int | Nombre maximal d'alertes |
| `alertes.canal_sms` | bool | Alertes reçues par SMS |
| `alertes.sms_par_jour` | int | Plafond de SMS par jour ; au-delà, regroupement dans un récapitulatif SMS du soir |
| `alertes.canal_whatsapp` | bool | (V2) Alertes par WhatsApp |
| `emploi.offres_mois` | int | Offres d'emploi publiées par mois calendaire (hors rejetées) |
| `service.offres_actives` | int | Annonces de service actives simultanément |
| `formation.offres_actives` | int | Formations actives simultanément |
| `publication.photos_max` | int | Photos par publication |
| `publication.remontees_mois` | int | Remontées gratuites incluses par mois |
| `publication.duree_max_jours` | int | Durée maximale d'une publication |
| `profil.badge` | bool | Badge « Pro » ou « Entreprise » sur les cartes |
| `statistiques.avancees` | bool | Statistiques détaillées dans « Mes publications » |
| `reponses.demandes_mois` | int | Réponses « Je suis disponible » aux demandes de service par mois |
| `cvtheque.acces` | bool | (V2) Accès aux profils des candidats |

**Ajouter une clé** demande du code (enum, migration de données, usage). **Changer un prix, une limite, un essai ou créer un plan** se fait dans l'admin uniquement.

### 14.4 Grille initiale (seeder `PlansSeeder`, valeurs modifiables dans l'admin)

> Prix indicatifs TTC en FCFA, à valider (§18).

**Plans**

| Code | Cible | Prix / durée | Essai | Limites principales |
|---|---|---|---|---|
| `membre_gratuit` *(défaut)* | membre | 0 | — | alertes.max 10, canal_sms **non**, reponses.demandes_mois 3 |
| `alertes_sms_7j` | membre | 300 / 7 j | **14 j** (famille `alertes_sms`) | canal_sms oui, sms_par_jour 5 |
| `alertes_sms_30j` | membre | 1 000 / 30 j | (même famille, un seul essai) | canal_sms oui, sms_par_jour 5 |
| `recruteur_gratuit` *(défaut)* | recruteur | 0 | — | emploi.offres_mois **3** (particulier : 1), photos_max 2 |
| `entreprise_30j` | recruteur | 50 000 / 30 j | 14 j | emploi.offres_mois −1, profil.badge, statistiques.avancees, remontees_mois 4 |
| `artisan_gratuit` *(défaut)* | artisan | 0 | — | service.offres_actives 3, photos_max 4, reponses.demandes_mois 5 |
| `artisan_pro_30j` | artisan | 3 000 / 30 j | **30 j (1er mois offert)** | service.offres_actives 10, photos_max 10, remontees_mois 4, profil.badge, statistiques.avancees, reponses.demandes_mois −1 |
| `organisme_gratuit` *(défaut)* | organisme | 0 | — | formation.offres_actives 1 |
| `organisme_pro_30j` | organisme | 15 000 / 30 j | 14 j | formation.offres_actives −1, profil.badge, statistiques.avancees, remontees_mois 4 |

**Produits ponctuels**

| Code | Type | Prix | Durée | Effet |
|---|---|---|---|---|
| `offre_emploi_unite` | offre_supplementaire | 3 000 | 30 j | +1 offre d'emploi au-delà du quota du mois |
| `remontee` | remontee | 500 | — | Remet la publication en tête du fil (§14.9) |
| `une_commune_7j` | une_commune | 2 500 | 7 j | En tête des résultats de sa commune et de sa catégorie |
| `sponsorise_fil_7j` | sponsorise_fil | 7 500 | 7 j | Emplacement « Sponsorisé » dans le fil général |

### 14.5 Calcul des droits (service `Droits`)

```php
Droits::pour($titulaire)->peut(CleDroit::AlertesCanalSms);           // bool
Droits::pour($titulaire)->limite(CleDroit::EmploiOffresMois);         // int (-1 = illimité)
Droits::pour($titulaire)->restant(CleDroit::EmploiOffresMois);        // limite - consommation
Droits::pour($titulaire)->verifierOuEchouer(CleDroit::EmploiOffresMois); // lève QuotaAtteintException
```

- **Titulaire selon le contexte** :
  - publication d'emploi ou de formation → l'entreprise choisie, ou l'utilisateur s'il est un particulier ;
  - service, alertes et réponses → l'utilisateur.
- **Droits effectifs** = plan par défaut de la cible, **combiné avec** tous les abonnements `essai`, `actif` ou `grace` en cours :
  - pour un **entier**, on prend le **maximum**, et `-1` l'emporte ;
  - pour un **booléen**, on applique un **OU**.
- On y ajoute les **crédits ponctuels** non consommés (produits `offre_supplementaire` payés et non utilisés).
- **Consommation**, calculée par requête et jamais stockée :
  - `emploi.offres_mois` : publications du titulaire créées dans le mois calendaire, hors `rejetee` et `brouillon` ;
  - `*.offres_actives` : publications `en_attente` ou `publiee` ;
  - `publication.remontees_mois` : mises en avant de type `remontee` incluses, ce mois-ci ;
  - `reponses.demandes_mois` : réponses de type `proposition` ce mois-ci.
- **Si `parametres.quotas_actifs = false`**, `limite()` renvoie `-1` pour toutes les clés de quota (lancement gratuit). Les clés de **canal** (`canal_sms`) restent soumises aux plans.
- **Cache** : droits effectifs en cache `droits:v{version}:{type}:{id}` pendant 10 min. `AbonnementObserver` supprime la clé du titulaire ; `PlanObserver` et `PlanLimiteObserver` incrémentent `droits:version`.
- **Intégration** : `PublicationPolicy@create`, `AlertePolicy@create` et `ReponsePolicy@create` appellent `Droits`. Quand le quota est atteint, le formulaire est remplacé par l'écran **« Quota atteint »** (§14.11), avec les options d'achat pertinentes.
- **Dépassement après expiration** : les publications déjà en ligne restent visibles jusqu'à leur expiration. Seules les **nouvelles** actions sont bloquées. Les alertes au-delà de la limite sont mises en pause (les plus anciennes restent actives).

### 14.6 Essais gratuits

- `plans.duree_essai_jours` (0 = pas d'essai) et `plans.famille` (les plans d'une même famille partagent **un seul essai**).
- **Un essai par numéro de téléphone et par famille, à vie** : table `essais_utilises` avec l'**empreinte SHA-256 du numéro E.164**. L'empreinte survit à la suppression du compte, ce qui empêche l'abus, et le numéro n'est pas conservé en clair.
- Pour un titulaire entreprise, c'est le numéro du propriétaire qui compte.
- **Numéro vérifié obligatoire** (OTP) pour démarrer un essai.
- Démarrage **sans paiement**, en un clic, depuis les incitations contextuelles (§14.11). Crée un `abonnement` au statut `essai`, avec `origine = essai`.
- **Notifications** (canal `database` + SMS si disponible) à J-3, J-1 et J0 : « Votre essai se termine… Continuer pour 1 000 FCFA / 30 jours ».
- À la fin : `expire`, retour au plan par défaut, rien n'est supprimé (§14.5, dépassement).

### 14.7 Commandes, paiement et renouvellement

**Passerelle** : interface `App\Contracts\PasserellePaiement`, avec deux drivers :
- `cinetpay` (production : Orange Money, MTN MoMo, Moov Money, Wave, carte) ;
- `fake` (local et tests : paiement simulé réussi ou échoué).

Variables : `PAIEMENT_DRIVER`, `CINETPAY_API_KEY`, `CINETPAY_SITE_ID`, `CINETPAY_SECRET`.

**Parcours** :
1. Choix d'un plan ou d'un produit, puis `commande` (`en_attente`) avec le montant **figé** au moment de la commande.
2. Redirection vers le guichet CinetPay.
3. **Webhook** `POST /paiements/notification/{passerelle}` :
   - signature vérifiée ;
   - payload journalisé dans `notifications_paiement` ;
   - **statut revérifié par l'API de la passerelle** (on ne fait jamais confiance à la redirection ni au seul webhook) ;
   - traitement **idempotent** (référence unique, verrou `Cache::lock`).
4. Si le paiement est confirmé, l'action `ActiverCommande` :
   - crée ou prolonge l'abonnement, ou applique le produit ;
   - attribue un **numéro de reçu séquentiel** (`REC-2026-000123`) ;
   - notifie l'utilisateur.
5. Page de retour `GET /paiements/retour/{commande}` : affiche « paiement en cours de confirmation » tant que le webhook n'est pas traité (actualisation automatique toutes les 3 s pendant 60 s).

**Autres règles** :
- **Renouvellement** : un nouvel achat du même plan **s'ajoute à la suite** de la période en cours (`debut_le` = ancienne `fin_le`). Le passage d'un essai à un plan payé démarre immédiatement.
- **Délai de grâce** : `parametres.jours_grace` (3 par défaut). Statut `grace`, droits maintenus, bandeau de relance.
- **Montants** : entiers en FCFA **TTC**. Le `taux_tva` (paramètre, 18 %) et le `montant_ht_fcfa` sont stockés sur la commande.
- **Reçu** : page HTML imprimable et téléchargeable en PDF (`/mon-espace/recus/{commande}`), avec les mentions légales issues des paramètres (raison sociale, RCCM, NCC).
- **Remboursement et annulation** : manuels, depuis l'admin (statut `remboursee`, l'abonnement passe en `resilie`).

### 14.8 SMS

- Interface `App\Contracts\FournisseurSms`, avec les drivers `log` (local et tests), `orange` et `twilio` (à choisir, §18). Variable `SMS_DRIVER`.
- Canal de notification `SmsChannel`. `AlerteCorrespondante::via()` ajoute `sms` si `Droits::peut(CanalSms)` **et** que le numéro est vérifié.
- **Plafond quotidien** `alertes.sms_par_jour` : au-delà, les correspondances sont regroupées dans **un récapitulatif SMS** à 19h00.
- **Coût maîtrisé** :
  - messages en **GSM-7 sans accents**, translittérés automatiquement (un accent force l'encodage UCS-2 et réduit le SMS de 160 à 70 caractères) ;
  - **un seul SMS** (≤ 160 caractères) par alerte ;
  - **lien court** interne `/p/{code}` (`publications.code_court`, 6 caractères).
  
  Exemple : `Rezohelp: Electricien dispo Cocody, 5000F. Voir: rezohelp.ci/p/ab12cd`.
- Journal `sms_envoyes` : statut, coût unitaire et référence du fournisseur. Coût mensuel visible dans l'admin.
- Pas d'envoi entre 21h00 et 7h00 : ces SMS sont mis en file jusqu'à 7h00.

### 14.9 Visibilité payante dans le fil

- **Remontée** : met `publications.tri_le = now()`. **Le fil trie sur `tri_le DESC, id DESC`** (`tri_le` = `publiee_le` à la publication), ce qui garde la pagination par curseur intacte. Il y a au maximum une remontée par publication toutes les 24 h.
- **Mises en avant** (table `mises_en_avant`, avec `debut_le` et `fin_le`) :
  - `une_commune` : bloc « À la une » (2 cartes au maximum, rotation) **au-dessus** des résultats filtrés sur la commune ou la catégorie ;
  - `sponsorise_fil` : **emplacements injectés** dans le fil général à la 1re position, puis toutes les `parametres.frequence_sponsorise` cartes (8 par défaut).
- **Injection sans casser le curseur** :
  - le service `EmplacementsSponsorises` reçoit le nombre de cartes déjà affichées (`?deja=24`) et insère les cartes sponsorisées aux positions voulues **en plus** de la salve ;
  - les cartes sponsorisées n'entrent pas dans le curseur ;
  - rotation pondérée par l'ancienneté de la dernière impression ;
  - **une même carte sponsorisée au plus une fois par salve**.
- Chaque carte payée porte l'étiquette **« Sponsorisé »** ou **« À la une »**.
- **Statistiques** : `nb_impressions` et `nb_clics` incrémentés en différé (le compteur est cumulé en cache et vidé en base toutes les 5 min). L'acheteur les voit dans « Mes publications ».
- Une publication `en_attente`, `rejetee` ou `expiree` n'est **jamais** mise en avant. Si elle sort du fil, la mise en avant est suspendue et sa durée restante est conservée.

### 14.10 Admin — module K « Plans et paiements » (permissions `abonnements.gerer`, `paiements.voir`)

- **Plans** (DataTables + formulaire) :
  - code, nom, cible, famille, prix, durée, essai, `est_defaut`, actif, visible sur `/tarifs`, ordre, description ;
  - **éditeur de limites** : la liste de toutes les clés `CleDroit` avec un champ adapté au type (case à cocher, nombre, « illimité »).
  
  Un plan déjà souscrit ne peut pas être supprimé, seulement désactivé.
- **Produits** : même fonctionnement.
- **Abonnements** : liste filtrable (plan, statut, échéance), fiche du titulaire, actions :
  - **offrir ou attribuer manuellement** (`origine = offert_admin`, avec un motif) ;
  - prolonger ;
  - résilier.
- **Commandes et paiements** : liste filtrable, détail avec payload de la passerelle, bouton « Revérifier le statut », remboursement manuel, reçus, export CSV.
- **Revenus** (tableau de bord) :
  - chiffre d'affaires du mois et par source (plans, produits) ;
  - abonnements actifs par plan ;
  - essais en cours et **taux de conversion essai → payant** ;
  - échéances des 7 prochains jours ;
  - coût SMS et coût IA du mois.
- **Paramètres liés** : `quotas_actifs`, `jours_grace`, `taux_tva`, `frequence_sponsorise`, mentions légales du reçu.
- **V2** : codes promo (`codes_promo` : pourcentage ou montant, dates, utilisations max) et offres de lancement.

### 14.11 Pages publiques et incitations

- **`/tarifs`** : générée à partir des plans `visible` et `actif`, groupés par cible (Membres, Recruteurs, Artisans, Organismes).
- **« Mon espace → Mon abonnement »** :
  - plan actuel et échéance ;
  - **compteurs restants** (« 2 / 3 offres ce mois », « SMS : 3 / 5 aujourd'hui ») ;
  - boutons Renouveler et Changer ;
  - historique des commandes et reçus.
- **Incitations contextuelles**, toujours fermables et jamais bloquantes pour la consultation :
  - après la création d'une alerte : « Recevez-la aussi par SMS — **14 jours gratuits** » ;
  - au 3e et dernier quota d'offres : « Il vous reste 1 offre gratuite ce mois-ci » ;
  - **écran « Quota atteint »** : acheter 1 offre (3 000 FCFA) **ou** essayer Entreprise 14 jours ;
  - après la validation d'une annonce artisan : « Remontez votre annonce en tête du fil pour 500 FCFA ».

### 14.12 Calendrier d'activation (sans redéploiement)

| Phase | Réglages dans l'admin |
|---|---|
| **Lancement** | `quotas_actifs = false` ; plans `alertes_sms_*` actifs (essai 14 j) ; produit `remontee` actif ; autres produits et plans payants inactifs |
| **+3 à 6 mois** | `quotas_actifs = true` ; `artisan_pro_30j`, `offre_emploi_unite`, `une_commune_7j` actifs |
| **+6 à 12 mois** | `entreprise_30j`, `organisme_pro_30j`, `sponsorise_fil_7j` actifs ; publicité directe vendue en `sponsorise_fil` |

---

## 15. PWA, responsive et accessibilité

- `manifest.webmanifest` : nom, icônes (192/512 + maskable), `theme_color`, `display: standalone`, `lang: fr`.
- Service worker (Workbox) :
  - *cache-first* pour les assets versionnés ;
  - *stale-while-revalidate* pour les fragments du fil ;
  - *network-first* pour les pages ;
  - **page hors ligne** dédiée ;
  - aucune mise en cache des pages de « Mon espace » ni de l'admin.
- Invitation à installer l'application après 2 visites (bannière discrète).
- Responsive *mobile-first* : 360 px → 1440 px ; navigation basse sur mobile, trois colonnes sur desktop.
- Accessibilité (WCAG 2.1 AA) : contrastes ≥ 4,5:1, cibles tactiles ≥ 44 px, focus visible, libellés de formulaires, `alt` sur les images, navigation au clavier, `prefers-reduced-motion` respecté, `lang="fr"`.

---

## 16. Sécurité et confiance

- Modération des publications natives (première publication de chaque compte toujours en attente).
- Bouton « Signaler » sur chaque carte ; masquage automatique après 3 signalements « arnaque », en attendant revue.
- Bandeau de prévention sur les offres d'emploi : « Aucun recruteur sérieux ne demande d'argent pour un emploi. »
- Badge « Entreprise vérifiée » attribué par l'admin.
- Protection CSRF, validation par FormRequest, `$fillable` explicite, politiques (`Policy`) sur chaque ressource, en-têtes de sécurité (CSP, HSTS).
- Données personnelles : le numéro de téléphone d'un membre n'est jamais public. Seul le `telephone_contact` d'une annonce de service est affiché, et seulement si l'artisan le choisit.
- Conformité à la loi ivoirienne n° 2013-450 sur les données personnelles (ARTCI) : page de confidentialité, suppression du compte sur demande.

---

## 17. Hébergement et déploiement

- Déploiement par Git + `composer install --no-dev --optimize-autoloader`, puis `php artisan migrate --force`, `optimize`, `view:cache` et `npm run build` (ou assets compilés poussés).
- Scheduler : cron `* * * * * php artisan schedule:run`.
- Files : sur mutualisé, cron `queue:work --stop-when-empty` ; sur VPS, Supervisor.
- **Memcached nécessite l'extension `php-memcached` et un serveur Memcached** : à vérifier chez l'hébergeur (voir §18).

---

## 18. Décisions à confirmer

1. **Nom définitif et domaine** (nom de travail : *Rezohelp*).
2. **Hébergement** : un VPS (Memcached, Supervisor, HTTP/2) est fortement recommandé. Sur cPanel mutualisé, il faut vérifier la disponibilité de Memcached, sinon basculer temporairement sur le cache `file` ou `database`.
3. **Fournisseur SMS pour l'OTP** (Orange SMS API, Twilio, ou WhatsApp Business API) et budget associé.
4. **Activer l'enrichissement IA** dès la V1 (coût API faible mais non nul) ou publier les lignes brutes au départ.
5. **Seuil de publication automatique** (score ≥ 70 proposé).
6. **Pays ouverts à l'inscription au lancement** (CI seul, ou toute l'Afrique de l'Ouest francophone).
7. **Prix définitifs** de la grille §14.4.
8. **Compte CinetPay** (KYC de la société) : le bac à sable suffit pour développer, le compte validé est requis avant la mise en production des paiements.
9. **Structure juridique et fiscalité** : raison sociale, RCCM, NCC, régime de TVA, mentions des reçus. À valider avec un comptable.
10. **Textes légaux** : CGU, CGV (abonnements et produits), politique de confidentialité, déclaration à l'ARTCI.

---

## 19. Glossaire

- **Publication** : tout élément du fil (offre d'emploi, service ou formation).
- **Fil** : liste chronologique des publications, chargée en continu.
- **Salve** : lot de publications chargé à chaque défilement (12 par défaut).
- **Veille IA** : collecte automatique toutes les 8 h par Claude, déposée dans Google Drive.
- **Empreinte source** : hachage de l'URL d'origine, utilisé pour éviter les doublons.
- **Score de fiabilité** : note de 0 à 100 estimant le risque d'arnaque.
- **Titulaire** : utilisateur ou entreprise qui détient des droits (§14.2).
- **Clé de droit** : capacité ou limite pilotée par les plans (`CleDroit`, §14.3).
- **Remontée** : achat qui remet une publication en tête du fil (`tri_le = now()`).
- **Mise en avant** : affichage payant « À la une » (commune) ou « Sponsorisé » (fil).
