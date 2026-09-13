<?php
#App\GP247\Plugins\ProductRating\Models\ExtensionModel.php

namespace App\GP247\Plugins\ProductRating\Models;

use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminMenu;
use Illuminate\Support\Facades\Schema;

/**
 * Install/uninstall orchestrator: the plugin's own tables, its admin menu
 * entries and the seed rows for its settings.
 *
 * Tables are created with Schema here rather than through a migrations folder
 * ("Pattern A"): a plugin folder is deleted and recreated by install/uninstall
 * and by 1-click update, so a migrations ledger would record batches for files
 * that are no longer on disk and the next install would skip creating the
 * tables — leaving the screens fatal. Doing it here is re-entrant: every step is
 * guarded, so installing over a half-removed state converges.
 */
class ExtensionModel
{
    /** @var string Reviews table (without prefix). */
    public const TABLE_REVIEW = 'product_review';

    /** @var string Review photos table (without prefix). */
    public const TABLE_REVIEW_IMAGE = 'product_review_image';

    /** @var string Moderation audit trail (without prefix). */
    public const TABLE_REVIEW_LOG = 'product_review_log';

    /**
     * Create the plugin's tables, seed its settings and add its admin menus.
     *
     * @return void
     */
    public function installExtension()
    {
        $this->createTables();
        $this->seedConfig();
        $this->installMenu();
    }

    /**
     * Mirror of installExtension(): remove menus and drop the tables, so an
     * uninstall leaves nothing behind. The admin_config rows are deleted by
     * AppConfig::uninstall() (it owns the rows it inserted).
     *
     * @return void
     */
    public function uninstallExtension()
    {
        $this->uninstallMenu();

        Schema::dropIfExists(GP247_DB_PREFIX . self::TABLE_REVIEW_LOG);
        Schema::dropIfExists(GP247_DB_PREFIX . self::TABLE_REVIEW_IMAGE);
        Schema::dropIfExists(GP247_DB_PREFIX . self::TABLE_REVIEW);
    }

    /**
     * Create the two tables when missing.
     *
     * Column types follow the shop schema: product/customer/order/store ids are
     * all char(36) there (uuid), so a join or a WHERE against them stays on the
     * index instead of forcing a charset/type conversion.
     *
     * @return void
     */
    protected function createTables(): void
    {
        $this->upgradeTables();

        if (!Schema::hasTable(GP247_DB_PREFIX . self::TABLE_REVIEW)) {
            Schema::create(GP247_DB_PREFIX . self::TABLE_REVIEW, function ($table) {
                $table->increments('id');
                // The storefront the review was written on. On a marketplace
                // (one shared domain) this is ROOT for every vendor's product.
                $table->char('store_id', 36)->default(GP247_STORE_ID_ROOT);
                // The store that SELLS the product (shop_product.store_id) — the
                // dimension a vendor/shop page and the per-shop statistics group
                // by. Equal to store_id on a single-store or plain multi-store
                // site; different only on a marketplace, which is exactly the
                // case store_id cannot answer. See gp247_product_rating_store_*().
                $table->char('seller_store_id', 36)->nullable();
                $table->char('product_id', 36);
                $table->char('customer_id', 36);
                // The order this review is the entitlement of. Reviewing is
                // granted PER ORDER, not per product (industry standard): buy
                // twice, review twice — and a deleted review does not hand back
                // a free slot, so a score cannot be re-rolled.
                //
                // NOT NULL with '' meaning "no order behind it" (only possible
                // while require_purchased is off): MySQL allows unlimited NULLs
                // in a unique key, so a nullable column would let an unverified
                // customer review the same product forever.
                $table->char('order_id', 36)->default('');
                // Denormalised display name: the review outlives the account, and
                // the storefront must not have to load a customer row per review.
                $table->string('customer_name', 100)->nullable();
                $table->tinyInteger('rating')->unsigned()->default(0);
                $table->text('content')->nullable();
                // 0 pending / 1 approved / 2 rejected — see ProductReview constants.
                $table->tinyInteger('status')->default(0);
                $table->timestamp('approved_at')->nullable();
                // The reason a review was rejected, from a CLOSED list applied
                // equally to every review regardless of score. Recorded because
                // "we did not like the score" is not a lawful reason to hide a
                // review (FTC 16 CFR 465.7; EU Directive 2019/2161), and an
                // operator handling a dispute needs to see the stated reason.
                $table->string('reject_reason', 32)->nullable();
                $table->string('reject_note', 255)->nullable();
                // The shop's single public answer to this review — the tool a
                // seller is meant to have instead of the power to erase.
                $table->text('reply_content')->nullable();
                $table->timestamp('reply_at')->nullable();
                // char(36), NOT an integer: GP247 ids are strings ("AU-AAAAA"),
                // and an int column silently rejects them with
                // "1366 Incorrect integer value" the first time a real admin
                // replies. Matches admin_user.id.
                $table->char('reply_by', 36)->nullable();
                // The STORE that answered, on a marketplace where the seller is
                // not the site owner: null = the marketplace/shop owner answered,
                // set = that vendor store answered. It also says which namespace
                // reply_by belongs to (admin_user when null, vendor_user when set).
                $table->char('reply_store_id', 36)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
                // Customer-initiated removal is a TOMBSTONE, not an erase: the
                // row keeps the order's review slot spent, so deleting cannot be
                // used to re-roll a score. Only a system administrator's delete
                // removes the row for real.
                $table->softDeletes();

                // The storefront's only query: approved reviews of one product.
                $table->index(['product_id', 'status'], 'idx_review_product_status');
                // The admin list and the storefront filter on this.
                $table->index(['store_id', 'status'], 'idx_review_store_status');
                // The shop/vendor page and the per-shop statistics group by this.
                $table->index(['seller_store_id', 'status'], 'idx_review_seller_status');
                // One review per customer per ORDER per product (fixed rule, not a
                // setting): enforced in the DB so a double submit or a race
                // cannot bypass it. order_id '' is the single unverified slot.
                $table->unique(
                    ['customer_id', 'order_id', 'product_id'],
                    'uniq_review_customer_order_product'
                );
            });
        }

        if (!Schema::hasTable(GP247_DB_PREFIX . self::TABLE_REVIEW_LOG)) {
            Schema::create(GP247_DB_PREFIX . self::TABLE_REVIEW_LOG, function ($table) {
                $table->increments('id');
                $table->integer('review_id')->unsigned();
                // approve | reject | reply | customer_edit | customer_delete | delete
                $table->string('action', 32);
                $table->string('reason_code', 32)->nullable();
                $table->string('note', 255)->nullable();
                // Exactly one of these identifies the actor. Both are char(36)
                // because every GP247 id is a string ("AU-…", "CUS-…").
                // The acting user: an admin_user id, or a vendor_user id when
                // store_id below names the vendor store that acted.
                $table->char('admin_id', 36)->nullable();
                $table->char('customer_id', 36)->nullable();
                // The vendor store that acted, on a marketplace; null = the
                // marketplace/shop owner (or the customer).
                $table->char('store_id', 36)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('review_id', 'idx_review_log_review');
            });
        }

        if (!Schema::hasTable(GP247_DB_PREFIX . self::TABLE_REVIEW_IMAGE)) {
            Schema::create(GP247_DB_PREFIX . self::TABLE_REVIEW_IMAGE, function ($table) {
                $table->increments('id');
                $table->integer('review_id')->unsigned();
                // Path on the "gp247" disk (storage/app/public), never inside the
                // plugin folder — that folder is replaced on update.
                $table->string('image', 255);
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->index('review_id', 'idx_review_image_review');
            });
        }
    }

