# Interface Prestataire CampusLink - Documentation Complète

## 🎯 Vue d'ensemble

L'espace Prestataire de CampusLink est une interface complète et moderne permettant aux prestataires de services de gérer efficacement:
- ✅ Leurs réservations
- 📋 Leurs services
- 💰 Leurs revenus
- 👥 Leurs interactions avec les étudiants

---

## 📁 Structure des fichiers

### Contrôleurs
```
src/Controller/Prestataire/
├── DashboardController.php      # Tableau de bord principal
├── ReservationController.php    # Gestion des réservations
├── ServiceController.php        # Gestion des services
└── PrestataireProfileController.php # Profil du prestataire
```

### Templates (Twig)
```
templates/prestataire/
├── layout.html.twig                 # Layout principal avec navigation
├── dashboard.html.twig              # Tableau de bord (statistiques)
├── reservations.html.twig           # Liste des réservations
├── reservation_details.html.twig    # Détails d'une réservation
├── services.html.twig               # Liste des services
├── service_form.html.twig           # Formulaire création/édition
├── service_details.html.twig        # Détails d'un service
└── profile.html.twig                # Profil du prestataire
```

---

## 🚀 Routes et endpoints

### Tableau de bord
- **`/prestataire`** ou **`/prestataire/dashboard`**
  - Route: `prestataire_dashboard`
  - Affiche les statistiques et réservations à venir

### Réservations
- **`/prestataire/reservations/`** - Route: `prestataire_reservations`
  - Liste toutes les réservations avec filtres
  - Paramètres de filtre: `?filter=all|upcoming|past`

- **`/prestataire/reservations/{id}/confirm`** - Route: `prestataire_reservation_confirm` (POST)
  - Confirme une réservation
  
- **`/prestataire/reservations/{id}/cancel`** - Route: `prestataire_reservation_cancel` (POST)
  - Annule une réservation
  
- **`/prestataire/reservations/{id}/details`** - Route: `prestataire_reservation_details`
  - Affiche les détails complets d'une réservation

### Services
- **`/prestataire/services/`** - Route: `prestataire_services`
  - Liste tous les services du prestataire

- **`/prestataire/services/new`** - Route: `prestataire_service_new`
  - Formulaire de création d'un nouveau service

- **`/prestataire/services/{id}/edit`** - Route: `prestataire_service_edit`
  - Formulaire de modification d'un service

- **`/prestataire/services/{id}/delete`** - Route: `prestataire_service_delete` (POST)
  - Supprime un service

- **`/prestataire/services/{id}`** - Route: `prestataire_service_details`
  - Affiche les détails d'un service

### Profil
- **`/prestataire/profile/`** - Route: `prestataire_profile`
  - Affiche le profil du prestataire

---

## 📊 Fonctionnalités principales

### 1. Tableau de bord (Dashboard)
**Fichier:** `DashboardController.php` & `templates/prestataire/dashboard.html.twig`

