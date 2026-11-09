# TaskBoard — Support de présentation (L2 MIAGE)

> **Durée visée : 12-15 min**  
> **Équipe : 7 membres**  
> **Stack : PHP 8 + MySQL (PDO) / HTML / CSS / JS vanilla — sans framework, sans librairie**

---

## 1. Introduction (30 s)

| Élément | Contenu |
|---------|---------|
| **Problème** | Gérer ses tâches personnelles : échéances, priorités, catégories, sans fuite de données entre utilisateurs. |
| **Objectif** | Application web complète, accessible, responsive, déployable sur Apache/MySQL standard (XAMPP/WAMP/MAMP). |
| **Contraintes L2** | Pas de framework PHP, pas de librairie JS, pas de CSRF/transactions/Docker/CI, pas de debounce sur la recherche. |

**Accroche** : « On a voulu quelque chose qui *marche* vraiment — pas un prototype. Chaque ligne sert une fonctionnalité demandée. »

---

## 2. Architecture & Stack technique (2 min)

### 2.1 Stack
- **Backend** : PHP 8.1, PDO/MySQL (MariaDB 10.4), sessions natives, `password_hash`/`verify` (bcrypt).
- **Frontend** : HTML5 sémantique, CSS (variables, `@layer`, pas de préprocesseur), JS ES6+ (`fetch`, `AbortController`, `IntersectionObserver`).
- **Serveur** : Apache (XAMPP) — port 80/443 standard, pas de Node, pas de Docker.

### 2.2 Structure du projet
```
taskboard/
├── config/db.php            # Connexion PDO unique
├── includes/
│   ├── auth.php             # Garde de session (3 lignes)
│   ├── functions.php        # Helpers partagés (échappement, dates, filtres, stats, états vides)
│   ├── header.php / footer.php
├── api/
│   ├── toggle_task.php      # Bascule statut (JSON)
│   ├── delete_task.php      # Suppression (JSON)
│   ├── search_tasks.php     # Filtres + recherche (JSON)
├── public/
│   ├── css/style.css        # Design system complet (~36 Ko)
│   └── js/app.js            # Interactions (~28 Ko)
├── index.php                # Liste, stats, filtres, catégories
├── login.php / register.php / logout.php
├── add_task.php / edit_task.php
└── taskboard.sql            # Schéma + données de démo
```

### 2.3 Base de données (3 tables)
```sql
users (id, nom, email UNIQUE, mot_de_passe, date_inscription)
categories (id, user_id FK, nom)
tasks (id, user_id FK, category_id FK NULL, titre, description, date_limite, priorite ENUM, statut ENUM, date_creation)
-- Index : idx_tasks_user(user_id), idx_tasks_categorie(category_id)
-- Contraintes : ON DELETE CASCADE (user→tasks/categories), SET NULL (category→tasks)
```
**Isolation** : chaque requête porte `WHERE user_id = ?` — impossible de voir/modifier les données d'un autre.

### 2.4 Flux de données (simplifié)
```
index.php (GET) → normaliser_filtres() → get_tasks() + get_stats() → HTML initial
        ↓
JS (fetch) → api/search_tasks.php → JSON → rendeListe() → DOM mis à jour
        ↓
Cocher → api/toggle_task.php → JSON → majStats() + appliquerEtat()
        ↓
Supprimer → toast 5s → api/delete_task.php → item.remove() + majStats()
```

---

## 3. Fonctionnalités démontrées (6 min — live)

> **Préparer** : deux fenêtres (normale + navigation privée), zoom 125 %, onglet Réseau ouvert.