    /**
     * Add columns introduced after 1.0 to an existing table.
     *
     * Pattern A again: the plugin owns no migrations folder, so every additive
     * change is a guarded ALTER that converges — running it on a fresh install
     * (table absent) or on an already-upgraded one is a no-op.
     *
     * No backfill: every answer written before this column existed came from the
     * marketplace owner, which is exactly what null means.
     *
     * @return void
     */
    protected function upgradeTables(): void
    {
        $table = GP247_DB_PREFIX . self::TABLE_REVIEW;
        if (Schema::hasTable($table) && !Schema::hasColumn($table, 'reply_store_id')) {
            Schema::table($table, function ($t) {
                $t->char('reply_store_id', 36)->nullable()->after('reply_by');
            });
        }

        $log = GP247_DB_PREFIX . self::TABLE_REVIEW_LOG;
        if (Schema::hasTable($log) && !Schema::hasColumn($log, 'store_id')) {
            Schema::table($log, function ($t) {
                $t->char('store_id', 36)->nullable()->after('customer_id');
            });
        }
    }

    /**
     * Seed the settings rows (GLOBAL scope) from config.php defaults.
     *
     * firstOrCreate, so re-installing never overwrites values the site owner
     * already chose for a store that was kept across a reinstall.
     *
     * @return void
     */
    protected function seedConfig(): void
    {
        $defaults = require __DIR__ . '/../config.php';

        foreach ($defaults as $key => $value) {
            AdminConfig::firstOrCreate(
                [
                    'group' => 'ProductRating',
                    'key' => 'productrating_' . $key,
                    'store_id' => GP247_STORE_ID_GLOBAL,
                ],
                [
                    'code' => 'ProductRating_config',
                    'sort' => 0,
                    'value' => $value,
                    'detail' => 'Plugins/ProductRating::lang.admin.' . $key,
                ]
            );
        }
    }

    /**
     * Add the moderation screen under the shop Catalog menu and the statistics
     * screen under the shop Report menu, where an operator looks for them.
     *
     * Guarded by an existence check on the uri so a re-install does not create
     * duplicate rows.
     *
     * @return void
     */
    protected function installMenu(): void
    {
        $catalogId = AdminMenu::where('key', 'ADMIN_SHOP_CATALOG')->value('id');
        $reportId = AdminMenu::where('key', 'ADMIN_SHOP_REPORT')->value('id');

        $menus = [
            [
                'parent_id' => $catalogId ?? 0,
                'title' => 'Plugins/ProductRating::lang.admin.menu_review',
                'icon' => 'far fa-star',
                'uri' => 'route_admin::admin_productrating_review.index',
                'sort' => 90,
            ],
            [
                'parent_id' => $reportId ?? 0,
                'title' => 'Plugins/ProductRating::lang.admin.menu_report',
                'icon' => 'fas fa-chart-bar',
                'uri' => 'route_admin::admin_productrating_report.index',
                'sort' => 90,
            ],
        ];

        foreach ($menus as $menu) {
            if (!AdminMenu::where('uri', $menu['uri'])->exists()) {
                AdminMenu::create($menu);
            }
        }
    }

    /**
     * Remove exactly the menu rows installMenu() added.
     *
     * @return void
     */
    protected function uninstallMenu(): void
    {
        AdminMenu::whereIn('uri', [
            'route_admin::admin_productrating_review.index',
            'route_admin::admin_productrating_report.index',
        ])->delete();
    }
}
