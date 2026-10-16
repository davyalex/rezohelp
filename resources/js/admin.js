// Back-office uniquement : jQuery, DataTables (mode serveur), Select2, SweetAlert2.
import jQuery from 'jquery';
import DataTable from 'datatables.net-dt';
import select2 from 'select2';
import Swal from 'sweetalert2';
import Alpine from 'alpinejs';

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

window.Alpine = Alpine;
Alpine.start();
