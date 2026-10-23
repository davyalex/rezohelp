// Back-office uniquement : jQuery, DataTables (mode serveur), Select2, SweetAlert2.
// Aucun script en ligne dans les vues : tout se déclare par attributs data-*.
import jQuery from 'jquery';
import DataTable from 'datatables.net-dt';
import select2 from 'select2';
import Swal from 'sweetalert2';
import Alpine from 'alpinejs';
import { activerSpinners, enregistrerComposants } from './interface';

window.$ = window.jQuery = jQuery;
select2(window, jQuery);

window.DataTable = DataTable;
window.Swal = Swal;

// Valeurs par défaut communes à toutes les listes : traitement côté serveur, en français.
Object.assign(DataTable.defaults, {
    serverSide: true,
    processing: true,
    pageLength: 25,
    language: {
        processing: 'Chargement…',
        search: 'Rechercher :',
        lengthMenu: 'Afficher _MENU_ lignes',
        info: '_START_ à _END_ sur _TOTAL_',
        infoEmpty: 'Aucun résultat',
        infoFiltered: '(filtré sur _MAX_)',
        zeroRecords: 'Aucun résultat',
        emptyTable: 'Aucune donnée',
        paginate: { first: 'Premier', previous: 'Précédent', next: 'Suivant', last: 'Dernier' },
    },
});

const toast = Swal.mixin({ toast: true, position: 'top-end', timer: 4000, timerProgressBar: true, showConfirmButton: false });

/**
 * <table data-datatable='{"ajax": "...", "columns": [...], "order": [...]}' data-filtres="#id-formulaire">
 * Les champs du formulaire de filtres sont envoyés avec chaque requête et rechargent la liste.
 */
function demarrerListes() {
    document.querySelectorAll('table[data-datatable]').forEach((element) => {
        const config = JSON.parse(element.dataset.datatable);
        const filtres = element.dataset.filtres ? document.querySelector(element.dataset.filtres) : null;

        const table = new DataTable(element, {
            ...config,
            ajax: {
                url: config.ajax,
                data: (donnees) => {
                    if (filtres) {
                        new FormData(filtres).forEach((valeur, cle) => {
                            donnees[cle] = valeur;
                        });
                    }
                },
            },
        });

        if (filtres) {
            const recharger = (evenement) => {
                evenement.preventDefault();
                table.ajax.reload();
            };
            filtres.addEventListener('submit', recharger);
            filtres.addEventListener('change', recharger);
        }

        // Actions groupées : cases à cocher de la colonne « selection ».
        const tout = element.querySelector('[data-tout-cocher]');
        tout?.addEventListener('change', () => {
            element.querySelectorAll('input[data-selection]').forEach((caseACocher) => {
                caseACocher.checked = tout.checked;
            });
        });
    });
}

/**
 * <form data-confirmer="Supprimer ?" data-motif="Motif du rejet" data-champ-motif="motif_rejet_detail">
 * Confirmation SweetAlert2 ; avec data-motif, un texte est demandé et copié dans le champ indiqué.
 * Délégué au document : fonctionne aussi pour les formulaires rendus dans les listes.
 */
function demarrerConfirmations() {
    document.addEventListener('submit', async (evenement) => {
        const formulaire = evenement.target;
        if (!(formulaire instanceof HTMLFormElement) || !formulaire.dataset.confirmer || formulaire.dataset.confirme) {
            return;
        }
        evenement.preventDefault();

        const avecMotif = Boolean(formulaire.dataset.motif);
        const resultat = await Swal.fire({
            title: formulaire.dataset.confirmer,
            icon: formulaire.dataset.danger ? 'warning' : 'question',
            input: avecMotif ? 'textarea' : undefined,
            inputLabel: formulaire.dataset.motif,
            inputValidator: avecMotif ? (valeur) => (!valeur || !valeur.trim() ? 'Indiquez un motif.' : undefined) : undefined,
            showCancelButton: true,
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: formulaire.dataset.danger ? '#b91c1c' : '#0e5e6f',
        });
        if (!resultat.isConfirmed) {
            return;
        }
        if (avecMotif) {
            const champ = formulaire.querySelector(`[name="${formulaire.dataset.champMotif}"]`)
                ?? Object.assign(document.createElement('input'), { type: 'hidden', name: formulaire.dataset.champMotif });
            champ.value = resultat.value;
            formulaire.append(champ);
        }
        // Actions groupées : les cases cochées de la liste sont ajoutées au formulaire.
        if (formulaire.dataset.selection) {
            document.querySelectorAll(`${formulaire.dataset.selection} input[data-selection]:checked`).forEach((caseACocher) => {
                formulaire.append(Object.assign(document.createElement('input'), { type: 'hidden', name: 'ids[]', value: caseACocher.value }));
            });
        }
        formulaire.dataset.confirme = '1';
        formulaire.requestSubmit();
    });
}

