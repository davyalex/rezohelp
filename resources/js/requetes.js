const jeton = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Envoie un formulaire en JSON. Un visiteur non connecté est envoyé vers la connexion.
 * Renvoie la réponse JSON, ou null en cas d'échec.
 */
export async function envoyerFormulaire(formulaire) {
    // Un fragment du fil peut venir du cache du service worker avec un ancien jeton :
    // le jeton de la page, toujours à jour, remplace celui du formulaire.
    const donnees = new FormData(formulaire);
    donnees.set('_token', jeton());

    try {
        const reponse = await fetch(formulaire.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': jeton(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: donnees,
            credentials: 'same-origin',
        });

        if (reponse.status === 401 || reponse.status === 419) {
            window.location.href = document.querySelector('meta[name="url-connexion"]')?.content ?? '/connexion';
            return null;
        }

        return reponse.ok ? await reponse.json() : null;
    } catch {
        return null;
    }
}
