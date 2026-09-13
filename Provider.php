<?php
/**
 * Provides everything needed for the Extension
 */

 $config = file_get_contents(__DIR__.'/gp247.json');
 $config = json_decode($config, true);
 $extensionPath = $config['configGroup'].'/'.$config['configKey'];

 $this->loadTranslationsFrom(__DIR__.'/Lang', $extensionPath);

 if (gp247_extension_check_active($config['configGroup'], $config['configKey'])) {

     // Declare this plugin's admin screens as store-scoped so the MultiStore Pro
     // StoreFence lets a store-admin reach their own store's reviews/report/config
     // (registry idiom, ADR plugin-manager_per-store-plugin-config). No-op on a
     // site without the Pro fence.
     config(['gp247-config.admin.store_scoped_segments' => array_values(array_unique(array_merge(
         (array) config('gp247-config.admin.store_scoped_segments', []),
         ['productrating']
     )))]);

     $this->loadViewsFrom(__DIR__.'/Views', $extensionPath);

     if (file_exists(__DIR__.'/config.php')) {
         $this->mergeConfigFrom(__DIR__.'/config.php', $extensionPath);
     }

     if (file_exists(__DIR__.'/function.php')) {
         require_once __DIR__.'/function.php';
     }

     // US-PLG-004: register the plugin's Livewire class namespaces so its
     // components resolve without relying on Composer autoload discovery at the
     // host. The component name travels in every livewire/update round-trip, so
     // these must stay registered or interactivity breaks mid-session. Guarded by
     // class_exists so a host without Livewire still boots the plugin cleanly
     // (shared-host support, NFR-AVAIL-002).
     if (class_exists(\Livewire\Livewire::class)) {
         // Admin screens: <livewire:ProductRating::admin-livewire> etc.
         \Livewire\Livewire::addNamespace('ProductRating', classNamespace: 'App\\GP247\\Plugins\\ProductRating\\Livewire');

         // Storefront review box, mounted by the active template's product-detail
         // screen as @livewire('gp247-productrating-front::review-box', [...]).
         \Livewire\Livewire::addNamespace(
             'gp247-productrating-front',
             classNamespace: 'App\\GP247\\Plugins\\ProductRating\\Livewire\\Front',
         );
     }
 }