/**
 * <select data-select2 data-ajax="/admin/recherche/utilisateurs">
 */
function demarrerSelections() {
    jQuery('select[data-select2]').each((_, element) => {
        const url = element.dataset.ajax;
        jQuery(element).select2({
            width: '100%',
            allowClear: !element.required && !element.multiple,
            placeholder: element.dataset.placeholder || 'Choisir…',
            language: { noResults: () => 'Aucun résultat', searching: () => 'Recherche…', inputTooShort: () => 'Tapez au moins 2 caractères' },
            ...(url ? { minimumInputLength: 2, ajax: { url, dataType: 'json', delay: 250, data: (p) => ({ q: p.term }) } } : {}),
        });
    });
}

/**
 * Messages de retour posés par le serveur : <div data-toast="succes" data-message="…">.
 */
function afficherMessages() {
    document.querySelectorAll('[data-toast]').forEach((element) => {
        toast.fire({ icon: element.dataset.toast === 'erreur' ? 'error' : 'success', title: element.dataset.message });
    });
}

/**
 * Modération au clavier : V valider, R refuser, D doublon, → suivante (ignoré pendant la saisie).
 */
Alpine.data('raccourcisModeration', () => ({
    touche(evenement) {
        if (evenement.ctrlKey || evenement.metaKey || evenement.altKey || evenement.repeat) {
            return;
        }
        if (evenement.target.closest('input, textarea, select, [contenteditable]') || document.querySelector('.swal2-container')) {
            return;
        }
        const refs = this.$refs;
        const actions = {
            v: () => refs.valider?.requestSubmit(),
            r: () => refs.rejeter && (refs.rejeter.open = true),
            d: () => refs.doublon && (refs.doublon.open = true),
            arrowright: () => refs.suivante && (window.location.href = refs.suivante.href),
        };
        const action = actions[evenement.key.toLowerCase()];
        if (action) {
            evenement.preventDefault();
            action();
        }
    },
}));

/**
 * Préférence d'affichage lue par le serveur au rendu suivant (pas de clignotement au chargement).
 * Cookies non chiffrés, déclarés dans bootstrap/app.php (encryptCookies except).
 */
function ecrireCookie(nom, valeur) {
    document.cookie = `${nom}=${encodeURIComponent(valeur)}; path=/admin; max-age=31536000; SameSite=Lax`;
}

function lireCookie(nom) {
    const ligne = document.cookie.split('; ').find((morceau) => morceau.startsWith(`${nom}=`));
    return ligne ? decodeURIComponent(ligne.slice(nom.length + 1)) : '';
}

/**
 * Mise en page du back-office : menu mobile, barre latérale réduite, groupes repliés.
 */
Alpine.data('layoutAdmin', (reduit) => ({
    menu: false,
    reduit,
    basculerReduit() {
        this.reduit = !this.reduit;
        ecrireCookie('admin_menu_reduit', this.reduit ? '1' : '0');
    },
    memoriserGroupe(cle, ouvert) {
        const fermes = new Set(lireCookie('admin_menu_groupes').split('-').filter(Boolean));
        ouvert ? fermes.delete(cle) : fermes.add(cle);
        ecrireCookie('admin_menu_groupes', [...fermes].join('-'));
    },
}));

