# Laravel Subscription

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vnuswilliams/laravel-subscription.svg?style=flat-square)](https://packagist.org/packages/vnuswilliams/laravel-subscription)
[![Total Downloads](https://img.shields.io/packagist/dt/vnuswilliams/laravel-subscription.svg?style=flat-square)](https://packagist.org/packages/vnuswilliams/laravel-subscription)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue?style=flat-square)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20|%2012%20|%2013-red?style=flat-square)](https://laravel.com)

Un package Laravel robuste, fluide et **entièrement agnostique au paiement** pour gérer les plans d’abonnement, les cycles de vie (essai, grâce, annulation) et les quotas de fonctionnalités consommables.

Ce package ne traite aucun paiement. Il gère exclusivement **la logique métier de l’abonnement** : qui a accès à quoi, pendant combien de temps, et combien lui reste-t-il. Vous branchez le système de paiement de votre choix (Stripe, Paystack, Flutterwave, PayPal…) autour.

Cette documentation est aussi [fourni en anglais](README.md).

---

-----

## Sommaire

1. [Architecture](#architecture)
1. [Installation](#installation)
1. [Configuration des plans en base](#configuration-des-plans-en-base)
1. [Préparer le modèle souscripteur](#préparer-le-modèle-souscripteur)
1. [Points d’entrée : trois façons d’utiliser le package](#points-dentrée--trois-façons-dutiliser-le-package)
1. [Gestion des abonnements](#gestion-des-abonnements)
1. [Features & Quotas](#features--quotas)
1. [Cycle de vie & Période de grâce](#cycle-de-vie--période-de-grâce)
1. [Middleware de protection des routes](#middleware-de-protection-des-routes)
1. [Événements Laravel](#événements-laravel)
1. [Commande Artisan](#commande-artisan)
1. [Recette complète : service applicatif](#recette-complète--service-applicatif)
1. [Référence API](#référence-api)
1. [Conseils & Bonnes pratiques](#conseils--bonnes-pratiques)

-----

## Architecture

Le package repose sur une séparation stricte des responsabilités :

```
SubscriptionManager          ← Point d'entrée public (Facade ou injection)
    │
    ├── SubscriptionService  ← Logique : subscribeTo, cancel, switchTo, renew…
    └── FeatureService       ← Logique : canConsume, consume, release, balance…

HasSubscriptions (Trait)     ← Proxy ergonomique sur le modèle Eloquent
```

**Règle d’or :** le trait `HasSubscriptions` ne contient aucune logique métier. Il délègue tout au `SubscriptionManager`. Ainsi, la logique reste testable, injectable et indépendante de l’Eloquent model.

-----

## Installation

### 1. Installer via Composer

```bash
composer require vnuswilliams/laravel-subscription
```

Le `ServiceProvider` et la `Facade` sont auto-découverts par Laravel. Aucune déclaration manuelle nécessaire.

### 2. Publier la configuration et les migrations

Utilisez la commande d'installation du package pour publier la configuration et les migrations en une seule étape :

```bash
php artisan subscription:install

# Choisissez ceci avant les migrations si votre modèle souscripteur utilise des UUIDs ou ULIDs
# Valeurs supportées : id (défaut), uuid, ulid
SUBSCRIPTIONS_SUBSCRIBER_KEY_TYPE=uuid

# Lancer les migrations
php artisan migrate
```

Si `config/subscriptions.php` ou une migration du package existe déjà dans votre application, `subscription:install` supprime d'abord le fichier existant puis régénère une copie fraîche depuis le package. Par défaut, la configuration `subscriptions.subscriber_key_type` vaut `id` et crée un `subscriber_id` entier classique. Définissez-la à `uuid` ou `ulid` avant d'exécuter les migrations du package si le modèle qui utilise `HasSubscriptions` possède une clé primaire UUID/ULID. Les migrations utilisent aussi `subscriptions.price.precision` et `subscriptions.price.scale` pour les prix des plans et abonnements, avec une colonne `decimal(12, 2)` par défaut afin de supporter aussi bien `100` que `19.99`.

### 3. (Optionnel) Publier le service applicatif stub

```bash
php artisan vendor:publish --tag=subscription-stubs
```

Cette commande copie `app/Services/SubscriptionService.php` dans votre projet — un service pré-rempli adapté à votre domaine métier (voir section [Recette complète](#recette-complète--service-applicatif)).

-----

## Configuration des plans en base

Le package ne crée pas vos plans automatiquement. Vous les insérez via un seeder, une migration ou l’interface d’administration de votre application.

Voici la structure attendue pour un plan mensuel avec features :

```php
// database/seeders/PlanSeeder.php

use Vnuswilliams\Subscription\Models\Plan;
use Vnuswilliams\Subscription\Enums\FeatureType;
use Vnuswilliams\Subscription\Enums\PeriodicityType;

// Plan Pro — mensuel, 7 jours de grâce
$pro = Plan::create([
    'name'             => 'Pro',
    'slug'             => 'pro',
    'description'      => 'Pour les équipes en croissance.',
    'price'            => 19.99,
    'periodicity_type' => PeriodicityType::Month->value,  // 'month'
    'periodicity'      => 1,
    'trial_days'       => 0,
    'grace_days'       => 7,
    'is_active'        => true,
]);

// Feature consumable : quota d'employés
$pro->features()->create([
    'slug'    => 'max-employees',
    'name'    => "Nombre d'employés",
    'type'    => FeatureType::Consumable->value,  // 'consumable'
    'charges' => 25,  // 25 slots disponibles
]);

// Feature booléenne : accès à l'espace collaborateur
$pro->features()->create([
    'slug'    => 'espace-collaborateur',
    'name'    => 'Espace collaborateur',
    'type'    => FeatureType::Boolean->value,  // 'boolean'
    'charges' => null,  // null = illimité / pas de compteur
]);

// Plan Gratuit — permanent (pas de periodicity), essai 15 jours
Plan::create([
    'name'             => 'Gratuit',
    'slug'             => 'free',
    'price'            => 0,
    'periodicity_type' => null,   // null = plan permanent, ne expire jamais
    'periodicity'      => null,
    'trial_days'       => 15,
    'grace_days'       => 0,
    'is_active'        => true,
]);
```

> **Conseil :** centralisez tous les slugs de features dans un `FeatureEnum` dans votre application. Vous éviterez les fautes de frappe et bénéficierez de l’autocomplétion IDE.

```php
// app/Enums/FeatureEnum.php
enum FeatureEnum: string
{
    case MAX_EMPLOYEES        = 'max-employees';
    case ESPACE_COLLABORATEUR = 'espace-collaborateur';
    case DOCUMENTS            = 'documents';
    case RAPPORTS_AVANCES     = 'rapports-avances';
    case SUPPORT_PRIORITAIRE  = 'support-prioritaire';
}
```

-----

## Préparer le modèle souscripteur

Ajoutez le trait `HasSubscriptions` sur n’importe quel modèle Eloquent qui doit pouvoir souscrire à un plan : `User`, `Company`, `Team`, `Organization`…

```php
// app/Models/Company.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Vnuswilliams\Subscription\Traits\HasSubscriptions;

class Company extends Model
{
    use HasSubscriptions;
}
```

C’est tout. Le trait expose automatiquement la relation `subscription()` et toutes les méthodes fluides du package directement sur votre modèle.

-----

## Points d’entrée : trois façons d’utiliser le package

Le package expose trois interfaces selon le contexte d’utilisation. Choisissez celle qui correspond à votre situation.

### 1. Via le Trait (sur le modèle)

La syntaxe la plus fluide pour des appels ponctuels directement sur l’instance Eloquent :

```php
$company->subscribeTo('pro');
$company->hasActiveSubscription();
$company->canConsume('max-employees', 1);
$company->balance('max-employees');
```

Idéal dans les Observers, les Policies, ou les vérifications rapides dans un Controller.

### 2. Via la Facade (n’importe où dans l’app)

La syntaxe statique Laravel-style, accessible partout sans injection :

```php
use Vnuswilliams\Subscription\Facades\Subscription;

Subscription::subscribeTo($company, 'pro');
Subscription::cancel($company);
Subscription::canConsume($company, 'max-employees', 1);
Subscription::balance($company, 'max-employees');
```

Idéal dans les Controllers, les Actions, les Jobs ou les Listeners.

### 3. Via l’injection du SubscriptionManager (dans vos services)

La méthode recommandée pour la logique métier complexe. Pleinement testable, sans dépendance statique :

```php
use Vnuswilliams\Subscription\SubscriptionManager;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionManager $subscription,
    ) {}

    public function canAddEmployee(Company $company): bool
    {
        return $this->subscription->hasActiveSubscription($company)
            && $this->subscription->canConsume($company, FeatureEnum::MAX_EMPLOYEES->value, 1);
    }
}
```

> **Conseil :** dans un service applicatif dédié aux abonnements, préférez toujours l’injection directe. La Facade est pratique pour des appels isolés, mais elle rend le code plus difficile à tester unitairement.

-----

## Gestion des abonnements

### Souscrire à un plan

Passez le slug du plan (string) ou directement une instance `Plan` :

```php
// Par slug
$company->subscribeTo('pro');

// Par instance
$plan = Plan::where('slug', 'pro')->firstOrFail();
$company->subscribeTo($plan);

// Via la Facade
use Vnuswilliams\Subscription\Facades\Subscription;
Subscription::subscribeTo($company, 'pro');
```

L'abonnement créé stocke son propre `price`. Si vous ne passez pas de prix personnalisé, le prix du plan est copié au moment de la souscription. Vous pouvez le surcharger pour un prix négocié, des add-ons, une remise ou un prorata :

```php
// Utilise le prix du plan
$company->subscribeTo('pro');

// Stocke 24.99 sur l'abonnement, sans modifier le prix du plan
$company->subscribeTo('pro', price: 24.99);

// Les prix entiers sont aussi supportés
Subscription::subscribeTo($company, 'enterprise', price: 100);
```

Si le plan a des `trial_days > 0`, le statut sera automatiquement `on_trial` et `trial_ends_at` sera calculé. Aucune action supplémentaire requise.

### Souscrire avec une expiration personnalisée

Utile pour les plans gratuits ou les offres promotionnelles à durée fixe :

```php
// Plan gratuit avec essai de 15 jours
$company->subscribeTo('free', expiration: now()->addDays(15));

// Offre promotionnelle : 3 mois offerts
$company->subscribeTo('pro', expiration: now()->addMonths(3));
```

### Changer de plan (upgrade / downgrade)

```php
// Changement immédiat : l'ancien abonnement est supprimé, le nouveau commence
$company->switchTo('business');

// Changement différé : l'ancien abonnement court jusqu'à son terme
$company->switchTo('starter', immediately: false);

// Via la Facade
Subscription::switchTo($company, 'business');
```

### Annuler un abonnement

L’annulation **ne coupe pas l’accès immédiatement**. L’utilisateur conserve l’accès jusqu’à `ends_at`, puis la période de grâce s’active si configurée. C’est le comportement attendu pour une résiliation en fin de période.

```php
$company->subscription->cancel();

// Ou via la Facade
Subscription::cancel($company);
```

Pour vérifier si un abonnement est annulé mais court encore :

```php
if ($company->subscription->isCanceled()) {
    // L'utilisateur a résilié, mais a encore accès jusqu'à ends_at
    $expiresAt = $company->subscriptionExpiresAt();
}
```

### Supprimer l’accès immédiatement

Pour couper l’accès sans attendre la fin de période (suspension pour non-paiement, violation des CGU, etc.) :

```php
$company->subscription->suppress();

// Ou via la Facade
Subscription::suppress($company);
```

### Renouveler un abonnement

Relance un cycle complet depuis maintenant. Utile après un paiement réussi :

```php
$company->renewSubscription();

// Ou via la Facade
Subscription::renew($company);
```

### Vérifier l’état de l’abonnement

```php
// L'abonnement est-il valide ? (actif, essai, ou en grâce)
$company->hasActiveSubscription(); // bool

// Quel est le plan actuel ?
$plan = $company->currentPlan(); // Plan|null
echo $plan->name;  // 'Pro'
echo $plan->slug;  // 'pro'

// Quand expire-t-il ?
$date = $company->subscriptionExpiresAt(); // Carbon|null

// Accès direct au modèle Subscription
$sub = $company->subscription;
$sub->isActive();        // bool
$sub->isOnTrial();       // bool
$sub->isOnGracePeriod(); // bool
$sub->isCanceled();      // bool
$sub->isExpired();       // bool
$sub->hasAccess();       // bool — agrège tous les états valides
```

-----

## Features & Quotas

### Features booléennes (accès oui/non)

Une feature booléenne est simplement attachée ou non au plan. Si elle n’est pas dans la liste des features du plan, l’accès est refusé.

```php
// L'entreprise a-t-elle accès à l'espace collaborateur ?
if ($company->canConsume('espace-collaborateur')) {
    // accès autorisé
}

// Via la Facade
if (Subscription::canConsume($company, 'espace-collaborateur')) {
    // accès autorisé
}
```

> Pour une feature booléenne, le paramètre `$amount` est ignoré. `canConsume('feature', 0)` et `canConsume('feature', 1)` retournent le même résultat.

### Features consumables (quotas)

Le flux standard pour une feature à quota : vérifier → agir → consommer.

```php
// ✅ Pattern recommandé
if ($company->canConsume('max-employees', 1)) {

    // L'action métier d'abord
    $employee = Employee::create([...]);

    // La consommation ensuite
    $company->consume('max-employees', 1);

} else {
    return back()->with('error', 'Quota d\'employés atteint. Passez à un plan supérieur.');
}
```

> **Important :** appelez toujours `canConsume()` avant `consume()`. Le package ne lève pas d’exception si vous consommez au-delà du quota — c’est à votre code de gérer cette garde.

### Libérer un slot (décrémenter la consommation)

Quand vous supprimez une ressource, libérez le slot correspondant :

```php
// Suppression d'un employé → libère 1 slot
$employee->delete();
$company->release('max-employees', 1);
```

`release()` décrémente `used` de manière sûre (jamais en dessous de 0). C’est plus fiable que de supprimer le dernier enregistrement de consommation.

### Consulter les quotas (pour les dashboards)

```php
// Slots totaux alloués par le plan
$total = $company->totalCharges('max-employees');  // ex: 25

// Slots consommés sur la période en cours
$used = $company->usedCharges('max-employees');    // ex: 17

// Slots restants (PHP_INT_MAX si illimité)
$remaining = $company->balance('max-employees');   // ex: 8
```

Exemple d’utilisation dans une vue Blade pour une barre de progression :

```blade
@php
    $total     = $company->totalCharges('max-employees');
    $used      = $company->usedCharges('max-employees');
    $remaining = $company->balance('max-employees');
    $percent   = $total > 0 ? round(($used / $total) * 100) : 0;
@endphp

<div class="quota-bar">
    <div class="quota-bar__fill" style="width: {{ $percent }}%"></div>
</div>
<p>{{ $used }} / {{ $total }} employés — {{ $remaining }} slots restants</p>
```

-----

## Cycle de vie & Période de grâce

Le cycle de vie complet d’un abonnement :

```
[on_trial] ──(trial_ends_at dépassé)──> [active]
[active]   ──(ends_at dépassé)────────> [on_grace_period] ──(grace_ends_at dépassé)──> [expired]
[active]   ──(cancel())───────────────> [canceled] (hasAccess() = true jusqu'à ends_at)
[active]   ──(suppress())─────────────> [expired]  (hasAccess() = false immédiatement)
```

La méthode `hasAccess()` est votre source de vérité. Elle retourne `true` pour les statuts `active`, `on_trial`, `on_grace_period` et `canceled` (si `ends_at` est dans le futur). Elle retourne `false` pour `expired` et les abonnements supprimés.

### Configurer la période de grâce par plan

La grâce se configure dans les données du plan (colonne `grace_days`). Aucune configuration globale n’est requise. Chaque plan peut avoir sa propre durée :

```php
Plan::create([
    'slug'       => 'pro',
    'grace_days' => 7,   // 7 jours de grâce après expiration
    // ...
]);

Plan::create([
    'slug'       => 'free',
    'grace_days' => 0,   // Pas de grâce sur le plan gratuit
    // ...
]);
```

-----

## Middleware de protection des routes

Le package enregistre automatiquement le middleware `subscribed`. Utilisez-le dans vos fichiers de routes :

```php
// routes/web.php

// Exige n'importe quel abonnement valide
Route::middleware(['auth', 'subscribed'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/employees', [EmployeeController::class, 'index']);
});

// Exige un plan spécifique (par slug)
Route::middleware(['auth', 'subscribed:business'])->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/support', [SupportController::class, 'index']);
});
```

En cas de refus, le middleware retourne :

- **JSON 403** si la requête attend du JSON (`Accept: application/json`)
- **Redirect** vers `home` avec un message `error` en session sinon

Pour personnaliser ce comportement, étendez `CheckSubscription` et rebindez-le dans votre `AppServiceProvider`.

-----

## Événements Laravel

Le package émet des événements natifs Laravel à chaque transition de cycle de vie. Branchez vos listeners dans `EventServiceProvider` ou avec les attributs `#[AsEventListener]` de Laravel 11+.

|Événement                       |Déclenchement                      |Utilisation typique                        |
|--------------------------------|-----------------------------------|-------------------------------------------|
|`SubscriptionCreated`           |Nouvel abonnement créé             |Email de bienvenue, activation des accès   |
|`SubscriptionCanceled`          |Abonnement résilié (fin de période)|Email de rétention, questionnaire de départ|
|`SubscriptionEnteredGracePeriod`|Expiration + grâce activée         |Email de relance de paiement urgent        |
|`SubscriptionExpired`           |Grâce terminée, accès coupé        |Suspension, notification, archivage        |
|`FeatureQuotaReached`           |Quota d’une feature épuisé         |Notification upsell, alerte admin          |

```php
// app/Listeners/SendWelcomeEmail.php

use Vnuswilliams\Subscription\Events\SubscriptionCreated;

class SendWelcomeEmail
{
    public function handle(SubscriptionCreated $event): void
    {
        $subscriber = $event->subscription->subscriber;
        // $subscriber est l'instance Company, User, etc.

        Mail::to($subscriber->email)->send(new WelcomeMail($subscriber));
    }
}
```

```php
// app/Listeners/NotifyQuotaExhausted.php

use Vnuswilliams\Subscription\Events\FeatureQuotaReached;

class NotifyQuotaExhausted
{
    public function handle(FeatureQuotaReached $event): void
    {
        $subscriber  = $event->subscription->subscriber;
        $featureSlug = $event->feature->slug;  // ex: 'max-employees'

        // Envoyer une notification suggérant un upgrade
        $subscriber->notify(new QuotaReachedNotification($featureSlug));
    }
}
```

-----

## Commande Artisan

La commande `subscription:check-lifecycle` parcourt tous les abonnements en base et effectue les transitions de statut manquantes (active → on_grace_period → expired).

Elle est utile pour les utilisateurs qui ne se reconnectent pas souvent : leur abonnement passera en grâce ou expirera même sans qu’ils fassent de requête, et les événements seront émis correctement.

```bash
# Exécution manuelle
php artisan subscription:check-lifecycle
```

Planifiez-la quotidiennement dans `routes/console.php` (Laravel 11+) :

```php
// routes/console.php

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscription:check-lifecycle')->daily();
```

Ou dans `app/Console/Kernel.php` (Laravel 10 et antérieur) :

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('subscription:check-lifecycle')->daily();
}
```

-----

## Recette complète : service applicatif

Publiez le stub fourni par le package, puis adaptez-le à votre domaine :

```bash
php artisan vendor:publish --tag=subscription-stubs
```

Cela génère `app/Services/SubscriptionService.php`. Voici ce qu’il contient et comment l’utiliser :

```php
// app/Services/SubscriptionService.php

use Vnuswilliams\Subscription\SubscriptionManager;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionManager $subscription,
    ) {}

    public function subscribeTo(Company $company, PlanEnum $planEnum): Subscription
    {
        $plan = $this->subscription->resolvePlan($planEnum->value);

        // Logique métier spécifique à votre app :
        // le plan FREE bénéficie d'un essai manuel de 15 jours
        if ($planEnum === PlanEnum::FREE) {
            return $this->subscription->subscribeTo($company, $plan, expiration: now()->addDays(15));
        }

        return $this->subscription->subscribeTo($company, $plan);
    }

    public function canAddEmployee(Company $company): bool
    {
        return $this->subscription->hasActiveSubscription($company)
            && $this->subscription->canConsume($company, FeatureEnum::MAX_EMPLOYEES->value, 1);
    }

    public function consumeEmployeeSlot(Company $company): void
    {
        $this->subscription->consume($company, FeatureEnum::MAX_EMPLOYEES->value, 1);
    }

    public function releaseEmployeeSlot(Company $company): void
    {
        $this->subscription->release($company, FeatureEnum::MAX_EMPLOYEES->value, 1);
    }

    public function remainingEmployeeSlots(Company $company): int
    {
        return $this->subscription->balance($company, FeatureEnum::MAX_EMPLOYEES->value);
    }

    public function currentPlan(Company $company): ?PlanEnum
    {
        $plan = $this->subscription->currentPlan($company);

        return $plan !== null ? PlanEnum::tryFrom($plan->slug) : null;
    }
}
```

Utilisation dans un Controller :

```php
// app/Http/Controllers/EmployeeController.php

class EmployeeController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $company = $request->user()->company;

        if (! $this->subscriptionService->canAddEmployee($company)) {
            return back()->with('error', 'Quota d\'employés atteint.');
        }

        $employee = Employee::create($request->validated());

        $this->subscriptionService->consumeEmployeeSlot($company);

        return redirect()->route('employees.index')
            ->with('success', 'Employé ajouté avec succès.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $company = auth()->user()->company;

        $employee->delete();

        // Libère le slot pour qu'il soit réutilisable
        $this->subscriptionService->releaseEmployeeSlot($company);

        return redirect()->route('employees.index')
            ->with('success', 'Employé supprimé.');
    }
}
```

-----

## Référence API

### Trait `HasSubscriptions`

|Méthode                                        |Retour             |Description                                    |
|-----------------------------------------------|-------------------|-----------------------------------------------|
|`subscription()`                               |`MorphOne`         |Relation Eloquent vers le dernier abonnement   |
|`subscribeTo($plan, $expiration, $immediately, $price)`|`Subscription`     |Souscrit ou switch si abonnement actif existant|
|`switchTo($plan, $immediately, $price)`                |`Subscription`     |Change de plan                                 |
|`renewSubscription()`                          |`Subscription`     |Renouvelle depuis maintenant                   |
|`hasActiveSubscription()`                      |`bool`             |Abonnement valide ? (actif, essai, grâce)      |
|`currentPlan()`                                |`Plan|null`        |Plan actuel                                    |
|`subscriptionExpiresAt()`                      |`Carbon|null`      |Date d’expiration                              |
|`canConsume($slug, $amount)`                   |`bool`             |Quota ou accès booléen disponible ?            |
|`consume($slug, $amount)`                      |`SubscriptionUsage`|Consomme $amount unités                        |
|`release($slug, $amount)`                      |`SubscriptionUsage`|Libère $amount unités                          |
|`balance($slug)`                               |`int`              |Solde restant (PHP_INT_MAX si illimité)        |
|`totalCharges($slug)`                          |`int`              |Total alloué par le plan                       |
|`usedCharges($slug)`                           |`int`              |Quantité consommée                             |

### Modèle `Subscription`

|Méthode            |Retour  |Description                           |
|-------------------|--------|--------------------------------------|
|`isActive()`       |`bool`  |Statut active ET ends_at dans le futur|
|`isOnTrial()`      |`bool`  |trial_ends_at dans le futur           |
|`isOnGracePeriod()`|`bool`  |Dans la fenêtre de grâce              |
|`isCanceled()`     |`bool`  |Résilié (accès encore possible)       |
|`isSuppressed()`   |`bool`  |Supprimé immédiatement                |
|`isExpired()`      |`bool`  |Plus aucun accès                      |
|`hasAccess()`      |`bool`  |Source de vérité globale              |
|`cancel()`         |`static`|Annulation fin de période             |
|`suppress()`       |`static`|Coupure immédiate                     |
|`renew()`          |`static`|Renouvellement depuis maintenant      |

### Enums disponibles

```php
use Vnuswilliams\Subscription\Enums\SubscriptionStatus;
use Vnuswilliams\Subscription\Enums\FeatureType;
use Vnuswilliams\Subscription\Enums\PeriodicityType;

PeriodicityType::Day;    // 'day'
PeriodicityType::Week;   // 'week'
PeriodicityType::Month;  // 'month'
PeriodicityType::Year;   // 'year'

FeatureType::Boolean;    // 'boolean'
FeatureType::Consumable; // 'consumable'

SubscriptionStatus::Active;        // 'active'
SubscriptionStatus::OnTrial;       // 'on_trial'
SubscriptionStatus::OnGracePeriod; // 'on_grace_period'
SubscriptionStatus::Canceled;      // 'canceled'
SubscriptionStatus::Expired;       // 'expired'
```

-----

## Conseils & Bonnes pratiques

**Centralisez vos slugs de features dans un enum.** Une faute de frappe dans `'max-employes'` au lieu de `'max-employees'` retourne silencieusement `false`. Un `FeatureEnum::MAX_EMPLOYEES->value` ne se trompe jamais.

**Toujours vérifier avant de consommer.** Le package ne lève pas d’exception si vous appelez `consume()` alors que le quota est épuisé. La garde `canConsume()` est de votre responsabilité.

**Utilisez `release()` à la suppression des ressources.** Si un utilisateur supprime un employé, libérez le slot. Sinon le compteur reste faussé et l’utilisateur perd une capacité qu’il devrait récupérer.

**Ne pas confondre `cancel()` et `suppress()`.** `cancel()` est la résiliation normale — l’accès est maintenu jusqu’à la fin de la période payée. `suppress()` est la suspension punitive ou administrative — l’accès est coupé immédiatement.

**Injectez `SubscriptionManager` dans vos services, utilisez la Facade dans vos controllers.** Les services ont besoin d’être testables unitairement — évitez la Facade dans les classes que vous testez avec `pest` ou `phpunit`. Dans un Controller ou un Livewire component, la Facade est parfaitement adaptée.

**Gérez les exceptions.** Le package lève des exceptions typées sur les cas d’erreur :

```php
use Vnuswilliams\Subscription\Exceptions\InvalidPlanException;
use Vnuswilliams\Subscription\Exceptions\SubscriptionNotFoundException;
use Vnuswilliams\Subscription\Exceptions\FeatureNotFoundException;

try {
    Subscription::subscribeTo($company, 'plan-inexistant');
} catch (InvalidPlanException $e) {
    // Plan introuvable ou inactif
    Log::warning($e->getMessage());
}
```

**Planifiez la commande `subscription:check-lifecycle` sans faute.** Sans elle, un utilisateur inactif qui n’effectue aucune requête ne verra jamais son abonnement transitionner vers `expired` en base — et les événements `SubscriptionExpired` ne seront jamais émis.

-----

## Licence

Ce package est distribué sous licence [MIT](LICENSE.md).