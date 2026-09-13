> 🌐 **Language:** [🇻🇳 Tiếng Việt](./readme_vi.md) · 🇬🇧 English (current)

# Product Rating & Review (ProductRating)

## Introduction

This plugin lets your customers rate and review the products on your GP247 store,
and gives you — the shop owner — the tools to moderate and answer those reviews.
It is written for store administrators (no programming needed); the last two
sections add notes for theme designers and developers. By the end you will be able
to install the plugin, know exactly what you can switch on and off, and know who
is allowed to do what with a review.

## Main features

- Customers **must be signed in** to review.
- You choose: **only customers who bought it** may review, or anyone.
- You choose: a new review **waits for approval**, or appears immediately.
- Let customers **attach photos** (on/off, with a limit).
- Rating scale of **5 stars or 10 stars**.
- A **moderation screen** and a **per-shop statistics screen**.
- You **answer publicly** underneath any review.
- Customers can **edit or withdraw** their own review.

## Installing

1. Sign in to the admin area and open the **Plugins** menu.

2. Find the **Product Rating & Review** row and click **Install**.

   If it works, you get a success message and the plugin switches to the enabled
   state.

3. Open a **Terminal** (on Windows, "Command Prompt"), go to your website folder,
   then type this exact line and press Enter:

   ```
   php artisan optimize:clear
   ```

   This clears the cache so the site picks up the new screens. Skip it and
   clicking the new menu may give you a "page not found" error — the single most
   common problem after installing a plugin.

4. Check the result: two new menu entries appear in the admin area:

   - **Catalog → Product reviews** (where you moderate)
   - **Report → Review statistics** (where you read the numbers)

5. Open any product page on your storefront and scroll below the description.
   You will see the **"Ratings & reviews"** block.

Uninstalling removes the review data and both menu entries.

## Settings

Go to **Plugins** and click **Config** on the Product Rating & Review row.

| Setting | Default | What it does |
| --- | --- | --- |
| Purchase required | On | Only customers with an order containing the product may review it. |
| Publish immediately | Off | Off: a new review waits for your approval. On: it appears at once. |
| Allow photos | Off | Let customers attach real photos of the product. |
| Photos per review | 3 | Maximum photos one review may carry. |
| Rating scale | 5 stars | Choose 5 stars or 10 stars. |

If your site runs **several stores**, each one can have its own settings: pick the
store in the selector at the top, then adjust. A store you never adjust simply
follows the shared defaults.

## How customers leave a review

1. The customer opens a product page and scrolls to the **Ratings & reviews** block.
2. If they are not signed in, they see a **Sign in** button instead of the form.
3. They pick a score, optionally write a comment, and click **Submit review**.
4. If you require approval, they are told the review was received and it lands in
   your moderation screen.
5. Afterwards they see their own review again, with an **Edit** and a
   **Remove my review** button.

A review from someone who actually bought the product carries a **"Verified
purchase"** label, so other shoppers trust it more.

## Managing reviews

Go to **Catalog → Product reviews**. You can filter by state (pending / approved /
rejected) and by store, and search by review text or customer name.

Click the **product name** to open that product on your storefront in a new tab.
An approved review also gets a **View on site** link that jumps straight to that
review, so you see it exactly as a shopper does.

Each review offers four actions:

- **Approve** — the review appears on the storefront and counts towards the
  average.
- **Reject** — the review no longer appears publicly. You **must pick a reason**
  from a fixed list (see below).
- **Public reply** — your answer appears directly under that review, visible to
  everyone browsing the product. One reply per review, editable; saving an empty
  body withdraws it.
- **Delete** — removes it for good. **Only a system administrator can do this**;
  shop owners and staff do not see the button.

### Why a shop owner cannot delete reviews

If a shop can delete the reviews that criticise it, its star rating becomes
marketing and shoppers can no longer trust it. Two major regulators now address
this directly:

- **United States (FTC, effective 21 October 2024):** you may not present a review
  section as if it shows everything while quietly holding back reviews **because
  they are low-scoring or critical**. Removal is still fine when the criteria are
  applied **equally to all reviews**, positive or negative.
- **European Union (Directive 2019/2161, applicable 28 May 2022):** bans
  misrepresenting consumer reviews in order to promote a product.

So the plugin is built this way: you can still take a review down, but you must
**state a reason** from a fixed list, and **every decision is logged** (who, when,
why). Your tool for a critical review is the **public reply**, not deletion.

The reject reasons are: spam or fake review · abusive or hateful · contains
someone else's personal data · illegal content · conflict of interest · not about
this product. There is deliberately **no** "low score" reason.

## Statistics

Go to **Report → Review statistics**. The screen shows you:

- Total reviews, how many are published, how many await approval, and the average.
- A comparison table **per shop** (useful when the site has several stores or
  several sellers).
- A score distribution: how many 5-star reviews, 4-star, and so on.
- The most-reviewed products with their own averages.

The average counts **approved reviews only**. A pending or rejected review never
moves the number shoppers see.

## Adding the block to another theme (for theme designers)

