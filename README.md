# TaskBoard

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL/MariaDB-10.4+-4479A1?logo=mysql&logoColor=white)](https://mariadb.org)
[![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-ES6+-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/fr/docs/Web/JavaScript)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Termin%C3%A9-brightgreen)]()

Gestionnaire de tâches personnelles en **PHP / MySQL / JavaScript vanilla**.
Chaque utilisateur gère uniquement ses propres tâches, classées par catégories, avec filtres, recherche instantanée et mode sombre.

Projet réalisé dans le cadre de la **L2 MIAGE — Web 2**.

**Dépôt** : https://github.com/shadowgarden-123/taskboard

---

## Prérequis

- PHP 8.0 ou supérieur (testé avec PHP 8.1)
- MySQL / MariaDB (XAMPP, WAMP ou MAMP)
- Apache
- Un navigateur récent

Aucune librairie JS, aucun framework PHP, aucun gestionnaire de dépendances.

---

## Installation

### Via Git (recommandé)
```bash
cd C:\xampp\htdocs  # ou /var/www/html sur Linux/Mac
git clone https://github.com/shadowgarden-123/taskboard.git
cd taskboard
cp config/db.php.example config/db.php  # puis éditez config/db.php si besoin
```

### Via ZIP
1. Télécharger le ZIP depuis GitHub → Extraire dans `htdocs/` (XAMPP) ou `www/` (WAMP).
2. Renommer le dossier en `taskboard` si nécessaire.
3. Copier `config/db.php.example` → `config/db.php` et adapter les identifiants.

### Suite commune
1. Démarrer **Apache** et **MySQL** (XAMPP / WAMP / MAMP).
2. Importer `taskboard.sql` dans phpMyAdmin, ou en ligne de commande :
   ```bash
   mysql -u root < taskboard.sql
   ```
3. Vérifier les identifiants MySQL dans `config/db.php` :
   ```php
   $host = 'localhost';
   $dbname = 'taskboard';
   $user = 'root';
   $pass = '';  // votre mot de passe root MySQL
   ```
4. Ouvrir `http://localhost/taskboard/`.

---

## Comptes de test

| Email | Mot de passe |
| --- | --- |
| `etudiant1@test.com` | `test123` |
| `etudiant2@test.com` | `test123` |

Chaque compte possède trois catégories (Cours, Perso, Projet) et cinq tâches de démonstration, dont une en retard.

---

## Fonctionnalités

### Back-end

- Inscription, connexion, déconnexion avec sessions PHP.
- Mots de passe hachés avec `password_hash()` et vérifiés avec `password_verify()`.
- CRUD complet des tâches : titre, description, date limite, priorité, statut, catégorie.
- Catégories personnalisées par utilisateur (ajout / suppression en POST classique).
- Chaque requête est filtrée par `user_id` : un utilisateur ne voit et ne modifie que ses tâches.
- Requêtes préparées PDO partout, affichage échappé avec `htmlspecialchars()`.
- Validation serveur sur tous les formulaires.

### JavaScript (fetch + DOM)

- Cocher / décocher une tâche sans rechargement, avec mise à jour optimiste et retour arrière si l'appel échoue.
- Suppression animée avec toast « Annuler » : l'appel `DELETE` n'est envoyé qu'à l'expiration du toast.
- Filtres par catégorie, priorité et statut, tri par échéance / priorité / date de création.
- Recherche en direct à chaque frappe (sans debounce, conformément au sujet).
- Reconstruction de la liste à partir du JSON renvoyé par `api/search_tasks.php`.
- Mode sombre persistant (`localStorage`), sans flash au rechargement.
- Compteurs de statistiques animés (désactivés si `prefers-reduced-motion`).

### Design

- Direction artistique « papier chaud, encre, terracotta ».
- Fraunces (titres) + IBM Plex Sans (texte) + IBM Plex Mono (chiffres et micro-labels).
- Tokens CSS pour toutes les couleurs, durées, rayons et espacements.
- Mode sombre par attribut `data-theme` sur `<html>` (un seul mécanisme).
- Carte de tâche avec barre de priorité, ombre décalée au survol, coche animée.
- États vides distincts : aucune tâche / aucun résultat de filtre.
- Focus visible, contrastes AA, cibles tactiles de 44 px, `prefers-reduced-motion`.

---

## Structure

