<?php
#App\GP247\Plugins\ProductRating\Livewire\AdminLivewire.php

namespace App\GP247\Plugins\ProductRating\Livewire;

use GP247\Core\AdminShell\Infrastructure\ConfigForm;
use GP247\Core\Models\AdminConfig;

/**
 * Settings screen for the product review plugin, backed by the key/value
 * admin_config table (ADR-005) so the site owner's choices survive a 1-click
 * update — which replaces every file of the plugin, config.php included.
 *
 * Store-scoped: on a multi-store site each store keeps its own review rules and
 * its own on/off state, inheriting the shared (GLOBAL) values until overridden.
 * On a single-store site every store-scope method is a no-op.
 *
 * @aidlc-unit plugin-manager
 * @aidlc-adr plugin-manager_per-store-plugin-config, ADR-005
 */
class AdminLivewire extends ConfigForm
{
    protected ?string $permission = null;

    /**
     * Seed the settings rows for installs made before a key existed, so the form
     * is never missing a row after an update that adds a setting.
     *
     * @return void
     */
    public function mount(): void
    {
        $defaults = require __DIR__ . '/../config.php';

        foreach ($defaults as $key => $value) {
            AdminConfig::firstOrCreate(
                [
                    'group' => $this->group(),
                    'key' => 'productrating_' . $key,
                    'store_id' => $this->storeId(),
                ],
                [
                    'code' => 'ProductRating_config',
                    'sort' => 0,
                    'value' => $value,
                    'detail' => 'Plugins/ProductRating::lang.admin.' . $key,
                ]
            );
        }

        parent::mount();
    }

    /**
     * @return string
     */
    protected function group(): string
    {
        return 'ProductRating';
    }

    /**
     * Opt into per-store overrides (store picker + per-store enable toggle).
     *
     * @return bool
     */
    protected function storeScoped(): bool
    {
        return true;
    }

    /**
     * The plugin's on/off flag key (admin_config group "Plugins"), so a sub-store
     * scope gets the "enable this plugin for this store" toggle.
     *
     * @return string|null
     */
    protected function enableKey(): ?string
    {
        return 'ProductRating';
    }

    /**
     * @return string
     */
    protected function heading(): string
    {
        return trans('Plugins/ProductRating::lang.title');
    }

    /**
     * The settings this screen edits, in the order they are shown.
     *
     * Keys carry the "productrating_" prefix because admin_config.key is a flat
     * namespace shared by every plugin — see gp247_product_rating_config().
     *
     * @return array<int, string>
     */
    protected function keys(): array
    {
        return [
            'productrating_require_purchased',
            'productrating_auto_approve',
            'productrating_allow_image',
            'productrating_image_max',
            'productrating_rating_max',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldTypes(): array
    {
        return [
            'productrating_require_purchased' => 'bool',
            'productrating_auto_approve' => 'bool',
            'productrating_allow_image' => 'bool',
            'productrating_image_max' => 'number',
            'productrating_rating_max' => 'select',
        ];
    }

    /**
     * Dropdown choices. The rating scale is a closed set on purpose: the star
     * widget and the stored ratings of existing reviews both depend on it, so a
     * free-text number would let an operator set 137 and break the screen.
     *
     * @return array<string, array<string, string>>
     */
    protected function fieldOptions(): array
    {
        return [
            'productrating_rating_max' => [
                '5' => trans('Plugins/ProductRating::lang.admin.rating_max_5'),
                '10' => trans('Plugins/ProductRating::lang.admin.rating_max_10'),
            ],
        ];
    }

    /**
     * Short explanations shown beside each label — these settings change what
     * customers are allowed to do, so the consequence must be readable without
     * opening the docs.
     *
     * @return array<string, string>
     */
    protected function fieldHints(): array
    {
        return [
            'productrating_require_purchased' => trans('Plugins/ProductRating::lang.admin.require_purchased_hint'),
            'productrating_auto_approve' => trans('Plugins/ProductRating::lang.admin.auto_approve_hint'),
            'productrating_allow_image' => trans('Plugins/ProductRating::lang.admin.allow_image_hint'),
            'productrating_image_max' => trans('Plugins/ProductRating::lang.admin.image_max_hint'),
            'productrating_rating_max' => trans('Plugins/ProductRating::lang.admin.rating_max_hint'),
        ];
    }

    /**
     * Canonical admin path this screen authorizes against (ADR-001 Layer-2).
     *
     * WHY a method and not the $screenUri property: that property is protected,
     * so Livewire does not carry it across update round-trips — it would be null
     * on every action after mount, and the path would fall back to the shared
     * /livewire/update endpoint instead of this screen. Computed here because
     * GP247_ADMIN_PREFIX is define()d at boot, not at class-compile time.
     *
     * @return string|null
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-adr ADR-001
     */
    protected function authScreenUri(): ?string
    {
        return GP247_ADMIN_PREFIX . '/productrating';
    }
}