| # | Action | Ce qu'on montre | Point technique à citer |
|---|--------|-----------------|-------------------------|
| 1 | Connexion `etudiant1@test.com` / `test123` | Redirection vers `index.php`, header affiche l'initiale | `session_regenerate_id(true)`, `password_verify` |
| 2 | Ouvrir fenêtre privée → `etudiant2@test.com` | Deux listes **différentes** (isolation `user_id`) | Clé étrangère + clause `WHERE user_id = ?` partout |
| 3 | Ajouter une tâche (titre + priorité haute + date demain) | Apparaît en haut, stats « À faire » incrémenté | `INSERT` préparé, `flash()` succès |
| 4 | Cocher la case d'une tâche | Case cochée, stats « Terminées » +1, « À faire » -1, **sans rechargement** | `fetch POST toggle_task.php`, UI optimiste + rollback si échec |
| 5 | Rechercher « rapport » | Résultats filtrés à chaque frappe | `input` → `fetch` immédiat, `AbortController` annule requête précédente |
| 6 | Filtrer « Priorité haute » + « À faire » | Chips actifs, liste mise à jour | `select` change → `fetch`, chips cliquables pour retirer |
| 7 | Trier « Par priorité » | Hautes en premier, puis moyennes, puis basses | `ORDER BY FIELD(priorite, 'haute','moyenne','basse')` |
| 8 | Supprimer une tâche | Animation collapse, toast « Annuler » 5 s, restauration si clic | `is-removing` (CSS `max-height:0`), `setTimeout` + `fetch` différé |
| 9 | Mode sombre (icône lune) | Thème bascule, persistant au rechargement | `localStorage.theme`, `data-theme` sur `<html>`, pas de flash |
| 10 | Réduire fenêtre à ~375 px | Une colonne, pas de scroll horizontal, cibles ≥ 44 px | Media queries, `min-height:44px` sur inputs/boutons |
| 11 | Naviguer au clavier (Tab) | Focus visible sur tous les éléments interactifs | `:focus-visible`, `skip-link`, `aria-label` |
| 12 | Déconnexion → tentative `index.php` | Redirection vers `login.php` | `auth.php` garde en 3 lignes |

---

## 4. Points forts techniques (2 min)

