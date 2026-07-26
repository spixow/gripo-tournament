# 🎮 TOURNOI FIFA 1v1 — Plateforme de Ligue

Plateforme web pour gérer un tournoi FIFA 1 contre 1 entre amis (12 joueurs, 5 rounds, 30 matchs),
développée en **PHP / MySQL**, avec un fond animé **Three.js** et une interface **Bootstrap 5**.

## ✨ Fonctionnalités

- **Comptes joueurs** : un compte par joueur + un compte administrateur.
- **Calendrier** : les 30 matchs des 5 rounds générés automatiquement.
- **Double validation du score** : après un match, **les deux joueurs** saisissent le score
  et joignent une **preuve image**. Le match est validé automatiquement si les deux scores
  concordent, sinon il passe en **litige**.
- **Classement automatique** avec départage : Points → Différence de buts → Buts marqués → Confrontation directe, avec podium et forme récente (5 derniers résultats).
- **Phase finale (bracket)** : barrages (3ᵉ–6ᵉ) → demi-finales → finale, avec avancement
  automatique des vainqueurs et affichage du champion.
- **Panneau admin** : résolution des litiges, forçage de score, réinitialisation d'un match
  et **gestion de la phase finale**.
- **Fond 3D animé** (Three.js) : pelouse de stade EA SPORTS FC 26 + design néon responsive.

## 🚀 Installation

1. Copiez le projet dans le dossier web de votre serveur (ex : `htdocs` de XAMPP/WAMP).
2. Créez un serveur MySQL et, si besoin, ajustez les identifiants dans
   [`config/config.php`](config/config.php) (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
3. Ouvrez `http://localhost/TOURNOI%20FIFA/install.php` dans le navigateur.
4. Cliquez sur **« Lancer l'installation »**.
5. **Notez les identifiants générés** affichés à l'écran.
6. **Supprimez `install.php`** pour la sécurité.
7. Accédez à la plateforme via `index.php`.

> **Mise à niveau d'une installation existante** : si la base a été créée avant l'ajout des
> cartes joueurs et de la phase finale, ouvrez `upgrade.php` une fois (ajoute les colonnes et la
> table du bracket sans perte de données), puis supprimez-le.

## 🔑 Identifiants

- **Administrateur** : `admin` / `admin2026`
- **Joueurs** : nom d'utilisateur = nom en minuscules sans espace, mot de passe = `<utilisateur>2026`
  - Exemples : `smock` / `smock2026`, `nomercy` / `nomercy2026`, `araknocci` / `araknocci2026`.

> Chaque joueur peut changer son mot de passe depuis la page **Mes matchs**.

## 🏆 Règlement

| Résultat | Points |
|----------|--------|
| Victoire | 3      |
| Nul      | 1      |
| Défaite  | 0      |

**Deadline de la phase de ligue :** 27-07-2026.

## 🗂️ Structure

```
config/       Configuration + connexion PDO
includes/     Fonctions, header, footer
assets/       CSS + Three.js
sql/          Schéma de la base
uploads/      Preuves images (protégé)
install.php   Script d'installation
index.php     Accueil / tableau de bord
login.php     Connexion
standings.php Classement complet
matches.php   Calendrier des matchs
match.php     Détail + saisie du score
bracket.php   Phase finale (barrages / demies / finale)
profile.php   Espace joueur
admin.php     Panneau administrateur
upgrade.php   Mise à niveau d'une base existante
```

## 🔒 Sécurité

- Mots de passe hachés (`password_hash`).
- Protection CSRF sur tous les formulaires.
- Validation stricte des uploads (type MIME, taille, extension).
- Exécution de scripts désactivée dans `uploads/`.
- Requêtes préparées (PDO) contre l'injection SQL.

## ⚙️ Prérequis

- PHP 8.0+ (utilise `match`, types nullables).
- MySQL 5.7+ / MariaDB 10.3+.
- Extension PHP `pdo_mysql` et `fileinfo`.