**Statistiques affichées:**
- Total des réservations
- Réservations en attente (avec badge d'alerte)
- Réservations confirmées
- Revenu total (calcul à partir des réservations confirmées)
- Nombre de services actifs

**Contenus:**
- ✨ Réservations à venir (5 premières)
- 📋 Tableau des réservations confirmées
- Actions rapides (Ajouter un service, Voir toutes les réservations)

### 2. Gestion des réservations
**Fichier:** `ReservationController.php` & `templates/prestataire/reservations.html.twig`

**Fonctionnalités:**
- Filtrage par statut (Toutes, À venir, Passées)
- Actions rapides sur chaque réservation:
  - ✓ Confirmer (si en attente)
  - ✕ Annuler (si en attente)
  - → Voir les détails
- Affichage du statut avec badges visuels
- Informations détaillées par réservation:
  - Service
  - Étudiant
  - Date et heure
  - Prix
  - Statut

**Statuts disponibles:**
- `PENDING` → ⏳ En attente
- `CONFIRMED` → ✅ Confirmée
- `CANCELLED` → ❌ Annulée

### 3. Gestion des services
**Fichier:** `ServiceController.php` & `templates/prestataire/services.html.twig`

**Fonctionnalités:**
- Créer un nouveau service
- Éditer un service existant
- Supprimer un service
- Voir les détails d'un service

**Champs du service:**
- Titre
- Description
- Prix (en DT)
- Catégorie
- Image
- Statut

**Affichage carte service:**
- Prix
- Catégorie
- Nombre de réservations
- Actions (Voir, Modifier, Supprimer)

### 4. Page de profil prestataire
**Fichier:** `profile.html.twig` & `PrestataireProfileController.php`

**Informations affichées:**
- Nom complet
- Email
- Téléphone
- Adresse
- Genre
- Date de naissance
- Points de confiance
- Statut du compte
- Date d'inscription

---

## 🎨 Design et UX

### Couleurs
```css
--primary-color: #5b6cff        /* Bleu principal */
--success-color: #10b981        /* Vert succès */
--warning-color: #f59e0b        /* Orange alerte */
--danger-color: #ef4444         /* Rouge danger */
--light-bg: #f3f4f6             /* Gris très clair */
--border-color: #e5e7eb         /* Gris bordure */
```

### Layout
- **Sidebar fixe** (280px) avec navigation
- **Contenu flexible** adaptable
- **Cartes et sections** bien délimitées
- **Responsive** sur tous les appareils

### Badges de statut
- **En attente:** Fond jaune, texte marron
- **Confirmé:** Fond vert, texte foncé
- **Annulé/Refusé:** Fond rouge, texte marron

---

## 🔐 Sécurité

### Authentification
Toutes les pages requièrent:
- **Authentification:** `ROLE_PRESTATAIRE` ou `ROLE_ADMIN`
- **Propriété:** Les prestataires ne peuvent voir/modifier que leurs propres données

### Vérifications d'accès
```php
$this->denyAccessUnlessGranted('ROLE_PRESTATAIRE');
```

### Vérification de propriété
Exemple pour les réservations:
```php
if ($reservation->getService()->getUser() !== $this->getUser()) {
    throw $this->createAccessDeniedException();
}
```

---

## 💾 Gestion des données

### Entités impliquées
1. **User** - L'utilisateur prestataire
2. **Service** - Services offerts
3. **Reservation** - Réservations des étudiants
4. **Categorie** - Catégories de services

### Relations
```
User (prestataire)
├── 1:n Services
│   └── 1:n Reservations
│       └── User (student)
```

### Requêtes Doctrine
Les contrôleurs utilisent le QueryBuilder pour:
- Filtrer les réservations par prestataire
- Filtrer les réservations passées/à venir
- Calculer les statistiques
- Gérer les statuts

---

## 📱 Responsive design

### Breakpoints
- **Desktop:** 1024px+
- **Tablet:** 768px - 1023px
- **Mobile:** < 768px

### Adaptations mobiles
- Sidebar devient navigation en haut
- Grilles deviennent une colonne
- Boutons deviennent full-width si nécessaire

---

## 🔧 Installation et configuration

### 1. Routes
Les routes sont définies via annotations Symfony:
```php
#[Route('/prestataire', name: 'prestataire_dashboard')]
```

### 2. Templates
Utilisation du layout principal:
```twig
{% extends "prestataire/layout.html.twig" %}
{% block prestataire_content %}
    {# Contenu ici #}
{% endblock %}
```

### 3. Contrôle d'accès
Dans `config/packages/security.yaml`, assurez-vous:
- Routes `/prestataire/**` protégées par `ROLE_PRESTATAIRE`

---

## 📈 Statistiques et calculs

### Revenu total
```php
$totalRevenue = 0;
foreach ($confirmedReservations as $reservation) {
    $totalRevenue += (float) $reservation->getPrice();
}
```

### Comptage des réservations
```php
$pendingCount = count(array_filter($allReservations, function($r) {
    return $r->getStatus() === 'PENDING';
}));
```

### Filtrage des dates
```php
$upcomingReservations = $reservationRepository->createQueryBuilder('r')
    ->where('r.date > :now')
    ->setParameter('now', new \DateTime())
    ->getQuery()
    ->getResult();
```

---

## 🎓 Utilisation par le prestataire

### Workflow typique

#### 1. Première visite
1. Vue du tableau de bord
2. Navigation vers "Mes services"
3. Création d'un premier service
4. Renseignement du profil

#### 2. Gestion des réservations
1. Réception d'une réservation (statut PENDING)
2. Confirmation ou refus de la réservation
3. Suivi via le tableau de bord

#### 3. Gestion des services
1. Création de services variés
2. Modification si nécessaire
3. Suppression des services obsolètes
4. Consultation des réservations par service

---

## 🚨 Messages et notifications

### Types de notifications
- **Succès:** ✅ Vert clair (#dcfce7)
- **Avertissement:** ⚠️ Jaune (#fef3c7)
- **Info:** ℹ️ Bleu (#e0e7ff)

### Flash messages
```php
$this->addFlash('success', 'Réservation confirmée avec succès!');
$this->addFlash('warning', 'Réservation annulée!');
```

---

## 🔗 Intégrations futures possibles

- Messagerie entre prestataire et étudiant
- Système d'avis et notation
- Calendrier des réservations
- Export de données (PDF, CSV)
- Statistiques avancées
- Tarification dynamique
- Disponibilités et créneaux horaires

---

## ✅ Checklist de fonctionnalités implémentées

- ✅ Tableau de bord avec statistiques
- ✅ Liste des réservations avec filtres
- ✅ Actions sur réservations (confirmer/annuler)
- ✅ Détails des réservations
- ✅ Gestion complète des services (CRUD)
- ✅ Détails des services
- ✅ Profil du prestataire
- ✅ Navigation cohérente
- ✅ Design moderne et responsive
- ✅ Sécurité basée sur les rôles
- ✅ Vérifications de propriété des données
- ✅ Messages flash
- ✅ Styling CSS intégré
- ✅ Responsif mobiles/tablettes
- ✅ Formules pour les statistiques

---

## 📞 Support et dépannage

### Problèmes courants

**Problem:** Les réservations ne s'affichent pas
**Solution:** Vérifier que les services sont bien liés à l'utilisateur connecté

**Problem:** Boutons d'action ne répondent pas
**Solution:** Assurez-vous que l'utilisateur a le rôle ROLE_PRESTATAIRE

**Problem:** Template ne s'affiche pas correctement
**Solution:** Vérifier que vous étendez le bon layout

---

## 📚 Ressources et références

- Symfony Doctrine: https://symfony.com/doc/current/doctrine.html
- Twig Documentation: https://twig.symfony.com/doc/
- Symfony Forms: https://symfony.com/doc/current/forms.html
- Symfony Security: https://symfony.com/doc/current/security.html

---

**Dernière mise à jour:** Avril 2026  
**Version:** 1.0  
**Développé pour:** CampusLink - Gestion des services et réservations
