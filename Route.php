<?php
use App\GP247\Plugins\ProductRating\Livewire\AdminLivewire;
use App\GP247\Plugins\ProductRating\Livewire\ReviewManager;
use App\GP247\Plugins\ProductRating\Livewire\ReviewReport;
use Illuminate\Support\Facades\Route;

$config = file_get_contents(__DIR__.'/gp247.json');
$config = json_decode($config, true);

if (gp247_extension_check_active($config['configGroup'], $config['configKey'])) {

    // Admin only: the storefront surface is a Livewire component embedded in the
    // product-detail screen, so this plugin owns no public URL of its own.
    //
    // Every screen sits under the same `productrating` segment, which is what the
    // Layer-2 RBAC model authorizes on (http_uri + method, ADR-001) and what
    // Provider.php declares as store-scoped.
    Route::group(
        [
            'prefix' => GP247_ADMIN_PREFIX.'/productrating',
            'middleware' => GP247_ADMIN_MIDDLEWARE,
        ],
        function () {
            // Settings — the target of AppConfig::clickApp() (the "config" button
            // on the Plugins screen), hence the bare index route.
            Route::get('/', AdminLivewire::class)
                ->name('admin_productrating.index');

            // Moderation queue (menu: Catalog → Product reviews).
            Route::get('/review', ReviewManager::class)
                ->name('admin_productrating_review.index');

            // Per-store statistics (menu: Report → Review statistics).
            Route::get('/report', ReviewReport::class)
                ->name('admin_productrating_report.index');
        }
    );
}