The plugin shows the review block automatically on the **GP247Front** theme. If you
use a different theme, open that theme's product-detail file and add this snippet
wherever you want the block:

```blade
@if (function_exists('gp247_product_rating_enabled') && gp247_product_rating_enabled())
    @livewire('gp247-productrating-front::review-box', ['productId' => $product->id], key('product-review-'.$product->id))
@endif
```

To change how it looks without touching the plugin, create a file named
`livewire/productrating_review-box.blade.php` inside your theme folder. The system
prefers your file, and the plugin's original stays untouched on update.

## Putting reviews on a shop page (for developers)

Every review remembers **two** stores: where the customer wrote it, and **the store
that sells the product**. On a single-store site those are the same. On a
marketplace where many sellers share one domain they differ — and the selling store
is the one a shop page must group by.

The ready-made block for a shop page (average, per-score filters, the shop's rated
products and the reviews themselves):

```blade
@livewire('gp247-productrating-front::store-rating-box', ['sellerStoreId' => $store->id])
```

To build your own markup instead, call these functions:

```php
// Headline numbers for a shop: total, average, per-score distribution.
$summary = gp247_product_rating_store_summary($sellerStoreId);

// Which products the shop is rated on, most reviewed first.
$products = gp247_product_rating_store_products($sellerStoreId, limit: 20, offset: 0);

// One call for a whole product grid (no slow repeated lookups).
$map = gp247_product_rating_bulk_summary($productIds, $sellerStoreId);

// Per-product numbers, as used by the product page.
$summary = gp247_product_rating_summary($productId, $storeId);
```

All of them count **approved** reviews only.

## Conditions & rules (know before you act)

### When a customer submits a review

- **They must be signed in** — a hard rule you cannot switch off. Anonymous reviews
  are the front door for spam.
- **A score is required; text alone will not submit** — the score is what produces
  the average, and without it the comment contributes nothing to a shopper who is
  still deciding.
- **The score must fit the chosen scale** (1–5 or 1–10). This is re-checked on the
  server, not only in the browser.
- **The comment is limited to 2,000 characters.**
- **One review per order** — buy twice and you may write two reviews, but a single
  purchase can never become two scores. This is what stops one person from pushing
  a product's rating up or down.
- **With "Purchase required" on**, the customer needs an order containing that
  product. **Cancelled** and **failed** orders do not count — if they did, ordering
  and immediately cancelling would be a free pass to review.
- **Photos:** JPG, PNG and WEBP only, up to 2 MB each, as many as your setting
  allows.

### When a customer edits or withdraws their review

- **An edit goes back to waiting for approval** (unless you turned on "Publish
  immediately") — otherwise an approved review could be rewritten into something
  else that nobody ever looked at.
- **Withdrawing does not free the slot for that order** — the text and photos are
  really deleted, but the order's review slot stays spent. Otherwise
  remove-and-rewrite would be an unlimited way to re-roll a score.

### When you moderate

- **Rejecting requires a reason** from the fixed list; without one the system will
  not take the review down. An empty or invented reason is refused.
- **A public reply is limited to 1,000 characters**, and replying **never changes**
  whether the review is published.
- **Only a system administrator can delete** a review. Shop owners and staff can
  approve, reject and reply.

## Q&A

**Q1: I installed it, but clicking the menu gives a "page not found" error?**

→ You skipped `php artisan optimize:clear` in step 3 of Installing. Run it and reload the page.

**Q2: The review block does not appear on my product page?**

→ Check that the plugin is enabled under the Plugins menu. If your site uses a theme other than GP247Front, add the snippet from "Adding the block to another theme".

**Q3: I want anyone to be able to review, without buying first?**

→ Open the plugin settings and turn off "Purchase required". Each customer then gets one review per product.

**Q4: Why can I not see a delete button?**

→ Because your account is not a system administrator. Shop owners and staff may approve, reject and reply — see "Why a shop owner cannot delete reviews".

**Q5: A 1-star review says something untrue. What can I do?**

→ Use "Public reply" to state the correct facts right under it. If the content matches one of the listed reject reasons (spam, abusive, off-topic…), reject it with that reason. You may not take it down merely for scoring low.

**Q6: A customer buys the same product repeatedly — how many reviews do they get?**

→ One per order. Three purchases earn three reviews, because those are three real experiences.

**Q7: Does a review still waiting for approval drag my star rating down?**

→ No. The average counts approved reviews only.

**Q8: My site has several stores — are the settings shared?**

→ Each store can be configured separately. A store you never configure follows the shared defaults.

**Q9: Will updating the plugin wipe my settings and data?**

→ No. Your choices and every review are kept, and customer photos are stored outside the plugin folder so they are not overwritten either.

**Q10: A customer asks me to delete their review for privacy reasons?**

→ They can click "Remove my review" themselves on the product page; the text and photos are really deleted. If every trace must go, ask a system administrator to use the delete button.

---

<sub>📅 **Last updated:** 2026-09-13 · ✍️ **Author:** GP247</sub>
