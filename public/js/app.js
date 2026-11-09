// TaskBoard — interactions (thème, header, filtres, tâches, toasts)
(function () {
    'use strict';

    var racine = document.documentElement;
    var reduire = window.matchMedia('(prefers-reduced-motion: reduce)');
    // petits raccourcis
    var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

    // ------------------------------------------------------------
    // Thème clair / sombre
    // ------------------------------------------------------------
    var boutonTheme = $('#theme-toggle');
    var metaTheme = $('#meta-theme-color');

    function majThemeUI(theme) {
        if (!boutonTheme) return;
        boutonTheme.setAttribute('aria-label', theme === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre');
        if (metaTheme) {
            var fond = getComputedStyle(racine).getPropertyValue('--bg').trim();
            if (fond) metaTheme.setAttribute('content', fond);
        }
    }

    majThemeUI(racine.dataset.theme === 'dark' ? 'dark' : 'light');

    if (boutonTheme) {
        boutonTheme.addEventListener('click', function () {
            var suivant = racine.dataset.theme === 'dark' ? 'light' : 'dark';
            racine.dataset.theme = suivant;
            try { localStorage.setItem('theme', suivant); } catch (e) {}
            majThemeUI(suivant);
        });
    }

    // Active les transitions de couleur après le premier rendu
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            racine.classList.add('theme-ready');
        });
    });

    // ------------------------------------------------------------
    // Header : ombre au défilement via IntersectionObserver
    // ------------------------------------------------------------
    var sentinelle = $('#header-sentinel');
    var entete = $('#site-header');
    if (sentinelle && entete && 'IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            entete.classList.toggle('is-scrolled', !entries[0].isIntersecting);
        }, { threshold: 0 }).observe(sentinelle);
    }

    // ------------------------------------------------------------
    // Compteurs de statistiques
    // ------------------------------------------------------------
    function animerCompteur(el) {
        var cible = parseInt(el.dataset.count || '0', 10) || 0;
        if (reduire.matches) {
            el.textContent = String(cible);
            return;
        }
        var debut = performance.now();
        var duree = 800;
        function step(now) {
            var progress = Math.min((now - debut) / duree, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = String(Math.round(cible * eased));
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    $$('[data-count]').forEach(animerCompteur);

    // ------------------------------------------------------------
    // Toasts (durée en pause au survol et au focus)
    // ------------------------------------------------------------
    var pileToasts = $('#toast-stack');

    function fermerToast(element) {
        element.classList.remove('is-visible');
        setTimeout(function () { element.remove(); }, reduire.matches ? 0 : 200);
    }

    function armerToast(element, duree, onExpire) {
        var timer = setTimeout(function () {
            fermerToast(element);
            if (typeof onExpire === 'function') onExpire();
        }, duree);

        element.addEventListener('mouseenter', function () { clearTimeout(timer); });
        element.addEventListener('mouseleave', function () {
            timer = setTimeout(function () {
                fermerToast(element);
                if (typeof onExpire === 'function') onExpire();
            }, 500);
        });

        return {
            annuler: function () {
                clearTimeout(timer);
                fermerToast(element);
            }
        };
    }

    function afficherToast(message, options) {
        options = options || {};
        if (!pileToasts) return null;

        var toast = document.createElement('div');
        toast.className = 'toast' + (options.type === 'error' ? ' toast--error' : '');
        toast.setAttribute('role', 'status');

        var texte = document.createElement('p');
        texte.className = 'toast__msg';
        texte.textContent = message;
        toast.appendChild(texte);

        var controle = null;

        if (options.actionLabel) {
            var action = document.createElement('button');
            action.type = 'button';
            action.className = 'toast__action';
            action.textContent = options.actionLabel;
            action.addEventListener('click', function () {
                if (controle) controle.annuler();
                if (typeof options.onAction === 'function') options.onAction();
            });
            toast.appendChild(action);
        }

        pileToasts.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('is-visible'); });
        controle = armerToast(toast, options.duration || 5000, options.onExpire);
        return controle;
    }

    $$('[data-toast]').forEach(function (toast) {
        armerToast(toast, 5000);
    });

    // ------------------------------------------------------------
    // Liste des tâches : filtres, recherche, rendu
    // ------------------------------------------------------------
    var formulaire = $('#filters-form');
    var region = $('#task-region');

    if (formulaire && region) {
        var recherche = $('#search');
        var selectCategorie = $('#filtre-categorie');
        var selectPriorite = $('#filtre-priorite');
        var selectStatut = $('#filtre-statut');
        var selectTri = $('#filtre-tri');
        var compteur = $('#task-count');
        var chips = $('#active-filters');

        var controleur = null;
        var sequence = 0;
        var minuterieSortie = null;
        var minuterieEntree = null;

        function el(tag, classe, texte) {
            var noeud = document.createElement(tag);
            if (classe) noeud.className = classe;
            if (texte !== undefined) noeud.textContent = texte;
            return noeud;
        }

        function tronquer(texte, max) {
            var propre = String(texte || '').replace(/\s+/g, ' ').trim();
            var caracteres = Array.from(propre);
            return caracteres.length <= max ? propre : caracteres.slice(0, max - 1).join('') + '…';
        }

        function labelPriorite(priorite) {
            if (priorite === 'basse') return 'Basse';
            if (priorite === 'haute') return 'Haute';
            return 'Moyenne';
        }

        var rtf = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });

        function majuscule(texte) {
            return texte.charAt(0).toUpperCase() + texte.slice(1);
        }

        function dateRelative(iso, statut) {
            if (!iso) return { label: 'Sans échéance', title: '', late: false };

            var parts = iso.split('-');
            var y = parseInt(parts[0], 10), m = parseInt(parts[1], 10), d = parseInt(parts[2], 10);
            var now = new Date();
            var today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
            var target = Date.UTC(y, m - 1, d);
            var diff = Math.round((target - today) / 86400000);
            var late = statut === 'a_faire' && diff < 0;
            var label;

            if (late) label = 'En retard de ' + Math.abs(diff) + ' j';
            else if (diff === 0) label = majuscule(rtf.format(0, 'day'));
            else if (diff === 1) label = majuscule(rtf.format(1, 'day'));
            else if (diff === -1) label = majuscule(rtf.format(-1, 'day'));
            else if (diff > 1) label = 'Dans ' + diff + ' j';
            else label = 'Il y a ' + Math.abs(diff) + ' j';

            return {
                label: label,
                title: String(d).padStart(2, '0') + '/' + String(m).padStart(2, '0') + '/' + y,
                late: late
            };
        }

        function estFiltre() {
            return recherche.value.trim() !== ''
                || selectCategorie.value !== ''
                || selectPriorite.value !== ''
                || selectStatut.value !== '';
        }

        function parametres() {
            var params = new URLSearchParams();
            var donnees = new FormData(formulaire);
            donnees.forEach(function (valeur, cle) {
                var propre = String(valeur).trim();
                if (propre !== '') params.set(cle, propre);
            });
            return params;
        }

        function etatVide(filtre) {
            if (filtre) {
                return '<div class="empty">'
                    + '<p class="empty__title">Rien ne correspond à cette recherche.</p>'
                    + '<p class="empty__text">Essayez un autre mot-clé ou retirez un filtre.</p>'
                    + '<a class="btn btn--secondary" href="index.php" data-clear-filters>Effacer les filtres</a>'
                    + '</div>';
            }
            return '<div class="empty">'
                + '<svg class="empty__illus" viewBox="0 0 120 100" aria-hidden="true" focusable="false">'
                + '<path class="illus-brand" d="M22 34h56v30a16 16 0 0 1-16 16H38a16 16 0 0 1-16-16z" fill="none" stroke-width="1.5"/>'
                + '<path class="illus-brand" d="M78 40h8a10 10 0 0 1 0 20h-8" fill="none" stroke-width="1.5"/>'
                + '<path class="illus-sage" d="M34 24c0-5 4-5 4-10M48 24c0-5 4-5 4-10M62 24c0-5 4-5 4-10" fill="none" stroke-width="1.5" stroke-linecap="round"/>'
                + '<path class="illus-sage" d="M96 66l14-14 6 6-14 14-8 2z" fill="none" stroke-width="1.5" stroke-linejoin="round"/>'
                + '</svg>'
                + '<p class="empty__title">Aucune tâche pour l\'instant.</p>'
                + '<p class="empty__text">Notez ce que vous avez à faire, puis avancez une tâche à la fois.</p>'
                + '<a class="btn btn--primary" href="add_task.php">Ajouter ma première tâche</a>'
                + '</div>';
        }

        function carteTache(tache) {
            var date = dateRelative(tache.date_limite, tache.statut);
            var terminee = tache.statut === 'terminee';

            var li = el('li', 'task-item');
            var interieur = el('div', 'task-item__inner');
            var article = el('article', 'task' + (terminee ? ' is-done' : '') + (date.late ? ' task--late' : ''));
            article.dataset.id = String(tache.id);
            article.dataset.priorite = tache.priorite;
            article.dataset.statut = tache.statut;
            article.dataset.late = date.late ? '1' : '0';

            var label = el('label', 'check');
            var input = el('input', 'check__input');
            input.type = 'checkbox';
            input.checked = terminee;
            input.setAttribute('aria-label', (terminee ? 'Rouvrir' : 'Terminer') + ' : ' + tache.titre);
            var boite = el('span', 'check__box');
            boite.innerHTML = '<svg class="check__mark" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5 10.5l3.2 3.2L15 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            label.appendChild(input);
            label.appendChild(boite);

            var corps = el('div', 'task__body');
            var titre = el('h3', 'task__title', tache.titre);
            titre.title = tache.titre;
            corps.appendChild(titre);

            if (tache.description) {
                corps.appendChild(el('p', 'task__desc', tronquer(tache.description, 80)));
            }

            var meta = el('div', 'task__meta');
            meta.appendChild(el('span', 'task__cat', tache.categorie || 'Sans catégorie'));
            var separateur = el('span', 'task__sep', '·');
            separateur.setAttribute('aria-hidden', 'true');
            meta.appendChild(separateur);

            if (tache.date_limite) {
                var temps = document.createElement('time');
                temps.className = 'task__date' + (date.late ? ' task__date--late' : '');
                temps.dateTime = tache.date_limite;
                temps.title = date.title;
                if (date.late) {
                    var point = el('span', null, '●');
                    point.setAttribute('aria-hidden', 'true');
                    temps.appendChild(point);
                    temps.appendChild(document.createTextNode(' ' + date.label));
                } else {
                    temps.textContent = date.label;
                }
                meta.appendChild(temps);
            } else {
                meta.appendChild(el('span', 'task__date', 'Sans échéance'));
            }

            meta.appendChild(el('span', 'badge badge--' + tache.priorite, labelPriorite(tache.priorite)));

            var actions = el('div', 'task__actions');
            var modifier = document.createElement('a');
            modifier.className = 'icon-btn';
            modifier.href = 'edit_task.php?id=' + encodeURIComponent(tache.id);
            modifier.setAttribute('aria-label', 'Modifier : ' + tache.titre);
            modifier.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 20h4L18.5 9.5l-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m13.5 6.5 4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';

            var supprimer = el('button', 'icon-btn icon-btn--danger');
            supprimer.type = 'button';
            supprimer.dataset.delete = String(tache.id);
            supprimer.dataset.title = tache.titre;
            supprimer.setAttribute('aria-label', 'Supprimer : ' + tache.titre);
            supprimer.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

            actions.appendChild(modifier);
            actions.appendChild(supprimer);

            article.appendChild(label);
            article.appendChild(corps);
            article.appendChild(actions);
            interieur.appendChild(article);
            li.appendChild(interieur);
            return li;
        }

        function rendreListe(donnees) {
            var taches = donnees.tasks || [];
            if (!taches.length) {
                region.innerHTML = etatVide(estFiltre());
                if (compteur) compteur.textContent = '0';
                return;
            }

            var liste = document.createElement('ul');
            liste.className = 'task-list';
            taches.forEach(function (tache) { liste.appendChild(carteTache(tache)); });
            region.replaceChildren(liste);
            if (compteur) compteur.textContent = String(taches.length);
        }

        function definirStat(cle, valeur, singulier, pluriel) {
            var nombre = Number(valeur) || 0;
            var valeurEl = document.querySelector('[data-stat-value="' + cle + '"]');
            var srEl = document.querySelector('[data-stat-sr="' + cle + '"]');
            if (valeurEl) valeurEl.textContent = String(nombre);
            if (srEl) srEl.textContent = nombre + ' ' + (nombre === 1 ? singulier : pluriel);
        }

        function majStats(stats) {
            if (!stats) return;
            definirStat('terminees', stats.terminees, 'tâche terminée', 'tâches terminées');
            definirStat('a_faire', stats.a_faire, 'tâche à faire', 'tâches à faire');
            definirStat('en_retard', stats.en_retard, 'tâche en retard', 'tâches en retard');
        }

        function majChips() {
            if (!chips) return;
            var liste = [];
            var q = recherche.value.trim();

            if (q !== '') liste.push({ cle: 'q', label: 'Recherche : « ' + q + ' »' });
            if (selectCategorie.value !== '') {
                liste.push({ cle: 'categorie', label: selectCategorie.options[selectCategorie.selectedIndex].text });
            }
            if (selectPriorite.value !== '') {
                liste.push({ cle: 'priorite', label: 'Priorité ' + labelPriorite(selectPriorite.value) });
            }
            if (selectStatut.value !== '') {
                liste.push({ cle: 'statut', label: selectStatut.value === 'a_faire' ? 'À faire' : 'Terminées' });
            }

            if (!liste.length) {
                chips.hidden = true;
                chips.replaceChildren();
                return;
            }

            chips.hidden = false;
            chips.replaceChildren.apply(chips, liste.map(function (item) {
                var puce = el('span', 'chip', item.label);
                var retirer = el('button', 'chip__remove');
                retirer.type = 'button';
                retirer.dataset.filter = item.cle;
                retirer.setAttribute('aria-label', 'Retirer le filtre ' + item.label);
                retirer.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
                puce.appendChild(retirer);
                return puce;
            }));
        }

        function afficherListe(donnees, silencieux) {
            if (silencieux || reduire.matches) {
                rendreListe(donnees);
                return;
            }

            clearTimeout(minuterieSortie);
            clearTimeout(minuterieEntree);
            region.style.minHeight = region.offsetHeight + 'px';
            region.classList.add('is-swapping');

            minuterieSortie = setTimeout(function () {
                rendreListe(donnees);
                region.classList.remove('is-swapping');
                minuterieEntree = setTimeout(function () {
                    region.style.minHeight = '';
                }, 160);
            }, 120);
        }

        function rafraichir(silencieux) {
            var maSequence = ++sequence;

            if (controleur) controleur.abort();
            controleur = new AbortController();

            fetch('api/search_tasks.php?' + parametres().toString(), {
                headers: { 'Accept': 'application/json' },
                signal: controleur.signal,
                credentials: 'same-origin'
            })
                .then(function (reponse) {
                    if (!reponse.ok) throw new Error('HTTP ' + reponse.status);
                    return reponse.json();
                })
                .then(function (donnees) {
                    if (maSequence !== sequence) return;
                    if (!donnees.success) throw new Error(donnees.message || 'Erreur');
                    afficherListe(donnees, silencieux === true);
                    majStats(donnees.stats);
                    majChips();
                })
                .catch(function (erreur) {
                    if (erreur.name === 'AbortError') return;
                    afficherToast('Impossible de charger les tâches. Réessayez.', { type: 'error' });
                });
        }

        formulaire.addEventListener('submit', function (e) {
            e.preventDefault();
            rafraichir();
        });

        recherche.addEventListener('input', function () {
            rafraichir();
        });

        [selectCategorie, selectPriorite, selectStatut, selectTri].forEach(function (select) {
            if (!select) return;
            select.addEventListener('change', function () {
                rafraichir();
            });
        });

        if (chips) {
            chips.addEventListener('click', function (e) {
                var bouton = e.target.closest('[data-filter]');
                if (!bouton) return;
                var cle = bouton.dataset.filter;
                if (cle === 'q') {
                    recherche.value = '';
                } else {
                    var champ = formulaire.elements[cle];
                    if (champ) champ.value = '';
                }
                rafraichir();
            });
        }

        region.addEventListener('click', function (e) {
            var lien = e.target.closest('[data-clear-filters]');
            if (!lien) return;
            e.preventDefault();
            recherche.value = '';
            selectCategorie.value = '';
            selectPriorite.value = '';
            selectStatut.value = '';
            selectTri.value = 'date';
            rafraichir();
            recherche.focus();
        });

        function appliquerEtat(article, terminee) {
            article.classList.toggle('is-done', terminee);
            article.dataset.statut = terminee ? 'terminee' : 'a_faire';

            var input = article.querySelector('.check__input');
            var titre = article.querySelector('.task__title');
            if (input) {
                input.checked = terminee;
                input.setAttribute('aria-label', (terminee ? 'Rouvrir' : 'Terminer') + ' : ' + (titre ? titre.textContent : 'tâche'));
            }

            var temps = article.querySelector('time.task__date');
            if (temps) {
                var date = dateRelative(temps.getAttribute('datetime'), terminee ? 'terminee' : 'a_faire');
                temps.classList.toggle('task__date--late', date.late);
                temps.title = date.title;
                temps.replaceChildren();
                if (date.late) {
                    var point = el('span', null, '●');
                    point.setAttribute('aria-hidden', 'true');
                    temps.appendChild(point);
                    temps.appendChild(document.createTextNode(' ' + date.label));
                } else {
                    temps.textContent = date.label;
                }
                article.dataset.late = date.late ? '1' : '0';
                article.classList.toggle('task--late', date.late);
            }
        }

        region.addEventListener('change', function (e) {
            var input = e.target.closest('.check__input');
            if (!input) return;

            var article = input.closest('.task');
            if (!article) return;

            var id = article.dataset.id;
            var etaitTerminee = article.classList.contains('is-done');
            appliquerEtat(article, !etaitTerminee);

            var donnees = new FormData();
            donnees.append('id', id);

            fetch('api/toggle_task.php', {
                method: 'POST',
                body: donnees,
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (reponse) { return reponse.json(); })
                .then(function (json) {
                    if (!json.success) throw new Error(json.message || 'Erreur');
                    appliquerEtat(article, json.statut === 'terminee');
                    majStats(json.stats);
                })
                .catch(function () {
                    appliquerEtat(article, etaitTerminee);
                    afficherToast('Impossible de mettre à jour la tâche. Vérifiez votre connexion.', { type: 'error' });
                });
        });

        region.addEventListener('click', function (e) {
            var bouton = e.target.closest('[data-delete]');
            if (!bouton) return;

            var article = bouton.closest('.task');
            var item = bouton.closest('.task-item');
            if (!article || !item || item.dataset.removing === '1') return;

            var id = bouton.dataset.delete;
            var titre = bouton.dataset.title || 'Tâche';
            item.dataset.removing = '1';
            item.classList.add('is-removing');

            var minuterie = setTimeout(function () {
                item.hidden = true;
            }, 320);

            var controle = afficherToast('« ' + titre + ' » supprimée', {
                actionLabel: 'Annuler',
                duration: 5000,
                onAction: function () {
                    clearTimeout(minuterie);
                    delete item.dataset.removing;
                    item.hidden = false;
                    item.classList.remove('is-removing');
                    if (!item.isConnected) rafraichir(true);
                },
                onExpire: function () {
                    var donnees = new FormData();
                    donnees.append('id', id);

                    fetch('api/delete_task.php', {
                        method: 'POST',
                        body: donnees,
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    })
                        .then(function (reponse) { return reponse.json(); })
                        .then(function (json) {
                            if (!json.success) throw new Error(json.message || 'Erreur');
                            item.remove();
                            majStats(json.stats);
                            if (region.querySelectorAll('.task-item').length === 0) rafraichir(true);
                        })
                        .catch(function () {
                            item.hidden = false;
                            item.classList.remove('is-removing');
                            delete item.dataset.removing;
                            afficherToast('La suppression a échoué. La tâche a été restaurée.', { type: 'error' });
                        });
                }
            });
        });
    }

    // ------------------------------------------------------------
    // Confirmation pour la suppression d'une catégorie
    // ------------------------------------------------------------
    $$('form[data-confirm]').forEach(function (formulaire) {
        formulaire.addEventListener('submit', function (e) {
            if (!window.confirm(formulaire.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
})();
