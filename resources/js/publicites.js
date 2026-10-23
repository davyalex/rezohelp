// Impressions des publicités (D-074) : une bannière compte quand au moins la moitié est restée visible
// une seconde ; une seule fois par page. Les bannières ajoutées par le fil infini sont suivies aussi.
// Envoi groupé (sendBeacon) toutes les 3 s et quand la page est quittée.

const SEUIL = 0.5;
const DUREE_VISIBLE = 1000;

export function suivrePublicites() {
    const jeton = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!jeton || !('IntersectionObserver' in window) || !('sendBeacon' in navigator)) {
        return;
    }

    const enAttente = new Set();
    const minuteries = new Map();

    const envoyer = () => {
        if (enAttente.size === 0) {
            return;
        }
        const donnees = new FormData();
        donnees.append('_token', jeton);
        enAttente.forEach((id) => donnees.append('ids[]', id));
        enAttente.clear();
        navigator.sendBeacon('/pub/vues', donnees);
    };

    const observateur = new IntersectionObserver((entrees) => {
        entrees.forEach(({ target, isIntersecting }) => {
            if (isIntersecting) {
                minuteries.set(target, window.setTimeout(() => {
                    enAttente.add(target.dataset.publiciteId);
                    observateur.unobserve(target);
                    minuteries.delete(target);
                }, DUREE_VISIBLE));
            } else {
                window.clearTimeout(minuteries.get(target));
                minuteries.delete(target);
            }
        });
    }, { threshold: SEUIL });

    const suivre = (racine) => {
        racine.querySelectorAll?.('[data-publicite-id]:not([data-publicite-suivie])').forEach((element) => {
            element.dataset.publiciteSuivie = '1';
            observateur.observe(element);
        });
    };

    suivre(document);
    new MutationObserver((changements) => {
        changements.forEach((changement) => changement.addedNodes.forEach((noeud) => noeud instanceof Element && suivre(noeud.parentElement ?? noeud)));
    }).observe(document.body, { childList: true, subtree: true });

    window.setInterval(envoyer, 3000);
    document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && envoyer());
}
