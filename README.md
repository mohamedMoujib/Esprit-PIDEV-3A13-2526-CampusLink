# CampusLink – Version Web

## Présentation du projet

**CampusLink** est une plateforme web universitaire développée avec **Symfony**, destinée aux étudiants afin de faciliter la gestion d’un marché de services au sein d’un environnement académique.

L’objectif principal du projet est de centraliser les échanges de services entre étudiants tout en intégrant un système complet de réservation, de paiement, d’évaluation et de gestion des factures, en utilisant la même base de données MySQL que la version desktop.

La plateforme permet aux étudiants de :

- Publier et consulter des services proposés par d'autres étudiants
- Réserver des services directement en ligne
- Suivre leurs paiements
- Consulter et gérer leurs factures
- Évaluer les services utilisés
- Rechercher et filtrer efficacement les activités disponibles

CampusLink favorise la collaboration, la transparence et la confiance entre les membres de la communauté universitaire grâce à une interface web moderne et accessible.

---

# Fonctionnalités principales

## Gestion des services
- Publication de services par les étudiants
- Consultation des services disponibles
- Recherche dynamique et filtrage avancé
- Gestion des catégories de services

## Gestion des réservations
- Réservation de services en ligne
- Consultation de l’historique des réservations
- Gestion des réservations actives
- Suivi du statut des réservations

## Système de paiement
- Enregistrement des paiements
- Historique des transactions
- Intégration avec la base de données MySQL existante
- Génération automatique des factures

## Gestion des factures
- Consultation des factures générées
- Recherche par identifiant ou utilisateur
- Filtrage par date
- Affichage détaillé des factures
- Suppression sécurisée avec confirmation

## Gestion des évaluations
- Attribution de notes aux services
- Ajout de commentaires après utilisation
- Consultation des avis et évaluations
- Amélioration de la qualité des prestations

## Gestion des utilisateurs
- Authentification et inscription
- Gestion des profils étudiants
- Sécurisation des accès avec Symfony Security
- Gestion des rôles (Étudiant / Administrateur)

---

# Technologies utilisées

- PHP 8
- Symfony 6
- Twig
- Doctrine ORM
- MySQL
- HTML5
- CSS3
- Bootstrap
- JavaScript
- Symfony Security
- Composer

---

# Architecture du projet

Le projet suit une architecture MVC afin de garantir une séparation claire des responsabilités :

```bash
campuslink/
│
├── src/
│   ├── Controller/
│   ├── Entity/
│   ├── Repository/
│   ├── Service/
│
├── templates/
├── public/
├── assets/
├── config/
└── migrations/
```

### Description des dossiers

- **Entity/** : Modèles de données liés à la base MySQL
- **Repository/** : Requêtes et accès aux données
- **Service/** : Logique métier
- **Controller/** : Gestion des requêtes utilisateur
- **templates/** : Interfaces Twig
- **assets/** : CSS, JavaScript et ressources frontend
- **migrations/** : Gestion des migrations de la base de données

---

# Base de données

La version web utilise la **même base de données MySQL** que la version JavaFX afin de conserver toutes les données existantes :

- utilisateurs
- services
- réservations
- paiements
- factures
- évaluations

L’intégration est assurée via **Doctrine ORM** et les paramètres de connexion Symfony.

---

# Objectifs pédagogiques

Ce projet permet de mettre en pratique :

- Le développement web avec Symfony
- L’architecture MVC
- La création d’applications full-stack
- L’intégration d’une base de données relationnelle
- La sécurisation des applications web
- La gestion des paiements et réservations
- L’amélioration de l’expérience utilisateur
- La structuration d’un projet professionnel

---

# Installation et exécution

## 1. Cloner le dépôt

```bash
git clone https://github.com/votre-utilisateur/campuslink.git
```

## 2. Installer les dépendances

```bash
composer install
```

## 3. Configurer la base de données

Modifier le fichier `.env` :

```env
DATABASE_URL="mysql://root:password@127.0.0.1:3306/campuslink"
```

## 4. Lancer les migrations

```bash
php bin/console doctrine:migrations:migrate
```

## 5. Démarrer le serveur Symfony

```bash
symfony server:start
```

ou

```bash
php -S localhost:8000 -t public
```

## 6. Accéder à l’application

```text
http://localhost:8000
```

---

# Sécurité

CampusLink utilise les fonctionnalités de sécurité de Symfony :

- Authentification sécurisée
- Gestion des sessions
- Protection CSRF
- Contrôle des accès par rôles
- Validation des formulaires

---

# Évolutions futures

- Système de messagerie entre étudiants
- Notifications en temps réel
- Paiement en ligne intégré
- Tableau de bord administrateur avancé
- API REST pour application mobile
- Version mobile responsive

---

# Licence

Projet réalisé dans un cadre académique à des fins éducatives.
