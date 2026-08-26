=== Mediline Partners ===
Contributors: mediline
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium, editable WordPress theme for the Mediline Partner Program.

== Installation ==

1. In WordPress, open Appearance > Themes > Add New > Upload Theme.
2. Upload the supplied Mediline Partners theme ZIP and activate it.
3. Open Appearance > Mediline Content to edit the landing-page content and PAP links.
4. Open Appearance > Languages to add, remove or rename public languages.
5. Use the language tabs in Mediline Content to edit each localized landing/access page.
6. Use Store Templates to edit previews, translations, screenshots and upload the installation ZIP for each storefront.
7. Open Appearance > Store Builder to preview the PAP-embeddable builder and view its secure bootstrap/provision endpoints.
8. Use Partner FAQ to edit, reorder, add/remove entries and maintain their translations.

The theme automatically provides /login/ and /register/ routes plus localized routes such as /fr/, /de/login/ and /it/register/. Forms send data directly to the configured Post Affiliate Pro URLs. WordPress does not store affiliate passwords.

== Multilingual content ==

* EN, FR, DE, SP and IT are enabled by default.
* English is canonical at the root URL; other languages use clean URL prefixes.
* FR, DE, SP and IT include curated starter translations for the fixed landing and access-page content.
* Main content is edited from Appearance > Mediline Content using language tabs.
* Store Template and FAQ edit screens include language-specific fields.
* Empty custom translations safely fall back to the curated starter or English content.
* The header, mobile menu and access pages include a responsive native language switcher.
* Appearance > Languages can add or remove languages without a multilingual plugin.

== Editable content ==

* Hero copy, buttons and metrics
* Navigation labels and hero artwork labels
* Partnership cards
* Five process steps
* Store Templates section copy and all six interactive previews
* Multi-image screenshot galleries with drag-to-reorder controls
* Benefits
* Commission and traffic conditions
* FAQ entries
* Final CTA and footer
* Login and registration page copy
* PAP login, signup and terms URLs
* Support email and custom logo

== Performance ==

* No page-builder dependency
* No jQuery dependency
* Framework-free deferred JavaScript
* Native responsive screenshot slider with keyboard and swipe navigation
* One local production stylesheet
* Local hero and logo assets
* Above-the-fold image preload
* WordPress block assets removed only from the custom landing/access routes

== Notes ==

Store Templates and FAQ defaults are created only on first theme activation. Existing entries are never overwritten by theme updates.

== Changelog ==

= 1.6.3 =

* Replaced the original design-direction demo records with the six production storefront themes: Aeris, Nova/24, Pulse, Bloom, Apotheke and Verde.
* Added each theme's real 1200×900 screenshot as the default card and modal preview while preserving administrator-uploaded galleries.
* Removed the obsolete Mediline Storefront Base demo bundle and automatic fallback assignment.

= 1.6.2 =

* Added explicit PAP/CRM attribution fields to partner registration without collecting login credentials.
* Propagated PAP click-tracking configuration into generated storefront installations.
* Bundled Store Core 1.2.3 with multilingual first/current-touch attribution and the central checkout bridge.

= 1.6.1 =

* Fixed the WordPress CLI container entrypoint so `core`, `theme`, `plugin`, `option`, `rewrite` and Mediline WP-CLI commands execute through `wp` instead of being treated as Linux executables.
* Interrupted one-command installations can continue on the same VPS by rerunning `install.sh` after the CLI fix.

= 1.6.0 =

* Added secure curl-based one-command deployment from Store Builder: fresh Linux VPS -> Docker -> package download -> WordPress -> database -> HTTPS -> Store Core -> initial catalog sync.
* Store Builder now returns a copyable `curl ... | sudo bash` command after package generation, while retaining ZIP download as a manual fallback.
* Added a short-lived bearer bootstrap URL for the generated installer and private generated-package storage under wp-content.
* Added automatic base-package preparation and Docker Engine/Compose installation on supported Linux distributions.
* Generated command can be re-run on the same server after an interrupted installation while the short-lived bootstrap token remains valid.

= 1.5.0 =

* Added the universal one-command Docker installer to every generated storefront package.
* Store Template ZIP uploads are now treated as WordPress storefront themes; Store Builder wraps them in the standard installer automatically.
* Generated packages now contain install.sh, update.sh, status.sh, Docker Compose, Caddy automatic HTTPS, the selected storefront theme, Store Core and a one-time provisioning manifest.
* Installer now provisions WordPress, MariaDB and Caddy, creates the WordPress admin, activates the selected theme and Store Core, configures catalog credentials and runs the first full sync unattended.

= 1.4.0 =

* Connected Store Builder package generation to Mediline Catalog Core.
* Generated packages now receive store-specific Catalog API credentials through the encrypted one-time provisioning payload.
* Bundled Mediline Store Core into every generated storefront package under mediline-system/mediline-store-core.zip.
* Store Builder now blocks package generation when the central Catalog Core is unavailable, preventing broken storefront downloads.
* Documented the storefront installer contract for full first sync and central checkout.

= 1.3.0 =

* Added PAP-embeddable Store Builder at /store-builder/.
* Added secure one-time bootstrap sessions, CSRF protection and PAP-only iframe policy.
* Added installation ZIP uploads and package versions to every Store Template.
* Added personalized package generation with a one-time mediline-installation.json manifest.
* Added one-time encrypted provisioning claims for installer configuration.
* Added an admin integration screen with Builder, bootstrap and provisioning endpoints.

= 1.2.0 =

* Added native multilingual routing and management with EN, FR, DE, SP and IT enabled by default.
* Added curated starter translations for fixed landing and partner-access content.
* Added per-language editors for landing content, Store Templates and FAQ entries.
* Added responsive language switchers to the public header, mobile navigation and access pages.
* Added localized metadata, hreflang links and safe English fallbacks.

= 1.1.0 =

* Added native multi-image screenshot galleries to Store Templates.
* Added drag-to-reorder and individual screenshot removal controls.
* Added screenshot covers and an adaptive modal slider with keyboard and swipe navigation.

= 1.0.0 =

* Initial Mediline Partners WordPress theme.

= 1.5.3 =
* Fix WordPress admin-preview REST authentication by sending a valid X-WP-Nonce to Store Builder package requests.
* Replace the generic WordPress permission error with an explicit expired-session response.
* Redesign Store Builder configuration as a clearer three-step workflow with selected-template preview, improved language controls, secure-access messaging and a sticky build action.
* Improve package generation status, errors and ready/download states.
* Show Catalog Core prerequisite state before package generation instead of failing after submit.

= 1.5.2 =
* Fix Store Template ZIP persistence and remove silent upload failures.
* Prefer persistent wp-content private storage and recover package paths after document-root changes.
* Validate theme ZIPs with WordPress PclZip when PHP ZipArchive is unavailable.
* Add explicit success/error notices for upload, validation, storage and server-limit failures.
* Allow Store Builder package generation through PharData when ZipArchive is unavailable.
* Use `sudo bash install.sh` so installer execution does not depend on preserved ZIP permission bits.

= 1.5.1 =
* Adds a native /terms-conditions/ page in the approved Mediline visual system, including localized routes.
* Terms content is editable per language from Appearance → Mediline Content and seeded from the legacy program conditions.
* Registration now links to the native Terms page instead of the old site.
* Refines the language-selector chevron with a CSS-drawn control.
* Refines the header Login action with a dedicated circular horizontal arrow while keeping it visually secondary to Register.