```
taskboard/
├── config/
│   └── db.php                 Connexion PDO
├── includes/
│   ├── auth.php               Garde de session
│   ├── functions.php          Utilitaires (échappement, dates, filtres, stats)
│   ├── header.php             En-tête, thème, navigation
│   └── footer.php             Pied de page, toasts, script
├── api/
│   ├── toggle_task.php        Bascule le statut d'une tâche (JSON)
│   ├── delete_task.php        Supprime une tâche (JSON)
│   └── search_tasks.php       Filtres + recherche (JSON)
├── public/
│   ├── css/style.css          Design system
│   └── js/app.js              Interactions
├── index.php                  Liste, filtres, statistiques, catégories
├── login.php
├── register.php
├── add_task.php
├── edit_task.php
├── logout.php
├── README.md
└── taskboard.sql
```

---

## Stack technique

| Couche | Technologie | Version |
|--------|-------------|---------|
| **Backend** | PHP | 8.1+ |
| **Base de données** | MySQL / MariaDB | 10.4+ |
| **Accès BDD** | PDO | Natif |
| **Frontend** | HTML5 / CSS3 / JS ES6+ | Natif (vanilla) |
| **Serveur** | Apache | 2.4+ (via XAMPP/WAMP/MAMP) |
| **Polices** | Google Fonts | Fraunces, IBM Plex Sans, IBM Plex Mono |
| **Architecture** | MVC-like (sans framework) | Pages + Includes + API |

---

## Captures d'écran

> *Ajoutez vos captures ici (dossier `docs/screenshots/`)*

| Page | Aperçu |
|------|--------|
| Connexion | `![Login](docs/screenshots/login.png)` |
| Liste tâches (clair) | `![Tasks Light](docs/screenshots/tasks-light.png)` |
| Liste tâches (sombre) | `![Tasks Dark](docs/screenshots/tasks-dark.png)` |
| Ajout tâche | `![Add Task](docs/screenshots/add-task.png)` |
| Responsive 375px | `![Mobile](docs/screenshots/mobile.png)` |

---

## Choix d'implémentation

- **Suppression avec « Annuler »** : la direction artistique v2 remplace le `confirm()` bloquant par un toast de 5 secondes. La suppression n'est réellement envoyée qu'à la fin du délai ; « Annuler » restaure la carte. Si le professeur exige explicitement `confirm()`, il suffit de rappeler `window.confirm()` avant `item.classList.add('is-removing')` dans `public/js/app.js`.
- **Catégories** : ajout et suppression en POST classique (pas d'AJAX), comme demandé.
- **Pas de CSRF, pas de transactions, pas de debounce** : conformément au périmètre L2 indiqué dans le sujet.
- **Recherche** : un appel `fetch` à chaque frappe, avec `AbortController` pour annuler la requête précédente. Ce n'est pas un debounce.
- **Accessibilité** : le compteur de statistiques est `aria-hidden` et doublé d'un texte `.sr-only` ; le retard est écrit, pas seulement coloré.

---

## Tests rapides

1. Inscription avec un email déjà utilisé → message d'erreur.
2. Connexion avec un mauvais mot de passe → message d'erreur.
3. Accès à `index.php` sans session → redirection vers `login.php`.
4. Connexion avec `etudiant1@test.com`, puis `etudiant2@test.com` → listes différentes.
5. `edit_task.php?id=1` avec le compte 2 → « Tâche introuvable ou accès refusé ».
6. Ajouter une tâche sans titre → validation serveur.
7. Cocher une tâche → statut mis à jour sans rechargement.
8. Couper le réseau puis cocher → la case revient et un toast d'erreur apparaît.
9. Supprimer une tâche → animation, toast « Annuler », restauration si on annule.
10. Rechercher « rapport » → résultats filtrés.
11. Tâche en retard → texte « En retard de N j » et barre rouge.
12. Recharger après avoir activé le mode sombre → thème conservé, sans flash.
13. Réduire la fenêtre à 375 px (et 320 px) → une seule colonne, pas de défilement horizontal.
14. Audit Lighthouse : accessibilité 100/100 sur `index.php` (clair et sombre), `login.php`, `register.php`, `add_task.php` et `edit_task.php`.

---

## Répartition du travail

À compléter par le groupe :

- Membre 1 :
- Membre 2 :
- Membre 3 :
- Membre 4 :
- Membre 5 :
- Membre 6 :

---

Projet L2 MIAGE — 2026.