| Domaine | Ce qu'on a fait | Pourquoi c'est propre |
|---------|-----------------|----------------------|
| **Sécurité** | `password_hash` (bcrypt), requêtes préparées partout, `htmlspecialchars` à l'affichage, `user_id` sur chaque requête, cookies `HttpOnly`+`SameSite=Lax` (à activer dans `php.ini`) | Aucune injection SQL/XSS possible, isolation totale entre users |
| **Validation** | Côté serveur sur **tous** les formulaires (titre requis, email valide, mdp ≥ 6, confirmation, date réelle, priorité ENUM, catégorie appartient à l'user) | Même avec JS désactivé, l'intégrité tient |
| **API REST-like** | 3 endpoints JSON (`toggle`, `delete`, `search`), codes HTTP précis (200, 401, 404, 422, 500), `AbortController` côté client | Séparation nette, testable au `curl` |
| **CSS Design System** | Variables pour **tout** (couleurs, espaces, rayons, durées, typos), `@layer` (reset/tokens/base/layout/components/pages), pas de couleur durcie hors tokens, dark mode par attribut | Maintenance facile, cohérence garantie, pas de `!important` sauvage |
| **Accessibilité** | Contraste AA (vérifié Lighthouse 100/100), cibles 44 px, `prefers-reduced-motion` désactive toutes les animations, `aria-live` sur compteurs, `sr-only` pour lecteurs d'écran, focus visible, `skip-link` | Conforme RGAA / WCAG 2.1 AA de base |
| **Responsive** | Breakpoints 899 / 720 / 560 / 520 px, grille fluide, pas de scroll horizontal à 320 px | Mobile-first réel, pas de « responsive en bonus » |
| **JS sans framework** | `fetch` + `AbortController`, `IntersectionObserver` (header), UI optimiste avec rollback, toasts avec pause au survol/focus, rendu liste 100% DOM natif | Zéro dépendance, bundle = 0 Ko, perf max |

---

## 5. Choix assumés & limites connues (1 min)

| Choix | Justification |
|-------|---------------|
| **Toast « Annuler » au lieu de `confirm()`** | Direction artistique v2 : non-bloquant, accessible, annulable. `confirm()` gardé pour suppression catégorie (action rare, destructive, POST classique). |
| **Pas de CSRF / transactions** | Hors périmètre L2 (sujet explicite). Prêt à être ajouté : token en session + `hash_equals` au POST. |
| **Pas de debounce sur recherche** | Interdit par le sujet. On annule la requête précédente via `AbortController` à la place. |
| **Catégories en POST classique** | Demandé ainsi. AJAX possible mais hors scope. |
| **Durées JS en dur (5000, 800, 320 ms)** | Fonctionnel. Pourraient être lues dans `getComputedStyle(...).getPropertyValue('--dur-xxx')` si temps. |
| **`session_start()` sans flags dans le code** | Flags posés via `php.ini` (`session.cookie_httponly=1`, `session.cookie_samesite=Lax`) — plus robuste. |

---

## 6. Répartition du travail (7 membres) — *à compléter ce soir*

| Membre | Modules principaux | Ce qu'il/elle doit savoir expliquer/démo en 30 s |
|--------|-------------------|--------------------------------------------------|
| **M1 (toi)** | Architecture globale, `config/db.php`, `includes/auth.php`, `includes/functions.php` (helpers), `taskboard.sql`, API (`search_tasks.php`) | Schéma BDD, isolation `user_id`, flux `fetch` → JSON → DOM |
| **M2** | Auth : `login.php`, `register.php`, `logout.php`, `password_hash/verify`, sessions, validation serveur | Connexion, inscription, déconnexion, garde `auth.php` |
| **M3** | Tâches CRUD : `index.php` (rendu initial), `add_task.php`, `edit_task.php`, `api/toggle_task.php`, `api/delete_task.php` | Ajout/modif/suppression, toggle AJAX, UI optimiste |
| **M4** | JavaScript : `public/js/app.js` (thème, header, stats, toasts, filtres, recherche, rendu liste, suppression toast) | `AbortController`, `IntersectionObserver`, toast annulable, UI optimiste |
| **M5** | CSS Design System : `public/css/style.css` (tokens, `@layer`, dark mode, composants tâche, checkbox, badges, responsive, animations) | Variables CSS, `@layer`, `prefers-reduced-motion`, grille tâche |
| **M6** | Accessibilité & tests : Lighthouse, contraste, cibles 44 px, navigation clavier, `skip-link`, `aria-*`, états vides, responsive 320/375 px | Démontrer score 100/100, focus visible, lecteurs d'écran |
| **M7** | Catégories, UI détails : `index.php` (section catégories POST), `edit_task.php` (select catégorie `aria-invalid`), README, déploiement, import SQL | Ajout/suppr catégories, validation catégorie user, installation |

> **Règle** : chaque membre doit pouvoir **modifier une ligne** dans son module et dire pourquoi elle est là.

---

## 7. Questions probables du prof — réponses types (2 min)

| Question | Réponse courte |
|----------|----------------|
| **Pas de CSRF ?** | « Hors périmètre L2. Pour l'ajouter : token stocké en session, vérifié au POST via `hash_equals()`. » |
| **Pourquoi pas de transactions ?** | « Une opération = une requête ici. Pas d'écriture multiple à atomiciser. » |
| **Pas de debounce ?** | « Interdit par le sujet. On utilise `AbortController` pour annuler la requête précédente à chaque frappe. » |
| **Pourquoi `@layer` et autant de variables CSS ?** | « Exigé par la DA v2. Centralise couleurs/durées/rayons, évite les conflits de cascade, facilite le dark mode. » |
| **Pourquoi la suppression attend 5 s ?** | « Choix UX : toast avec annulation au lieu du `confirm()` bloquant. Accessible, non-modal. » |
| **Pourquoi pas Laravel / React ?** | « Interdit par le sujet : PHP natif + JS vanilla pour montrer la maîtrise des bases. » |
| **Comment gérez-vous la concurrence ?** | « Pas de verrouillage optimiste (hors scope). En production : `version` ou `updated_at` + `WHERE version = ?`. » |
| **Les mots de passe sont-ils bien hachés ?** | « `password_hash(PASSWORD_DEFAULT)` → bcrypt (coût 10). `password_verify` à la connexion. Jamais en clair. » |
| **Comment testez-vous l'isolation user ?** | « Deux fenêtres (normale + privée), deux comptes, on vérifie que l'user 1 ne voit pas les tâches de l'user 2, et que `edit_task.php?id=X` renvoie 404 si X appartient à l'autre. » |

---

## 8. Checklist finale (ce soir)

- [ ] Importer `taskboard.sql` sur la machine de démo (base vide → import → 2 users, 6 cats, 10 tâches).
- [ ] Vérifier `config/db.php` (identifiants locaux).
- [ ] Lancer Apache + MySQL (XAMPP), tester `http://localhost/taskboard/`.
- [ ] Remplir le tableau « Répartition du travail » (section 6) avec les vrais noms.
- [ ] Chaque membre : relire **son** module, pouvoir changer une valeur (ex: couleur `--brand`, durée toast, libellé priorité) et l'expliquer.
- [ ] Deux fenêtres prêtes (normale + privée), zoom 125 %, onglet Réseau ouvert.
- [ ] Avoir un `curl` de test prêt : `curl -X POST -d "id=1" http://localhost/taskboard/api/toggle_task.php` (avec cookie de session).

---

## 9. Plan B si démo live plante

1. Captures d'écran annotées dans le repo (dossier `docs/screenshots/`).
2. Vidéo 30 s (OBS) du parcours complet — à tourner ce soir si temps.
3. `curl` + `jq` en ligne de commande pour prouver que l'API répond.

---

**Bonne soutenance !** 🎯  
*Le code est solide. La différence se fait à l'oral : montrez que vous **maîtrisez** ce que vous présentez.*