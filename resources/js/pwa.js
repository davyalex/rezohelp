/**
 * PWA (CONTEXTE §15) : enregistrement du service worker (production seulement) et invitation
 * discrète à installer l'application, à partir de la 2e visite, tant qu'elle n'a pas été refusée.
 */
const CLE_VISITES = 'pwa:visites';
const CLE_REFUS = 'pwa:invitation-refusee';
const VISITES_AVANT_INVITATION = 2;

function lire(cle) {
    try {
        return window.localStorage.getItem(cle);
    } catch {
        return null;
    }
}

function ecrire(cle, valeur) {
    try {
        window.localStorage.setItem(cle, valeur);
    } catch {
        // Stockage indisponible (navigation privée) : l'invitation reste un simple confort.
    }
}

/** Une visite = une session de navigation. */
function compterVisite() {
    let dejaComptee = false;
    try {
        dejaComptee = window.sessionStorage.getItem(CLE_VISITES) !== null;
        window.sessionStorage.setItem(CLE_VISITES, '1');
    } catch {
        // Sans sessionStorage, chaque page compte : l'invitation vient simplement plus tôt.
    }
    const visites = Number(lire(CLE_VISITES) ?? 0) + (dejaComptee ? 0 : 1);
    ecrire(CLE_VISITES, String(visites));

    return visites;
}

export function demarrerPwa() {
    // Pas de service worker dans un navigateur piloté par un outil de test (navigator.webdriver) :
    // il intercepterait les navigations des tests de bout en bout. Toujours faux pour un visiteur.
    if ('serviceWorker' in navigator && import.meta.env.PROD && !navigator.webdriver) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {}));
    }

    const visites = compterVisite();
    const bandeau = document.getElementById('invitation-installation');
    if (!bandeau) {
        return;
    }

    window.addEventListener('beforeinstallprompt', (evenement) => {
        evenement.preventDefault();
        if (visites < VISITES_AVANT_INVITATION || lire(CLE_REFUS) !== null) {
            return;
        }
        bandeau.hidden = false;

        bandeau.querySelector('[data-installer]')?.addEventListener('click', async () => {
            bandeau.hidden = true;
            await evenement.prompt();
        }, { once: true });

        bandeau.querySelector('[data-plus-tard]')?.addEventListener('click', () => {
            bandeau.hidden = true;
            ecrire(CLE_REFUS, '1');
        }, { once: true });
    });

    window.addEventListener('appinstalled', () => {
        bandeau.hidden = true;
        ecrire(CLE_REFUS, '1');
    });
}
