<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Noms des tables
    |--------------------------------------------------------------------------
    | Personnalisez les noms de tables pour éviter les collisions.
    */
    'tables' => [
        'plans'              => 'plans',
        'plan_features'      => 'plan_features',
        'subscriptions'      => 'subscriptions',
        'subscription_usages' => 'subscription_usages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modèles
    |--------------------------------------------------------------------------
    | Vous pouvez étendre les modèles du package et pointer ici vers les vôtres.
    */
    'models' => [
        'plan'               => \Vnuswilliams\Subscription\Models\Plan::class,
        'plan_feature'       => \Vnuswilliams\Subscription\Models\PlanFeature::class,
        'subscription'       => \Vnuswilliams\Subscription\Models\Subscription::class,
        'subscription_usage' => \Vnuswilliams\Subscription\Models\SubscriptionUsage::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Type de clé du modèle souscripteur
    |--------------------------------------------------------------------------
    | Utilisé par la migration subscriptions pour créer subscriber_id.
    | Valeurs supportées : id (entier), uuid, ulid.
    */
    'subscriber_key_type' => env('SUBSCRIPTIONS_SUBSCRIBER_KEY_TYPE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | Colonne prix
    |--------------------------------------------------------------------------
    | Decimal supporte les prix entiers (ex: 100) et décimaux (ex: 19.99).
    */
    'price' => [
        'precision' => 12,
        'scale'     => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Période de grâce par défaut (en jours)
    |--------------------------------------------------------------------------
    | Appliqué uniquement si le Plan n'a pas de grace_days explicite.
    */
    'grace_days' => 0,

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'alias' => 'subscribed',
    ],

];
