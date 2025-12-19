# Planning Poker - Application Web

Application de Planning Poker pour estimer la complexité des User Stories en méthodologie Scrum.

## Application en ligne

L'application est déployée et accessible directement :

**https://planningpoker.infinityfreeapp.com/Planning_poker/public/index.php**

Aucune installation nécessaire - Créez une session et commencez à voter !

---

## Installation Locale

### Prérequis

- XAMPP (Apache + MySQL + PHP)
- Git
- Composer

### Étapes d'installation

**1. Démarrer XAMPP**
- Lancer Apache
- Lancer MySQL

**2. Cloner le projet**
```bash
cd C:\xampp\htdocs
git clone https://github.com/diatigui75/Planning_poker.git
cd Planning_poker
```

**3. Installer les dépendances**
```bash
composer install
```

**4. Créer les bases de données**

Via phpMyAdmin (http://localhost/phpmyadmin) :
- Créer la base `planning_poker`
- Créer la base `planning_poker_test`
- Importer `database.sql` dans les deux bases

Ou via ligne de commande :
```bash
mysql -u root -p -e "CREATE DATABASE planning_poker;"
mysql -u root -p -e "CREATE DATABASE planning_poker_test;"
mysql -u root -p planning_poker < database.sql
mysql -u root -p planning_poker_test < database.sql
```

**5. Configurer la connexion**

Copier les templates de configuration :
```bash
cp config/config_exemple.php config/config.php
cp config/config_example_test.php config/config.test.php
```

Éditer `config/config.php` avec vos paramètres :
```php
<?php
return [
    'db_host' => 'localhost',
    'db_port' => 3307,              // 3306 si XAMPP standard
    'db_name' => 'planning_poker',
    'db_user' => 'root',
    'db_pass' => '',                // Votre mot de passe MySQL
    'base_url' => '/Planning_poker/public',
];
```

Faire de même pour `config/config.test.php` (avec `planning_poker_test` comme base).

**6. Accéder à l'application**

Ouvrir dans un navigateur : **http://localhost/Planning_poker/public/index.php**

**7. Tester l'installation**
```bash
composer test
```

---

## Commandes utiles

```bash
composer test              # Tests unitaires
composer stan              # Analyse statique
composer check             # Tous les contrôles qualité
```

---

## Structure du projet

```
Planning_poker/
├── config/               # Configuration base de données
├── public/              # Point d'entrée (index.php, vote.php)
├── src/                 # Code source (Models, Controllers, Services)
├── tests/               # Tests PHPUnit
├── database.sql         # Schéma de base de données
└── composer.json        # Dépendances PHP
```

---

## Notes importantes

- **Port MySQL** : Le projet utilise par défaut le port 3307. Si votre XAMPP utilise 3306, adaptez `config.php`, `config.test.php` et `phpunit.xml`.

- **Fichiers sensibles** : `config.php` et `config.test.php` contiennent vos credentials et ne doivent pas être commités (déjà dans `.gitignore`).

- **Deux bases** : Une base pour l'application (`planning_poker`) et une pour les tests (`planning_poker_test`) pour garantir l'isolation.

---

## Problèmes courants

**"Fichier de configuration introuvable"**
```bash
cp config/config_exemple.php config/config.php
# Éditer avec vos paramètres
```

**"Access denied for user 'root'"**
- Vérifier le mot de passe dans `config/config.php`
- Par défaut XAMPP : mot de passe vide

**"Connection refused"**
- Vérifier que MySQL est démarré dans XAMPP
- Vérifier le port dans `config/config.php`

**Tests échouent avec "Unknown database"**
```bash
mysql -u root -p -e "CREATE DATABASE planning_poker_test;"
mysql -u root -p planning_poker_test < database.sql
```

---

## Équipe

- Melissa Aliouche
- Haitam Khoumri
- Fane Diatigui

**M1 Informatique - Université Lumière Lyon 2 - 2025/2026**

