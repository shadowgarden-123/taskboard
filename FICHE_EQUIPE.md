# Fiches mémo — 7 membres (à imprimer / partager)

> Chaque membre garde sa fiche pendant la soutenance. 30 s pour expliquer son module + 1 modification live.

---

## 🎯 M1 — Architecture, BDD, Helpers, API Search
**Fichiers** : `config/db.php`, `includes/auth.php`, `includes/functions.php`, `taskboard.sql`, `api/search_tasks.php`
**Points clés à connaître** :
- Schéma 3 tables, FK `ON DELETE CASCADE` / `SET NULL`, index `user_id` + `category_id`
- `normaliser_filtres()` → valide/q/cat/prio/statut/tri, `get_tasks()` → `LEFT JOIN categories` + `WHERE user_id = ?`
- `get_stats()` : 1 requête, 4 compteurs (total, terminées, à faire, en retard)
- `e()`, `date_relative()`, `date_valide()`, `flash()`, `json_reponse()`, `etat_vide()`
- API `search_tasks.php` : session → filtres → JSON `{tasks, stats, count, filters}`
**Modif live suggérée** : changer `--brand` dans `style.css` → recharger → couleur change partout
**Question piège** : « Comment garantissez-vous qu'un user ne voit pas les tâches d'un autre ? » → clause `WHERE user_id = ?` dans **chaque** requête (helpers + API + pages)

---

## 🔐 M2 — Authentification
**Fichiers** : `login.php`, `register.php`, `logout.php`, `includes/header.php` (partie session)
**Points clés à connaître** :
- `password_hash(PASSWORD_DEFAULT)` à l'inscription, `password_verify()` à la connexion
- `session_regenerate_id(true)` après login (évite fixation)
- Validation : email `filter_var`, mdp ≥ 6, confirmation, email unique en BDD
- `flash()` pour messages succès/erreur, affichés dans `header.php` via toast
- `logout.php` : `session_destroy()` + `header('Location: login.php')`
- `auth.php` : 3 lignes, appelé en tout début de chaque page protégée
**Modif live suggérée** : passer `PASSWORD_DEFAULT` → `PASSWORD_ARGON2ID` dans `register.php` (si PHP 7.2+)
**Question piège** : « Pourquoi `session_regenerate_id(true)` ? » → empêche l'attaque de fixation de session

---

## ✅ M3 — CRUD Tâches + API Toggle/Delete
**Fichiers** : `index.php` (rendu initial + `render_task()`), `add_task.php`, `edit_task.php`, `api/toggle_task.php`, `api/delete_task.php`
**Points clés à connaître** :
- `render_task()` dans `index.php` : génère le `<li>` complet (checkbox, méta, actions) — **même HTML** que le JS
- `add_task.php` : validation titre/date/priorité/catégorie (appartient à l'user), `INSERT` préparé
- `edit_task.php` : pré-remplissage, même validation, `UPDATE` préparé + `user_id` dans WHERE
- `toggle_task.php` : `UPDATE ... SET statut = IF(statut='a_faire','terminee','a_faire') WHERE id=? AND user_id=?` + renvoie nouveau statut + stats
- `delete_task.php` : `DELETE WHERE id=? AND user_id=?` + `rowCount()` pour 404
- Toutes les API : session check → 401, méthode POST → 405, validation ID → 422
**Modif live suggérée** : dans `api/toggle_task.php`, ajouter `error_log($id)` pour tracer
**Question piège** : « Pourquoi `user_id` dans le WHERE du DELETE ? » → empêche de supprimer la tâche d'un autre (même si on devine l'ID)

---

## ⚡ M4 — JavaScript (app.js)
**Fichiers** : `public/js/app.js` (entier)
**Points clés à connaître** :
- **Thème** : `localStorage.theme` + `data-theme` sur `<html>`, `theme-ready` pour transitions
- **Header** : `IntersectionObserver` sur `#header-sentinel` → classe `.is-scrolled`
- **Stats** : `requestAnimationFrame` + easing cubic (`1-(1-t)^3`), respect `prefers-reduced-motion`
- **Toasts** : `afficherToast()` → pile, pause au survol/focus, action « Annuler », auto-expiration
- **Filtres/Recherche** : `AbortController` annule requête précédente, `fetch` → JSON → `rendreListe()`
- **Rendu liste** : `carteTache()` construit 100% DOM natif (`document.createElement`), `replaceChildren()`
- **Toggle** : UI optimiste (`appliquerEtat()`), `fetch` → si erreur → rollback + toast erreur
- **Delete** : `.is-removing` (CSS `max-height:0`), toast 5s « Annuler » → si expire → `fetch delete` → `item.remove()`
- **Chips** : `majChips()` reconstruit depuis `FormData` du formulaire
**Modif live suggérée** : changer `duration: 5000` → `3000` dans l'appel `afficherToast` du delete
**Question piège** : « Pourquoi `AbortController` et pas `setTimeout` (debounce) ? » → sujet l'interdit ; `AbortController` annule vraiment la requête réseau

---

