const jeton = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Envoie un formulaire en JSON. Un visiteur non connecté est envoyé vers la connexion.
 * Renvoie la réponse JSON, ou null en cas d'échec.
 */
export async function envoyerFormulaire(formulaire) {
    try {
        const reponse = await fetch(formulaire.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': jeton(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(formulaire),
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
