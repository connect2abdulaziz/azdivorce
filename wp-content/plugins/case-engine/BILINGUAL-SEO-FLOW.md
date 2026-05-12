# Bilingual (EN/ES) + SEO — Implementation Flow

## Overview

We add **English + Spanish** and **SEO (hreflang, meta)** in this order: prepare the plugin for translation → add the multilingual plugin → translate content and strings → add language switcher → ensure SEO (hreflang, sitemap).

---

## Step 1: Prepare Case Engine for translation

**Goal:** All user-facing text in the plugin can be translated when the site language changes.

1. **Load the plugin text domain**  
   In `case-engine.php`, call `load_plugin_textdomain( 'case-engine', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );` on the `plugins_loaded` action (with priority 0 or 1).  
   This makes WordPress load `languages/case-engine-{locale}.mo` (e.g. `es_ES` for Spanish).

2. **Verify strings are wrapped**  
   Case Engine already uses `__( '...', 'case-engine' )`, `esc_html__( '...', 'case-engine' )`, etc. No change needed unless you find raw English strings.

3. **Create the languages folder**  
   Add `wp-content/plugins/case-engine/languages/` and place translation files there:
   - `case-engine.pot` — template (optional; for generating translations).
   - `case-engine-es_ES.po` and `case-engine-es_ES.mo` — Spanish translations.  
   You can generate the `.pot` with WP-CLI (`wp i18n make-pot`) or Poedit, then create `es_ES` from it. The `.mo` is compiled from the `.po` (Poedit does this on save; or use `msgfmt`).

**Result:** When site language is Spanish, Case Engine’s intake, dashboard, and admin strings use the Spanish .mo file.

---

## Step 2: Install and configure Polylang

**Goal:** Site has two languages (EN default, ES) and a clear URL structure.

1. **Install Polylang**  
   Plugins → Add New → search “Polylang” → Install and Activate.

2. **Run the language setup wizard**  
   - Choose **English** as default language (if not already).  
   - Add **Spanish (Español)**.  
   - Choose URL structure: “The language is set from the directory name in pretty permalinks” (e.g. `yoursite.com/en/`, `yoursite.com/es/`).  
   - Optional: add a language switcher in a menu (see Step 4).

3. **Optional: Polylang for WooCommerce**  
   If you use WooCommerce and want translated products/cart, install “Polylang for WooCommerce”. Not required for intake/dashboard only.

**Result:** Two languages (EN, ES) and URLs like `/en/intake/`, `/es/intake/`.

---

## Step 3: Translate WordPress content (pages)

**Goal:** Key pages exist in both languages and are linked so Polylang can add hreflang.

1. **Identify key pages**  
   At least: Home, Intake (page with `[az_intake]`), Client Dashboard, About, Contact, Services (and any other main nav pages).

2. **For each key page (e.g. Intake):**  
   - Open the page in the editor.  
   - In the sidebar (or language meta box), set language to **English** and save.  
   - Click “+” next to “Spanish” (or “Add new” in Languages) to create the **Spanish translation**.  
   - Edit the Spanish version: translate the title and body (and keep the same shortcode `[az_intake]` on the Spanish intake page).  
   - Save. Polylang links the two as translations of each other.

3. **Client Dashboard**  
   - Create (or use existing) English “Client Dashboard” page with `[az_client_dashboard]`; assign English.  
   - Create Spanish “Panel del cliente” (or similar) with the same shortcode; assign Spanish and link as translation of the English dashboard page.

4. **Menus**  
   Create or edit the main menu: add the **Language switcher** block/widget (Polylang) so users can switch EN/ES. Optionally build a separate menu per language or one menu with both languages.

**Result:** Key pages have EN and ES versions; Polylang knows the pairs and will output hreflang (Step 5).

---

## Step 4: Translate Case Engine strings (Spanish .po/.mo)

**Goal:** Intake flow, dashboard, and admin UI show in Spanish when the current language is Spanish.

1. **Generate .pot (optional but recommended)**  
   From project root:  
   `wp i18n make-pot wp-content/plugins/case-engine wp-content/plugins/case-engine/languages/case-engine.pot --domain=case-engine`  
   Or use Poedit: open “New from POT/PO”, point to plugin folder, domain `case-engine`.

2. **Create Spanish translation**  
   - Copy `case-engine.pot` to `case-engine-es_ES.po`.  
   - Translate all strings to Spanish (Poedit or Loco Translate plugin).  
   - Save; Poedit creates `case-engine-es_ES.mo`. Or compile: `msgfmt -o case-engine-es_ES.mo case-engine-es_ES.po`.  
   - Put `case-engine-es_ES.po` and `case-engine-es_ES.mo` in `plugins/case-engine/languages/`.

3. **How it’s used**  
   When the user visits a Spanish page, WordPress sets locale to `es_ES`. `load_plugin_textdomain` loads `case-engine-es_ES.mo`, so all `__( '...', 'case-engine' )` strings in intake, dashboard, and admin show in Spanish.

**Result:** Intake screens, dashboard labels, and Case Engine admin text appear in Spanish on Spanish pages.

---

## Step 5: SEO — hreflang and meta

**Goal:** Search engines see language variants and correct meta.

1. **hreflang**  
   Polylang adds `<link rel="alternate" hreflang="en" href="...">` and `hreflang="es" href="..."` for posts/pages that have a translation. No extra code if you created translated pages and linked them in Step 3.

2. **Default language**  
   In Polylang → Languages, set English as default. Polylang will use `hreflang="x-default"` for the default language.

3. **Yoast / Rank Math**  
   If you use Yoast SEO or Rank Math, ensure they’re compatible with Polylang (they usually are). Use “Canonical URL” and let Polylang handle hreflang, or use the SEO plugin’s multilingual sitemap if available.

4. **Sitemap**  
   If you use an SEO plugin sitemap, it will typically include both languages. Polylang does not create a sitemap by itself; the SEO plugin’s sitemap will list EN and ES URLs.

**Result:** Each translated page has correct hreflang and default language; sitemap includes both languages if you use an SEO plugin.

---

## Step 6: (Optional) RTL or more languages

- Spanish is LTR; no RTL changes needed.  
- To add more languages later: add the language in Polylang, create translations of pages, and add a new `.po`/`.mo` for Case Engine (e.g. `case-engine-fr_FR.po`).

---

## Order of implementation (summary)

| Order | Task | Where |
|-------|------|--------|
| 1 | Load plugin text domain | `case-engine.php` |
| 2 | Create `languages/` folder; add `.pot` and `es_ES.po`/`.mo` | Case Engine plugin |
| 3 | Install and configure Polylang (EN + ES, URL structure) | WordPress admin |
| 4 | Create/link translated pages (Intake, Dashboard, Home, etc.) | WordPress admin |
| 5 | Add language switcher to menu/header | Theme / Polylang block |
| 6 | Translate Case Engine strings (fill `es_ES.po`, compile `.mo`) | Poedit or Loco |
| 7 | Verify hreflang and SEO plugin compatibility | Front-end / SEO plugin |

---

## Files to add or change (code only)

- **case-engine.php:** Add `load_plugin_textdomain` on `plugins_loaded`.
- **wp-content/plugins/case-engine/languages/:** Add `case-engine.pot`, `case-engine-es_ES.po`, `case-engine-es_ES.mo` (or generate .pot with WP-CLI and create es_ES from it).

Everything else (Polylang settings, creating Spanish pages, menu switcher) is done in the WordPress admin and does not require new code in the repo.
