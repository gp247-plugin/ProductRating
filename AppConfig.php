<?php
/**
 * Plugin format 2.1
 */
#App\GP247\Plugins\ProductRating\AppConfig.php
namespace App\GP247\Plugins\ProductRating;

use App\GP247\Plugins\ProductRating\Models\ExtensionModel;
use App\GP247\Plugins\ProductRating\Models\ProductReview;
use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminHome;
use GP247\Core\ExtensionConfigDefault;
use Illuminate\Support\Facades\Schema;
class AppConfig extends ExtensionConfigDefault
{
    public function __construct()
    {
        //Read config from gp247.json
        $config = file_get_contents(__DIR__.'/gp247.json');
        $config = json_decode($config, true);
    	$this->configGroup = $config['configGroup'];
        $this->configKey = $config['configKey'];
        $this->configCode = $config['configCode'] ?? $this->configKey;
        $this->requireCore = $config['requireCore'] ?? [];
        $this->requireComposerPackages = $config['requireComposerPackages'] ?? [];
        $this->requireGp247Extensions = $config['requireGp247Extensions'] ?? [];
        //Path
        $this->appPath = $this->configGroup . '/' . $this->configKey;
        //Language
        $this->title = trans($this->appPath.'::lang.title');
        //Image logo or thumb
        $this->image = $this->appPath.'/'.$config['image'];
        //
        $this->version = $config['version'];
        $this->auth = $config['auth'];
        $this->link = $config['link'];
    }

    public function install()
    {
        $check = AdminConfig::where('key', $this->configKey)
            ->where('group', $this->configGroup)->first();
        if ($check) {
            //Check Plugin key exist
            $return = ['error' => 1, 'msg' =>  gp247_language_render('admin.extension.plugin_exist')];
        } else {
            //Insert plugin to config
            $dataInsert = [
                [
                    'group'  => $this->configGroup,
                    'code'    => $this->configCode,
                    'key'    => $this->configKey,
                    'sort'   => 0,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                    'value'  => self::ON, //Enable extension
                    'detail' => $this->appPath.'::lang.title',
                ],
            ];
            try {
                AdminConfig::insert(
                    $dataInsert
                );
                (new ExtensionModel)->installExtension();
                $return = ['error' => 0, 'msg' => gp247_language_render('admin.extension.install_success')];
            } catch (\Throwable $e) {
                $return = ['error' => 1, 'msg' => $e->getMessage()];
            }
        }

        return $return;
    }

    /**
     * Update hook — the core calls this after replacing the plugin files
     * (ExtensionUpdateManager::update), unconditionally: a plugin without it
     * fatals on "Call to undefined method AppConfig::update()" and the whole
     * update is rolled back.
     *
     * Everything it does is re-entrant, so running it from any older version (or
     * twice) converges: add columns introduced after 1.0 and re-seed settings
     * that firstOrCreate will skip when the site already chose a value.
     *
     * @param string|null $fromVersion Version the site is coming from.
     * @return array{error:int, msg:string}
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seller-reply-contract
     */
    public function update(?string $fromVersion = null)
    {
        try {
            (new ExtensionModel)->installExtension();
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }

        return ['error' => 0, 'msg' => ''];
    }

    public function uninstall()
    {
        //Please delete all values inserted in the installation step
        try {
            (new AdminConfig)
            ->where('key', $this->configKey)
            ->orWhere('code', $this->configKey.'_config')
            ->delete();

            //Admin config home
            AdminHome::where('extension', $this->appPath)->delete();

            (new ExtensionModel)->uninstallExtension();

            $return = ['error' => 0, 'msg' => gp247_language_render('admin.extension.uninstall_success')];
        } catch (\Throwable $e) {
            $return = ['error' => 1, 'msg' => $e->getMessage()];
        }

        return $return;
    }
    
    public function enable()
    {
        $process = (new AdminConfig)
            ->where('group', $this->configGroup)
            ->where('key', $this->configKey)
            ->update(['value' => self::ON]);
        //Admin config home
        AdminHome::where('extension', $this->appPath)->update(['status' => 1]);

        if (!$process) {
            $return = ['error' => 1, 'msg' => gp247_language_render('admin.extension.action_error', ['action' => 'Enable'])];
        }
        $return = ['error' => 0, 'msg' => gp247_language_render('admin.extension.enable_success')];
        return $return;
    }

    public function disable()
    {
        $return = ['error' => 0, 'msg' => ''];
        $process = (new AdminConfig)
            ->where('group', $this->configGroup)
            ->where('key', $this->configKey)
            ->update(['value' => self::OFF]);
        if (!$process) {
            $return = ['error' => 1, 'msg' => gp247_language_render('admin.extension.action_error', ['action' => 'Disable'])];
        }

        //Admin config home
        AdminHome::where('extension', $this->appPath)->update(['status' => 0]);

        return $return;
    }


    /**
     * Drop everything this plugin owns for a store the platform is deleting:
     * its reviews (and, through the model's delete(), their photo files and
     * image rows) and its per-store settings/enable overrides.
     *
     * Uses each()->delete() rather than a bulk delete so ProductReview::delete()
     * still runs — a bulk delete would leave the uploaded photos orphaned in
     * storage forever.
     *
     * @param  mixed $storeId
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-store-remove-cleanup
     */
    public function removeStore($storeId = null)
    {
        if ($storeId === null) {
            return;
        }

        if (Schema::hasTable(GP247_DB_PREFIX . ExtensionModel::TABLE_REVIEW)) {
            ProductReview::where('store_id', $storeId)
                ->each(fn ($review) => $review->delete());
        }

        // Both channels this plugin writes per store: its settings rows and the
        // per-store enable override. Grouped so the OR can never widen past the
        // store being removed.
        AdminConfig::where('store_id', $storeId)
            ->where(function ($query) {
                $query->where('code', $this->configKey . '_config')
                    ->orWhere(function ($enable) {
                        $enable->where('group', $this->configGroup)
                            ->where('key', $this->configKey);
                    });
            })
            ->delete();
    }

    /**
     * Nothing to seed for a new store: the settings inherit from the GLOBAL rows
     * until the site owner overrides them, and reviews are created by customers.
     *
     * @param  mixed $storeId
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-store-setup
     */
    public function setupStore($storeId = null)
    {
        // Intentionally empty — see the docblock above.
    }


    /**
     * Target of the "config" button on the Plugins screen.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-admin-config
     */
    public function clickApp()
    {
        return redirect()->route('admin_productrating.index');
    }

    /**
     * Get info plugin
     *
     * WHY (per-store aware, ADR plugin-manager_per-store-plugin-config): a plugin that
     * declares "storeScope": "store" in gp247.json should read its settings for the
     * EFFECTIVE store, not GLOBAL — use gp247_config($key, gp247_plugin_store_id())
     * (the seam resolves admin session / marketplace checkout / storefront domain), so
     * one plugin serves both a multi-store and a marketplace site unchanged. On a
     * single-store site the seam is ROOT ⇒ falls back to GLOBAL ⇒ identical behaviour.
     *
     * @return  [type]  [return description]
     */
    public function getInfo()
    {
        $arrData = [
            'title' => $this->title,
            'key' => $this->configKey,
            'code' => $this->configCode,
            'image' => $this->image,
            'permission' => self::ALLOW,
            'version' => $this->version,
            'auth' => $this->auth,
            'link' => $this->link,
            'value' => 0, // this return need for plugin shipping
            'appPath' => $this->appPath
        ];

        return $arrData;
    }
}
