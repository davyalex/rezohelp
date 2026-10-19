/**
 * Formulaire de publication (CONTEXTE §11.3) : brouillon enregistré dans le navigateur
 * toutes les 5 s, titre proposé pour une offre de service, aperçu de la carte.
 */
const INTERVALLE_BROUILLON = 5000;
const CHAMPS_IGNORES = ['_token', '_method', 'action'];

export function formulairePublication(cle, urlApercu, proposerTitreAuto) {
    return {
        restaure: false,
        titreModifie: false,
        erreurApercu: '',

        init() {
            const champTitre = this.$el.elements.namedItem('titre');
            this.titreModifie = Boolean(champTitre?.value);

            if (!cle) {
                return;
            }

            this.restaurer();
            window.setInterval(() => this.sauvegarder(), INTERVALLE_BROUILLON);
        },

        donnees() {
            const valeurs = {};
            for (const [nom, valeur] of new FormData(this.$el).entries()) {
                if (CHAMPS_IGNORES.includes(nom) || valeur instanceof File) {
                    continue;
                }
                (valeurs[nom] ??= []).push(valeur);
            }
            return valeurs;
        },

        sauvegarder() {
            try {
                window.localStorage.setItem(cle, JSON.stringify(this.donnees()));
            } catch {
                // Stockage indisponible (navigation privée) : le brouillon serveur reste possible.
            }
        },

        restaurer() {
            let brouillon = null;
            try {
                brouillon = JSON.parse(window.localStorage.getItem(cle) ?? 'null');
            } catch {
                return;
            }

            // On ne remplace jamais une saisie déjà présente (retour de validation du serveur).
            const champTitre = this.$el.elements.namedItem('titre');
            if (!brouillon || champTitre?.value) {
                return;
            }

            for (const [nom, valeurs] of Object.entries(brouillon)) {
                const champs = this.$el.querySelectorAll(`[name="${CSS.escape(nom)}"]`);
                champs.forEach((champ) => {
                    if (champ.type === 'checkbox' || champ.type === 'radio') {
                        champ.checked = valeurs.includes(champ.value);
                    } else if (champ.type !== 'hidden' && champ.type !== 'file') {
                        champ.value = valeurs[valeurs.length - 1];
                    }
                    champ.dispatchEvent(new Event('input', { bubbles: true }));
                    champ.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
            this.restaure = true;
            this.titreModifie = true;
        },

        effacer() {
            this.oublier();
            window.location.reload();
        },

        oublier() {
            if (cle) {
                try {
                    window.localStorage.removeItem(cle);
                } catch {
                    // Rien à faire.
                }
            }
        },

        /** « Électricité — disponible à Cocody », tant que le membre n'a pas écrit son propre titre. */
        proposerTitre(evenement) {
            if (!proposerTitreAuto || this.titreModifie || !['categorie_id', 'commune_id'].includes(evenement.target.name)) {
                return;
            }
            const metier = this.$el.elements.namedItem('categorie_id');
            const commune = this.$el.elements.namedItem('commune_id');
            const titre = this.$el.elements.namedItem('titre');
            if (metier?.value && commune?.value && titre) {
                const nomMetier = metier.options[metier.selectedIndex].text.trim();
                const nomCommune = commune.options[commune.selectedIndex].text.trim();
                titre.value = `${nomMetier} — disponible à ${nomCommune}`;
            }
        },

        async apercu() {
            this.erreurApercu = '';
            const donnees = new FormData(this.$el);
            donnees.delete('_method');
            donnees.delete('photos[]');
            donnees.delete('entreprise_logo');

            try {
                const reponse = await fetch(urlApercu, {
                    method: 'POST',
                    // JSON pour recevoir les erreurs de validation en 422 ; la réponse réussie est du HTML.
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: donnees,
                    credentials: 'same-origin',
                });
                if (reponse.status === 422) {
                    this.erreurApercu = 'Complétez les champs obligatoires pour voir l’aperçu.';
                    return;
                }
                if (!reponse.ok) {
                    throw new Error(String(reponse.status));
                }
                this.$refs.apercu.innerHTML = await reponse.text();
            } catch {
                this.erreurApercu = 'L’aperçu n’a pas pu être chargé. Vérifiez votre connexion.';
            }
        },
    };
}
