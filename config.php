<?php
/**
 * Plugin DEFAULT configuration.
 *
 * IMPORTANT (plugin standard #7 — ADR plugin-manager_extension-update-flow):
 * this file is package-owned and is OVERWRITTEN on 1-click update. It holds
 * DEFAULTS only. Every value the site owner edits lives in `admin_config`
 * (written by the ConfigForm screen, per store), which the update flow
 * preserves. Read the effective value with ProductRating_config().
 *
 * These keys are also the seed rows created on install and by the settings
 * screen's mount(), so an already-installed site never sees an empty form.
 */
return [
    // Only customers who already bought the product may review it.
    'require_purchased' => 1,

    // 0 = a new review waits for admin approval; 1 = it is published at once.
    'auto_approve' => 0,

    // Allow the reviewer to attach photos.
    'allow_image' => 0,

    // Maximum photos per review (only meaningful when allow_image = 1).
    'image_max' => 3,

    // Rating scale: the highest score a reviewer can give (5 or 10).
    'rating_max' => 5,
];
