/**
 * Fil infini (CONTEXTE §8) : la première salve est rendue par le serveur ; les suivantes
 * arrivent en fragments HTML de /fil/suite, le curseur suivant dans l'en-tête X-Curseur-Suivant.
 * Le lien « Charger plus » reste utilisable au clavier et sans JavaScript.
 */
const INTERVALLE_NOUVELLES = 60_000;

export function demarrerFil() {
    const fil = document.getElementById('fil');
    if (!fil) {
        return;
    }

    const lienPlus = document.querySelector('[data-charger-plus]');
    const fin = document.querySelector('[data-fin]');
    const squelette = document.getElementById('squelette-carte');
    let curseur = fil.dataset.curseur || '';
    // Cartes du fil déjà affichées : le serveur place les cartes sponsorisées en conséquence.
    let deja = Number(fil.dataset.deja || 0);
    let enCours = false;

    const urlSuite = () => {
        const url = new URL(fil.dataset.suite, window.location.origin);
        url.searchParams.set('curseur', curseur);
        url.searchParams.set('deja', String(deja));
        return url;
    };

    const terminer = () => {
        lienPlus?.remove();
        fin?.removeAttribute('hidden');
        observateur?.disconnect();
    };

    async function chargerSuite() {
        if (enCours || !curseur) {
            return;
        }
        enCours = true;
        fil.setAttribute('aria-busy', 'true');
        const squelettes = [0, 1].map(() => fil.appendChild(squelette.content.firstElementChild.cloneNode(true)));

        try {
            const reponse = await fetch(urlSuite(), { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!reponse.ok) {
                throw new Error(String(reponse.status));
            }
            const fragment = document.createElement('template');
            fragment.innerHTML = await reponse.text();
            curseur = reponse.headers.get('X-Curseur-Suivant') || '';
            deja += fragment.content.querySelectorAll('[data-carte]').length;
            squelettes.forEach((element) => element.remove());
            fil.append(fragment.content);
            if (lienPlus && curseur) {
                const url = new URL(lienPlus.href);
                url.searchParams.set('curseur', curseur);
                url.searchParams.set('deja', String(deja));
                lienPlus.href = url;
            }
            if (!curseur) {
                terminer();
            }
        } catch {
            squelettes.forEach((element) => element.remove());
        } finally {
            enCours = false;
            fil.setAttribute('aria-busy', 'false');
        }
    }

    const sentinelle = document.getElementById('fin-fil');
    const observateur = curseur && 'IntersectionObserver' in window
        ? new IntersectionObserver((entrees) => entrees.some((entree) => entree.isIntersecting) && chargerSuite(), { rootMargin: '800px 0px' })
        : null;
    observateur?.observe(sentinelle);

    lienPlus?.addEventListener('click', (evenement) => {
        evenement.preventDefault();
        chargerSuite();
    });

    surveillerNouvelles(fil);
}

/**
 * Clics sur les cartes « Sponsorisé » / « À la une » : comptés sans retarder la navigation.
 */
export function suivreMisesEnAvant() {
    const jeton = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!jeton || !('sendBeacon' in navigator)) {
        return;
    }

    document.addEventListener('click', (evenement) => {
        const lien = evenement.target.closest('a[href]');
        const carte = lien?.closest('[data-mise-en-avant]');
        if (!carte || carte.dataset.clicCompte) {
            return;
        }
        carte.dataset.clicCompte = '1';
        const donnees = new FormData();
        donnees.append('_token', jeton);
        navigator.sendBeacon(carte.dataset.miseEnAvant, donnees);
    });
}

/**
 * Bandeau « N nouvelles publications ↑ » plutôt qu'un saut de contenu.
 */
function surveillerNouvelles(fil) {
    const bandeau = document.getElementById('bandeau-nouvelles');
    if (!bandeau || !fil.dataset.depuis) {
        return;
    }

    const verifier = async () => {
        if (document.hidden) {
            return;
        }
        const url = new URL(fil.dataset.nouvelles, window.location.origin);
        url.searchParams.set('depuis', fil.dataset.depuis);
        try {
            const reponse = await fetch(url, { headers: { Accept: 'application/json' } });
            const { nombre } = await reponse.json();
            if (nombre > 0) {
                bandeau.querySelector('[data-nouvelles-nombre]').textContent =
                    nombre === 1 ? '1 nouvelle publication' : `${nombre} nouvelles publications`;
                bandeau.hidden = false;
            }
        } catch {
            // Silencieux : le bandeau est un confort, pas une fonction essentielle.
        }
    };

    bandeau.querySelector('[data-nouvelles-bouton]').addEventListener('click', () => {
        window.scrollTo({ top: 0 });
        window.location.reload();
    });

    window.setInterval(verifier, INTERVALLE_NOUVELLES);
}
