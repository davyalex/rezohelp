// Petits composants Alpine partagés par le site public et le back-office (aucune dépendance).

/**
 * Menu déroulant accessible : bouton [x-ref="bouton"] + panneau [x-ref="panneau"].
 * Échap ferme et rend le focus au bouton, flèches haut / bas parcourent les liens, clic extérieur ferme.
 */
export function menuDeroulant() {
    return {
        ouvert: false,
        basculer() {
            this.ouvert ? this.fermer() : this.ouvrir();
        },
        ouvrir() {
            this.ouvert = true;
            this.$nextTick(() => this.elements()[0]?.focus());
        },
        fermer(rendreFocus = true) {
            if (!this.ouvert) {
                return;
            }
            this.ouvert = false;
            if (rendreFocus) {
                this.$refs.bouton?.focus();
            }
        },
        elements() {
            return [...(this.$refs.panneau?.querySelectorAll('a[href], button:not([disabled])') ?? [])];
        },
        deplacer(pas) {
            const elements = this.elements();
            if (elements.length === 0) {
                return;
            }
            const index = elements.indexOf(document.activeElement);
            elements[(index + pas + elements.length) % elements.length].focus();
        },
    };
}

/**
 * Rangée de puces défilante : flèches visibles seulement s'il reste du contenu de ce côté,
 * et l'élément actif ([aria-pressed=true] ou [aria-current]) est amené dans la vue au chargement.
 */
export function defilementHorizontal() {
    return {
        gauche: false,
        droite: false,
        init() {
            const zone = this.$refs.zone;
            const actif = zone.querySelector('[aria-pressed="true"], [aria-current]');
            if (actif) {
                zone.scrollLeft = actif.offsetLeft - zone.clientWidth / 2 + actif.clientWidth / 2;
            }
            this.mesurer();
            new ResizeObserver(() => this.mesurer()).observe(zone);
        },
        mesurer() {
            const zone = this.$refs.zone;
            this.gauche = zone.scrollLeft > 4;
            this.droite = zone.scrollLeft + zone.clientWidth < zone.scrollWidth - 4;
        },
        // Molette verticale de la souris : fait défiler la rangée à l'horizontale tant qu'il reste de la place,
        // puis rend la main au défilement de la page.
        molette(evenement) {
            const zone = this.$refs.zone;
            if (Math.abs(evenement.deltaY) <= Math.abs(evenement.deltaX)) {
                return;
            }
            const peutDefiler = evenement.deltaY > 0
                ? zone.scrollLeft + zone.clientWidth < zone.scrollWidth - 1
                : zone.scrollLeft > 0;
            if (peutDefiler) {
                evenement.preventDefault();
                zone.scrollLeft += evenement.deltaY;
            }
        },
        defiler(sens) {
            const zone = this.$refs.zone;
            const reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            zone.scrollBy({ left: sens * zone.clientWidth * 0.75, behavior: reduit ? 'auto' : 'smooth' });
        },
    };
}

/**
 * Affichage / masquage d'un mot de passe (x-champ type="password").
 */
export function motDePasse() {
    return {
        visible: false,
        basculer() {
            this.visible = !this.visible;
            this.$refs.champ.focus();
        },
    };
}

export function enregistrerComposants(Alpine) {
    Alpine.data('menuDeroulant', menuDeroulant);
    Alpine.data('defilementHorizontal', defilementHorizontal);
    Alpine.data('motDePasse', motDePasse);
}

const SPINNER = '<svg data-spinner aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-4 shrink-0 animate-spin">'
    + '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/>'
    + '<path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';

/** Au-delà, le bouton redevient utilisable (téléchargement qui ne change pas de page, réseau perdu…). */
const DELAI_MAXIMAL = 20000;

function liberer(formulaire) {
    delete formulaire.dataset.envoiEnCours;
    formulaire.querySelectorAll('[data-chargement]').forEach((bouton) => {
        bouton.removeAttribute('data-chargement');
        bouton.removeAttribute('aria-busy');
        bouton.removeAttribute('aria-disabled');
        bouton.querySelector('[data-spinner]')?.remove();
    });
}

/**
 * Spinner sur le bouton qui envoie un formulaire, et pas de double envoi.
 * Ignoré quand l'envoi a été annulé ou repris en JS (preventDefault : J'aime, confirmations…),
 * pour les formulaires ouverts dans un autre onglet et ceux marqués data-sans-spinner.
 * À enregistrer après les autres écouteurs « submit » du document.
 */
export function activerSpinners() {
    document.addEventListener('submit', (evenement) => {
        const formulaire = evenement.target;
        if (!(formulaire instanceof HTMLFormElement) || formulaire.target || 'sansSpinner' in formulaire.dataset) {
            return;
        }
        if (formulaire.dataset.envoiEnCours) {
            evenement.preventDefault();
            return;
        }
        if (evenement.defaultPrevented) {
            return;
        }

        const bouton = evenement.submitter ?? formulaire.querySelector('button[type="submit"], button:not([type])');
        formulaire.dataset.envoiEnCours = '1';
        // Pas de « disabled » : le bouton cliqué doit rester dans les données envoyées (name/value).
        if (bouton instanceof HTMLButtonElement && !bouton.hasAttribute('data-chargement')) {
            bouton.setAttribute('data-chargement', '');
            bouton.setAttribute('aria-busy', 'true');
            bouton.setAttribute('aria-disabled', 'true');
            bouton.insertAdjacentHTML('afterbegin', SPINNER);
        }
        window.setTimeout(() => liberer(formulaire), DELAI_MAXIMAL);
    });

    // Retour arrière (cache du navigateur) : la page revient telle qu'elle était, spinner compris.
    window.addEventListener('pageshow', (evenement) => {
        if (evenement.persisted) {
            document.querySelectorAll('form[data-envoi-en-cours]').forEach(liberer);
        }
    });
}
