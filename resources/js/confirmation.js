// Confirmation des actions destructives sur le site public, dans une boîte du site plutôt que
// la boîte native du navigateur (window.confirm). Sans jQuery ni SweetAlert2 (budget JS public).
//
// <form data-confirmer="Supprimer cette alerte ?" data-detail="Elle ne vous préviendra plus." data-bouton="Supprimer" data-danger>
//
// La boîte est l'élément <dialog id="dialogue-confirmation"> du layout public : focus piégé,
// Échap pour annuler et retour du focus au bouton d'origine sont gérés par le navigateur.

export function demarrerConfirmations() {
    const dialogue = document.getElementById('dialogue-confirmation');

    if (!(dialogue instanceof HTMLDialogElement)) {
        return;
    }

    const titre = dialogue.querySelector('[data-titre]');
    const detail = dialogue.querySelector('[data-detail]');
    const confirmer = dialogue.querySelector('[data-confirmer]');
    let enAttente = null;

    document.addEventListener('submit', (evenement) => {
        const formulaire = evenement.target;

        if (!(formulaire instanceof HTMLFormElement) || !formulaire.dataset.confirmer || formulaire.dataset.confirme) {
            return;
        }

        evenement.preventDefault();
        enAttente = { formulaire, bouton: evenement.submitter };

        titre.textContent = formulaire.dataset.confirmer;
        detail.textContent = formulaire.dataset.detail ?? '';
        detail.hidden = !formulaire.dataset.detail;
        confirmer.textContent = formulaire.dataset.bouton ?? 'Confirmer';
        confirmer.classList.toggle('bg-red-700', 'danger' in formulaire.dataset);
        confirmer.classList.toggle('hover:bg-red-800', 'danger' in formulaire.dataset);
        confirmer.classList.toggle('bg-brand', !('danger' in formulaire.dataset));
        confirmer.classList.toggle('hover:bg-brand-fonce', !('danger' in formulaire.dataset));

        dialogue.returnValue = '';
        dialogue.showModal();
    });

    // Un clic sur le fond (hors de la boîte) annule.
    dialogue.addEventListener('click', (evenement) => {
        if (evenement.target === dialogue) {
            dialogue.close('annuler');
        }
    });

    dialogue.addEventListener('close', () => {
        if (dialogue.returnValue === 'confirmer' && enAttente) {
            const { formulaire, bouton } = enAttente;
            formulaire.dataset.confirme = '1';
            formulaire.requestSubmit(bouton instanceof HTMLElement && formulaire.contains(bouton) ? bouton : undefined);
        }
        enAttente = null;
    });
}
