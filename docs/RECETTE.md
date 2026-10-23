# Rezohelp — Recette

Liste à cocher en **préproduction** (HTTPS, Memcached, worker et cron actifs), après un déploiement selon `docs/DEPLOIEMENT.md`.

- Comptes de test : deux téléphones ou deux navigateurs (membre A, membre B), plus le super administrateur du `.env`.
- Tant qu'aucun fournisseur SMS n'est branché, les codes et SMS se lisent dans `storage/logs/sms.log` (`SMS_DRIVER=log`).
- Pour les paiements : soit le bac à sable CinetPay, soit, en préproduction seulement, le guichet simulé (`PAIEMENT_DRIVER=fake`).

La recette est terminée quand toutes les cases sont cochées (critère d'acceptation du lot 10).

---

## 1. Installation et qualité

- [ ] `php artisan migrate:fresh --seed --force` sur une base vide se termine sans erreur ; le super administrateur est créé.
- [ ] Contrôle des référentiels en base : 13 communes d'Abidjan ; 9 plans, dont 4 « par défaut » (un par cible).
- [ ] Les prix et limites des plans correspondent à la grille §14.4 (valeurs ajoutées : D-016).
- [ ] Les mots à risque et les plafonds de salaire (Admin → Paramètres) vous conviennent.
- [ ] La CI GitHub est verte sur la dernière version : Pint, Larastan, tests (dont les tests de bout en bout dans Chromium), budget JS, migrations.
- [ ] `npm run build && npm run budget-js` : moins de 50 Ko de JS public compressé.

## 2. Visiteur (sans compte)

- [ ] Sur mobile (360 px), le fil s'affiche sans saut de mise en page ; le défilement charge la suite automatiquement ; « Vous êtes à jour 🎉 » en fin de fil.
- [ ] Les onglets Tout · Emplois · Home service · **Demandes** · Formations filtrent le fil ; la liste « Commune », « Aujourd'hui » et les contrats aussi.
- [ ] Sur ordinateur : trois colonnes ; la recherche (loupe) et le bouton « Publier une annonce » sont bien distincts.
- [ ] Une annonce de la veille porte « Voir l'offre sur Educarrière ↗ », qui ouvre l'annonce d'origine dans un nouvel onglet.
- [ ] Page détail d'une offre de la veille :
  - « Informations clés » en tête (date limite avec les jours restants, contrat, lieu, salaire s'il est connu) ;
  - puis « Postuler sur … », puis la description ;
  - aucune note interne de la veille (« à vérifier ») dans le texte.
- [ ] « Partager sur WhatsApp » ouvre WhatsApp avec le titre et le lien court `/p/…`.
- [ ] Une offre d'emploi passe le test des résultats enrichis de Google (bloc `application/ld+json`).

## 3. Compte et authentification

- [ ] S'inscrire avec un numéro ivoirien (« 05 06 07 08 09 ») : arrivée sur « Vérifiez votre numéro » ; saisir le code reçu : numéro vérifié.
- [ ] Se déconnecter, puis se reconnecter en tapant « +225 05 06 07 08 09 » : connexion réussie.
- [ ] « Mot de passe oublié ? » : code reçu, nouveau mot de passe, connexion.
- [ ] Six mauvaises tentatives de connexion en moins d'une minute : la sixième est refusée.
- [ ] Un mot de passe connu des fuites (par exemple « Password123 ») est refusé à l'inscription.
- [ ] Profil :
  - changer la photo et le nom ;
  - changer de numéro (code SMS) ;
  - supprimer un compte de test.

## 4. Alertes et notifications

- [ ] Sans compte, filtrer sur « Home service » + « Cocody », puis « Créer une alerte » : inscription, puis formulaire d'alerte pré-rempli.
- [ ] Créer l'alerte « Électricien · Cocody ». Avec le membre B, publier une annonce correspondante et la faire valider : le membre A reçoit la notification (pastille sur la cloche).
- [ ] Mettre l'alerte en pause : plus de notification. `php artisan alertes:recap-quotidien` envoie le récapitulatif d'une alerte quotidienne.
- [ ] Supprimer une alerte : la boîte de confirmation du site s'ouvre (Annuler / Supprimer), pas celle du navigateur.

## 5. Publication par les membres

- [ ] Depuis un mobile, publier un exemple de chacun des 4 types. La première publication part « en attente ».
- [ ] Un titre court (« Plombier ») et une description longue sont acceptés : il n'y a plus de minimum (D-069).
- [ ] Une publication contenant « frais de dossier » part en attente, avec le signal visible en modération.
- [ ] Couper la connexion pendant la saisie puis revenir : le brouillon est restauré. « Voir l'aperçu dans le fil » montre la même carte que le fil.
- [ ] « Mes publications » :
  - modifier, clôturer, prolonger (3 fois au plus), dupliquer ;
  - supprimer, avec la boîte de confirmation du site.
- [ ] Avec le membre B :
  - postuler à une offre ;
  - répondre « Je suis disponible » à une demande de prestation ;
  - l'auteur retrouve la réponse et le numéro.
- [ ] Une demande de prestation validée apparaît :
  - dans l'onglet « Demandes » ;
  - dans l'encadré « Ils cherchent un prestataire » (fil et colonne de droite) ;
  - avec sa bordure orange et le bouton « Je suis disponible ».

## 6. Abonnements, paiements et SMS

- [ ] `/tarifs` ne montre que les plans actifs et visibles (au lancement : alertes SMS 7 j et 30 j, remontée).
- [ ] Après la création d'une alerte, « Recevez-la aussi par SMS — 14 jours gratuits » ; « Essayer gratuitement » démarre l'essai (refusé sans numéro vérifié).
- [ ] « Mon abonnement » → « Choisir · 1 000 FCFA » → paiement (bac à sable CinetPay ou guichet simulé), puis vérifier :
  - le reçu `REC-AAAA-…` et son PDF ;
  - l'abonnement actif.
- [ ] Recette CinetPay en bac à sable : la notification réelle est reçue et revérifiée, et les champs signés du jeton `x-token` sont corrects (D-053).
- [ ] Avec un essai SMS et un numéro vérifié, une offre correspondant à une alerte envoie un SMS court sans accents (log du fournisseur).
- [ ] `quotas_actifs = 1` (Admin → Paramètres) :
  - l'écran « Quota atteint » propose l'achat et l'essai ;
  - « Il vous reste 1 offre » s'affiche avant la limite.
- [ ] Activer `une_commune_7j` et `sponsorise_fil_7j`, puis acheter une mise en avant :
  - carte « Sponsorisé » dans le fil ;
  - « À la une » sur la commune.
- [ ] `php artisan abonnements:traiter` : relances J-3 / J-1 / J0, grâce, expiration (dates à ajuster en base).

## 7. Back-office

- [ ] Se connecter sur `/admin/connexion` avec le super administrateur ; changer le mot de passe provisoire.
- [ ] Tableau de bord : compteurs, graphique, bandeau rouge si aucun import depuis plus de 9 h.
- [ ] Modération :
  - valider, refuser et marquer un doublon au clavier (V, R, D, →) ;
  - essayer une action groupée ;
  - la note de la veille est visible sur une annonce importée.
- [ ] Signalements : une publication signalée 3 fois « arnaque » est masquée ; tester les trois décisions.
- [ ] Membres :
  - rechercher `07 08 09 10 11` puis `+225…` ;
  - suspendre puis réactiver un membre ;
  - réinitialiser un mot de passe.
- [ ] Équipe :
  - créer un modérateur : il ne voit que ses modules (pas de plans, paramètres, référentiels ni veille) ;
  - impossible de se suspendre soi-même ou de retirer le dernier super administrateur.
- [ ] Plans :
  - modifier le prix d'« Alertes SMS 30 jours » et une limite d'« Artisan gratuit », puis vérifier `/tarifs` et « Mon abonnement » ;
  - offrir « Artisan Pro » à un artisan.
- [ ] Paramètres : modifier `frequence_sponsorise`, puis vider le cache du fil.

## 8. Veille (Google Drive)

- [ ] `php artisan veille:importer` sur le dossier « Offres Emploi Abidjan » :
  - les annonces arrivent avec leur catégorie, leur date de clôture et leur source ;
  - une deuxième exécution répond « Aucun nouveau fichier ».
- [ ] « Relancer l'import » ne crée aucun doublon.
- [ ] Déclarer les sites fiables dans « Sources de la veille » (confiance haute + publication automatique) : leurs annonces en attente passent en ligne après `queue:work`.
- [ ] Le lendemain d'une date de clôture, l'annonce a quitté le fil ; 5 jours plus tard, elle est supprimée.
- [ ] Créer si besoin une catégorie de service « Gardiennage et sécurité ».

## 9. PWA, performance et accessibilité

- [ ] Installer l'application sur un téléphone Android (Chrome → « Installer l'application ») : icône, ouverture plein écran.
- [ ] En mode avion, rouvrir l'application : « Vous êtes hors connexion » s'affiche ; « Mon espace » n'est jamais servi depuis le cache.
- [ ] Lighthouse mobile sur `/` et sur une page détail : Performance ≥ 90, Accessibilité ≥ 95, Bonnes pratiques ≥ 95, SEO ≥ 95.
- [ ] Navigation au clavier sur le parcours public : lien « Aller au contenu », focus visible, menus et boîte de confirmation utilisables sans souris.

## 10. Mise en production

- [ ] `.env` de production relu (section 2 de `DEPLOIEMENT.md`) : `APP_DEBUG=false`, `PAIEMENT_DRIVER=cinetpay`, aucune clé dans le dépôt.
- [ ] Cron et worker actifs ; vérifications de la section 9 de `DEPLOIEMENT.md` cochées.
- [ ] Sauvegarde nocturne en place, et une restauration testée une fois.
- [ ] Textes légaux (CGU, confidentialité) et mentions des reçus complétés dans Admin → Paramètres (CONTEXTE §18).
