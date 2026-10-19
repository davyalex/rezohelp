// Site public : Alpine.js pour les interactions légères, JS vanilla pour le fil (budget < 50 Ko).
// jQuery et les plugins du back-office ne sont JAMAIS importés ici.
import Alpine from 'alpinejs';
import { demarrerFil } from './fil';
import { envoyerFormulaire } from './requetes';
import { formulairePublication } from './publication';

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

window.Alpine = Alpine;
Alpine.start();

demarrerFil();
