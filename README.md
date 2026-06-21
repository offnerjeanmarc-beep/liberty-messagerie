# Messagerie IA - Conciergerie

Application web PHP/MySQL qui lit les messages voyageurs Lodgify, affiche les conversations à traiter et permet de demander un brouillon IA par logement avant envoi manuel.

Stack : **PHP + MySQL + cron**, compatible avec un hébergement cPanel mutualisé.

## Structure

```text
messagerie liberty IA/
  config/
    config.example.php   -> à copier en config.php
    .htaccess            -> bloque l'accès web aux secrets
  cron/
    poll.php             -> surveillance Lodgify
    .htaccess            -> bloque l'accès web
  public/                -> seul dossier à exposer sur le web
    index.php            -> boîte de réception
    instructions.php     -> chatbots par logement
    historique.php       -> historique
    diagnostic.php       -> contrôle cPanel/MySQL/cron
    actions/             -> endpoints AJAX
    assets/              -> CSS/JS
  sql/
    schema.sql           -> tables MySQL
    .htaccess            -> bloque l'accès web
  src/                   -> librairies PHP privées
```

**Important :** le Document Root du domaine ou sous-domaine doit pointer vers `public/`. Les dossiers `config/`, `src/`, `cron/` et `sql/` ne doivent pas être exposés.

## Déploiement GitHub

Le script recommandé est :

```powershell
.\push-to-github.ps1 -RepoUrl "https://github.com/VOTRE-COMPTE/messagerie-ia-conciergerie.git"
```

Ensuite, pour les mises à jour :

```powershell
.\update-github.ps1 -Message "Description courte de la modification"
```

Le fichier `config/config.php` est ignoré par Git. Les clés API, mots de passe et secrets ne doivent jamais être poussés.

## Déploiement cPanel

1. Créer un dépôt GitHub vide, puis pousser le projet avec `push-to-github.ps1`.
2. Dans cPanel, utiliser **Git Version Control** ou téléverser les fichiers.
3. Créer un sous-domaine, par exemple `messagerie.votredomaine.fr`.
4. Régler le **Document Root** sur :

```text
/home/UTILISATEUR/messagerie/public
```

5. Créer la base MySQL et son utilisateur dans cPanel.
6. Importer `sql/schema.sql` dans phpMyAdmin.
7. Copier `config/config.example.php` vers `config/config.php`.
8. Remplir les identifiants MySQL, `app_key`, compte admin et clé Lodgify.

Génération des valeurs sensibles :

```bash
php -r "echo bin2hex(random_bytes(32));"
php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT);"
```

## Cron

Ajouter dans cPanel > **Cron Jobs** :

```text
*/2 * * * * /usr/local/bin/php /home/UTILISATEUR/messagerie/cron/poll.php >> /home/UTILISATEUR/messagerie/cron/poll.log 2>&1
```

Le chemin de PHP peut varier selon l'hébergeur. La page **Diagnostic** affiche le dernier passage connu du cron.

## Première utilisation

1. Aller sur `/login.php` et se connecter avec le compte admin.
2. Ouvrir **Diagnostic** et corriger tout point rouge ou orange.
3. Ouvrir **Instructions des chatbots**.
4. Cliquer sur **Synchroniser les logements (Lodgify)**.
5. Pour chaque logement : renseigner instruction, fournisseur IA, modèle, clé API, statut actif et envoi auto.
6. Utiliser **Tester** avant d'activer l'envoi automatique.

## Mode hybride

Le fonctionnement actuel est manuel :

- le cron récupère les nouveaux messages Lodgify ;
- la boîte de réception affiche les conversations à traiter ;
- une conversation non lue est surlignée en vert clair ;
- la page conversation affiche le fil complet au format vertical, adapté mobile ;
- le bouton **Demander à l'IA** génère un brouillon seulement à la demande ;
- l'administrateur relit, modifie, puis envoie manuellement.

L'envoi automatique est volontairement désactivé pour l'instant.

## Notes techniques

- Lecture des messages : `GET /v2/messaging/{thread_uid}`.
- Envoi : `POST /v1/reservation/booking/{booking_id}/messages`.
- Les clés IA par logement sont chiffrées avec AES-256-CBC.
- Fournisseurs IA pris en charge : Claude/Anthropic et OpenAI.
