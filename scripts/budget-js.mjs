// Budget JavaScript du site public (CLAUDE.md : < 50 Ko) — lancé après `npm run build`, en local et en CI.
// Mesure le JS chargé par l'entrée publique (resources/js/app.js et ses imports statiques), compressé
// en gzip comme il transite sur le réseau, et vérifie qu'aucune bibliothèque du back-office n'y figure.
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { join } from 'node:path';

const DOSSIER = 'public/build';
const ENTREE_PUBLIQUE = 'resources/js/app.js';
const BUDGET_KO = Number(process.env.BUDGET_JS_KO ?? 50);
// Réservées au back-office (resources/js/admin.js) : jamais sur le site public.
const INTERDITES = ['jQuery', 'DataTable', 'select2', 'sweetalert2', 'Swal.fire'];

const manifeste = JSON.parse(readFileSync(join(DOSSIER, 'manifest.json'), 'utf8'));

function fichiers(cle, vus = new Set()) {
    if (vus.has(cle) || !manifeste[cle]) return vus;
    vus.add(cle);
    for (const importe of manifeste[cle].imports ?? []) fichiers(importe, vus);
    return vus;
}

if (!manifeste[ENTREE_PUBLIQUE]) {
    console.error(`Entrée ${ENTREE_PUBLIQUE} absente du manifeste : lancez d'abord npm run build.`);
    process.exit(1);
}

let brut = 0;
let compresse = 0;
const erreurs = [];

for (const cle of fichiers(ENTREE_PUBLIQUE)) {
    const contenu = readFileSync(join(DOSSIER, manifeste[cle].file));
    const taille = gzipSync(contenu, { level: 9 }).length;
    brut += contenu.length;
    compresse += taille;
    console.log(`  ${manifeste[cle].file.padEnd(40)} ${(contenu.length / 1024).toFixed(1).padStart(6)} Ko  (${(taille / 1024).toFixed(1)} Ko gzip)`);

    for (const nom of INTERDITES) {
        if (contenu.includes(nom)) erreurs.push(`${manifeste[cle].file} contient « ${nom} », réservé au back-office.`);
    }
}

console.log(`JS public : ${(brut / 1024).toFixed(1)} Ko, ${(compresse / 1024).toFixed(1)} Ko gzip (budget ${BUDGET_KO} Ko gzip).`);

if (compresse > BUDGET_KO * 1024) erreurs.push(`Budget dépassé : ${(compresse / 1024).toFixed(1)} Ko gzip > ${BUDGET_KO} Ko.`);

if (erreurs.length > 0) {
    erreurs.forEach((erreur) => console.error(`✗ ${erreur}`));
    process.exit(1);
}
