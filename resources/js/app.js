// Site public : Alpine.js pour les interactions légères, JS vanilla pour le fil (budget < 50 Ko).
// jQuery et les plugins du back-office ne sont JAMAIS importés ici.
import Alpine from 'alpinejs';
import { demarrerFil, suivreMisesEnAvant } from './fil';
import { envoyerFormulaire } from './requetes';
import { formulairePublication } from './publication';
import { demarrerPwa } from './pwa';
import { activerSpinners, enregistrerComposants } from './interface';
import { demarrerConfirmations } from './confirmation';
import { suivrePublicites } from './publicites';

enregistrerComposants(Alpine);
Alpine.data('formulairePublication', formulairePublication);

// « J'aime » optimiste : l'interface change tout de suite, le serveur confirme.
Alpine.data('reaction', (aime, nb) => ({
    aime,
    nb,
    async basculer(formulaire) {
        const avant = { aime: this.aime, nb: this.nb };
        this.aime = !this.aime;
        this.nb += this.aime ? 1 : -1;
        const reponse = await envoyerFormulaire(formulaire);
        if (reponse) {
            this.aime = reponse.aime;
            this.nb = reponse.nb_jaime;
        } else {
            Object.assign(this, avant);
        }
    },
}));

// Bascule simple (enregistrer / retirer).
Alpine.data('bascule', (actif) => ({
    actif,
    async envoyer(formulaire) {
        const avant = this.actif;
        this.actif = !this.actif;
        const reponse = await envoyerFormulaire(formulaire);
        this.actif = reponse ? reponse.actif : avant;
    },
}));

// Incitation fermable : le choix est retenu dans ce navigateur (confort seulement).
Alpine.data('incitation', (cle) => ({
    visible: true,
    init() {
        try {
            this.visible = window.localStorage.getItem(`incitation:${cle}`) === null;
        } catch {
            this.visible = true;
        }
    },
    fermer() {
        this.visible = false;
        try {
            window.localStorage.setItem(`incitation:${cle}`, '1');
        } catch {
            // Stockage indisponible : l'incitation reviendra à la prochaine visite.
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();

// Avant les spinners : un envoi suspendu par la confirmation n'active pas le spinner.
demarrerConfirmations();
activerSpinners();
demarrerFil();
suivreMisesEnAvant();
suivrePublicites();
demarrerPwa();