/**
 * Lien du menu : l'état actif suit le clic tout de suite, sans attendre le chargement de la page.
 */
function suivreMenuActif() {
    const menu = document.getElementById('menu-admin');
    menu?.addEventListener('click', (evenement) => {
        const lien = evenement.target.closest('a[data-lien-menu]');
        if (!lien || evenement.ctrlKey || evenement.metaKey || evenement.shiftKey || evenement.button !== 0) {
            return;
        }
        menu.querySelectorAll('a[data-lien-menu][aria-current]').forEach((autre) => autre.removeAttribute('aria-current'));
        lien.setAttribute('aria-current', 'page');
    });
}

/**
 * Menu latéral : le lien actif est ramené au milieu de la zone défilante au chargement.
 */
function centrerLienActif() {
    const zone = document.querySelector('#menu-admin [data-menu-defilant]');
    const actif = zone?.querySelector('a[aria-current="page"]');
    if (!zone || !actif) {
        return;
    }
    const haut = actif.getBoundingClientRect().top - zone.getBoundingClientRect().top + zone.scrollTop;
    zone.scrollTop = Math.max(0, haut - zone.clientHeight / 2 + actif.offsetHeight / 2);
}

/**
 * Menus « ⋯ » des tableaux (x-admin.menu-actions, API popover) : placés sous leur bouton
 * (ou au-dessus s'il n'y a pas la place), fermés après un choix ou au défilement.
 * Délégués au document : les lignes DataTables sont recréées à chaque rechargement.
 */
function demarrerMenusActions() {
    const MARGE = 8;

    const placer = (menu) => {
        const bouton = document.querySelector(`[popovertarget="${menu.id}"]`);
        if (!bouton) {
            return;
        }
        const zone = bouton.getBoundingClientRect();
        const largeur = menu.offsetWidth || 224;
        const hauteur = menu.offsetHeight || 0;
        const gauche = Math.min(Math.max(MARGE, zone.right - largeur), window.innerWidth - largeur - MARGE);
        const enDessous = zone.bottom + 4 + hauteur <= window.innerHeight - MARGE || zone.top < hauteur + MARGE;
        menu.style.left = `${gauche}px`;
        menu.style.top = `${enDessous ? zone.bottom + 4 : zone.top - hauteur - 4}px`;
        bouton.setAttribute('aria-expanded', 'true');
    };

    // « toggle » ne remonte pas : écoute en phase de capture.
    document.addEventListener('toggle', (evenement) => {
        const menu = evenement.target;
        if (!(menu instanceof HTMLElement) || !menu.classList.contains('menu-actions')) {
            return;
        }
        if (evenement.newState === 'open') {
            placer(menu);
            menu.querySelector('a[href], button:not([disabled]), input')?.focus();
        } else {
            document.querySelector(`[popovertarget="${menu.id}"]`)?.setAttribute('aria-expanded', 'false');
        }
    }, true);

    // Un choix ferme le menu (sinon il resterait au-dessus de la confirmation SweetAlert2).
    document.addEventListener('click', (evenement) => {
        const choix = evenement.target.closest('.menu-actions a, .menu-actions button');
        choix?.closest('.menu-actions')?.hidePopover?.();
    });

    const toutFermer = () => document.querySelectorAll('.menu-actions:popover-open').forEach((menu) => menu.hidePopover());
    window.addEventListener('resize', toutFermer);
    document.addEventListener('scroll', (evenement) => {
        if (!(evenement.target instanceof Element && evenement.target.closest('.menu-actions'))) {
            toutFermer();
        }
    }, true);
}

enregistrerComposants(Alpine);
window.Alpine = Alpine;
Alpine.start();

centrerLienActif();
suivreMenuActif();
demarrerMenusActions();
demarrerListes();
demarrerConfirmations();
activerSpinners(); // après les confirmations : un envoi annulé n'affiche pas de spinner
demarrerSelections();
afficherMessages();