## 🎨 M5 — CSS Design System
**Fichiers** : `public/css/style.css` (entier, ~36 Ko)
**Points clés à connaître** :
- **Tokens** (lignes 24-128) : `--bg`, `--surface`, `--text*`, `--accent`, `--brand`, `--border*`, `--success`, `--danger`, `--space-*`, `--radius-*`, `--dur-*`, `--ease-*`, `--font-*`, `--header-h` — **claire** (`:root`) + **sombre** (`html[data-theme='dark']`)
- **@layer** : `reset` → `tokens` → `base` → `layout` → `components` → `pages` (cascade maîtrisée)
- **Dark mode** : un seul mécanisme (`data-theme`), pas de `.dark`/`body.dark-mode`
- **Composants tâche** : grille `44px minmax(0,1fr) auto`, barre priorité `::before`, late `::after` pulse
- **Checkbox** : native masquée (opacity 0), `.check__box` stylée, `stroke-dashoffset` pour animation coche
- **Responsive** : breakpoints 899 / 720 / 560 / 520 px, `min-height:44px` sur tous les interactifs
- **Animations** : `shake` (erreur champ), `pulse-late` (retard), transitions `transform/opacity/color/border`
- **Accessibilité** : `@media (prefers-reduced-motion)` → durées 0.01ms, `focus-visible`, `sr-only`, contraste AA
**Modif live suggérée** : changer `--dur-base: 200ms` → `500ms` → toutes les transitions ralentissent
**Question piège** : « Pourquoi `@layer` ? » → évite les guerres de spécificité, ordre explicite : reset < tokens < base < layout < components < pages

---

## ♿ M6 — Accessibilité & Tests
**Preuves à montrer** :
- **Lighthouse** : 100/100 Accessibilité sur `index.php` (clair/sombre), `login.php`, `register.php`, `add_task.php`, `edit_task.php`
- **Contraste** : `--text-3` ajusté (#6B6359 clair / #948C80 sombre) → ratio ≥ 4.5:1 sur fond carte
- **Cibles** : tous inputs/boutons/liens ≥ 44×44 px (vérifié à 320 px et 375 px)
- **Clavier** : `Tab` → focus visible everywhere, `Skip-link` fonctionnel, pas de piège
- **Lecteurs d'écran** : `aria-live="polite"` sur compteur, `sr-only` pour libellés stats, `aria-label` sur actions, `role="group"` sur chips
- **États vides** : 2 distincts (aucune tâche / aucun résultat filtre) avec illustration SVG + texte + action
- **Reduced motion** : `@media (prefers-reduced-motion: reduce)` désactive **toutes** animations/transitions
- **Responsive** : pas de scroll horizontal à 320 px, grille 1 colonne, header compact
**Modif live suggérée** : dans DevTools, activer « Emulate prefers-reduced-motion » → animations disparues
**Question piège** : « Comment testez-vous le contraste ? » → Lighthouse + calcul manuel (ratio 4.5:1 pour texte normal, 3:1 pour large)

---

## 📦 M7 — Catégories, Détails UI, Déploiement, README
**Fichiers** : `index.php` (section catégories POST), `edit_task.php` (select catégorie `aria-invalid`), `README.md`, `taskboard.sql`
**Points clés à connaître** :
- **Catégories** : `add_category` / `delete_category` en POST classique (pas AJAX), `flash()` succès/erreur, ancre `#categories`
- **Suppression catégorie** : `DELETE WHERE id=? AND user_id=?` → tâches concernées `category_id=NULL` (FK `SET NULL`)
- **Validation catégorie** : `edit_task.php` vérifie que `category_id` appartient à l'user (`aria-invalid` + `aria-describedby` si erreur)
- **README** : installation, comptes test, structure, choix implémentation (toast vs confirm), tests rapides (13 cas), répartition
- **SQL** : `CREATE DATABASE`, 3 tables, FK, index, 2 users (hash bcrypt `test123`), 6 cats, 10 tâches (dont 2 en retard, 2 terminées)
- **Déploiement** : copier dans `htdocs/`, importer SQL, vérifier `config/db.php`, Apache+MySQL démarrés
**Modif live suggérée** : dans `README.md`, ajouter une ligne « Testé sur PHP 8.1 / MariaDB 10.4 / Apache 2.4 »
**Question piège** : « Que se passe-t-il aux tâches quand on supprime leur catégorie ? » → `ON DELETE SET NULL` → `category_id` devient NULL, tâches conservées

---

## ⚡ Rappels communs (tous les membres)

| Situation | Réponse courte |
|-----------|----------------|
| « C'est qui qui a fait ça ? » | « C'est **mon** module, je peux l'expliquer. » |
| « Pourquoi pas X ? » | « Hors périmètre L2 (sujet). Pour l'ajouter : [1 phrase]. » |
| « Y a-t-il des failles ? » | « Requêtes préparées partout, `htmlspecialchars` à l'affichage, `user_id` sur chaque requête, cookies HttpOnly+SameSite. » |
| « Comment débugger ? » | « Onglet Réseau → requêtes API → JSON. `error_log()` côté PHP si besoin. » |
| « C'est vous qui avez codé le CSS/JS ? » | « Oui, sans framework ni lib. On a utilisé les APIs natives (`fetch`, `IntersectionObserver`, variables CSS, `@layer`). » |

---

## 🕐 Timeline soirée (suggéré)

| Heure | Action |
|-------|--------|
| 20:00 | Import SQL sur machine de démo, test complet (13 cas README) |
| 20:30 | Chacun relit **son** module, essaie 1 modif live |
| 21:00 | Remplir tableau « Répartition » dans `README.md` + `PITCH.md` |
| 21:30 | Répétition à 2 (chronométrée 12 min) |
| 22:00 | Captures d'écran / vidéo OBS (plan B) |
| 22:30 | Repos — confiance, vous connaissez votre code |

---

**Bonne chance !** 🚀  
*7 personnes qui maîtrisent leur bout = équipe solide.*