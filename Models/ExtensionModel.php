<?php
#App\GP247\Plugins\ProductRating\Models\ExtensionModel.php

namespace App\GP247\Plugins\ProductRating\Models;

use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminMenu;
use Illuminate\Support\Facades\DB;
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
                $table->char('admin_id', 36)->nullable();
                $table->char('customer_id', 36)->nullable();
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
     * Fill seller_store_id for rows written before the column existed, from the
     * store that owns each product.
     *
     * Idempotent (only touches rows that are still null) and chunked, so it is
     * safe to re-run and does not load a large review table into memory.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-seller-dimension
     */
    public function backfillSellerStore(): void
    {
        $reviews = GP247_DB_PREFIX . self::TABLE_REVIEW;
        $products = GP247_DB_PREFIX . 'shop_product';

        if (!Schema::hasTable($reviews) || !Schema::hasTable($products)) {
            return;
        }

        DB::table($reviews)->whereNull('seller_store_id')->orderBy('id')->chunkById(500, function ($rows) use ($reviews, $products) {
            $owners = DB::table($products)
                ->whereIn('id', collect($rows)->pluck('product_id')->unique()->all())
                ->pluck('store_id', 'id');

            foreach ($rows as $row) {
                DB::table($reviews)
                    ->where('id', $row->id)
                    // Fall back to the storefront the review was written on: on
                    // every non-marketplace site the two are the same store.
                    ->update(['seller_store_id' => $owners[$row->product_id] ?? $row->store_id]);
            }
        });
    }

    /**
     * Move the review entitlement from "one per product" to "one per ORDER per
     * product" (1.2), for an install that already has the old unique key.
     *
     * Order of operations matters: the NULLs must become '' BEFORE the column is
     * made NOT NULL, and the old unique key must be dropped before the new one
     * is added or two customers' legacy rows could collide during the swap.
     *
     * Every step is guarded on the current state, so re-running is a no-op.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-per-order-entitlement
     */
    public function upgradeEntitlementToPerOrder(): void
    {
        $table = GP247_DB_PREFIX . self::TABLE_REVIEW;

        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'order_id')) {
            return;
        }

        // '' is the single "no order behind it" slot; NULL would be unlimited,
        // because MySQL does not collapse NULLs in a unique key.
        DB::table($table)->whereNull('order_id')->update(['order_id' => '']);

        if (Schema::hasIndex($table, 'uniq_review_customer_product')) {
            Schema::table($table, function ($table) {
                $table->dropUnique('uniq_review_customer_product');
            });
        }

        if (!Schema::hasIndex($table, 'uniq_review_customer_order_product')) {
            Schema::table($table, function ($table) {
                $table->char('order_id', 36)->default('')->nullable(false)->change();
                $table->unique(
                    ['customer_id', 'order_id', 'product_id'],
                    'uniq_review_customer_order_product'
                );
            });
        }
    }

    /**
     * Add the moderation-accountability columns and the audit log (1.3).
     *
     * Each step is guarded on the current state so re-running is a no-op.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-moderation-accountability
     */
    public function upgradeModerationAccountability(): void
    {
        $table = GP247_DB_PREFIX . self::TABLE_REVIEW;

        if (Schema::hasTable($table)) {
            $missing = array_filter(
                ['reject_reason', 'reject_note', 'reply_content', 'reply_at', 'reply_by', 'deleted_at'],
                fn ($column) => !Schema::hasColumn($table, $column)
            );

            if ($missing !== []) {
                Schema::table($table, function ($table) use ($missing) {
                    if (in_array('reject_reason', $missing, true)) {
                        $table->string('reject_reason', 32)->nullable();
                    }
                    if (in_array('reject_note', $missing, true)) {
                        $table->string('reject_note', 255)->nullable();
                    }
                    if (in_array('reply_content', $missing, true)) {
                        $table->text('reply_content')->nullable();
                    }
                    if (in_array('reply_at', $missing, true)) {
                        $table->timestamp('reply_at')->nullable();
                    }
                    if (in_array('reply_by', $missing, true)) {
                        $table->integer('reply_by')->nullable();
                    }
                    if (in_array('deleted_at', $missing, true)) {
                        $table->softDeletes();
                    }
                });
            }
        }

        // createTables() is re-entrant and only creates what is missing, so it
        // is the one place that knows the log table's shape.
        $this->createTables();
    }

    /**
     * Widen the actor columns from int to char(36) (1.4).
     *
     * GP247 ids are strings ("AU-AAAAA"), so the original int columns rejected
     * the very first real reply with "1366 Incorrect integer value". Any value
     * already stored was an int, so widening is lossless.
     *
     * Guarded on the current column type, so re-running is a no-op.
     *
     * @return void
     *
     * @aidlc-unit plugin-product-rating
     * @aidlc-story US-product-rating-string-actor-ids
     */
    public function upgradeActorIdsToString(): void
    {
        $targets = [
            GP247_DB_PREFIX . self::TABLE_REVIEW => 'reply_by',
            GP247_DB_PREFIX . self::TABLE_REVIEW_LOG => 'admin_id',
        ];

        foreach ($targets as $table => $column) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            if (!$this->columnIsIntegerType($table, $column)) {
                continue;
            }

            Schema::table($table, function ($blueprint) use ($column) {
                $blueprint->char($column, 36)->nullable()->change();
            });
        }
    }

    /**
     * Whether a column is still one of MySQL's integer types.
     *
     * WHY read information_schema rather than trust the version number: an
     * install may have been created by any of the 1.3 builds, so the only
     * reliable question is what the column actually is right now.
     *
     * @param string $table  Table name including the prefix.
     * @param string $column
     * @return bool
     */
    protected function columnIsIntegerType(string $table, string $column): bool
    {
        $type = Schema::getConnection()
            ->getSchemaBuilder()
            ->getColumnType($table, $column);

        return in_array(strtolower((string) $type), ['integer', 'int', 'bigint', 'smallint', 'tinyint'], true);
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
