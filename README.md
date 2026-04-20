# seekoe.com Static Site

This workspace contains a plain HTML version of the Seekoe website.

The original WordPress-shaped mirror was converted into a static, route-based site with:

- local HTML pages
- local CSS, JavaScript, fonts, and media assets
- a lightweight PHP form endpoint for contact, consultation, and careers submissions

## Project structure

Main public routes:

- `/index.html`
- `/book-a-call/index.html`
- `/careers/index.html`
- `/careers/<job-slug>/index.html`
- `/hire-bookkeeping-virtual-assistant/index.html`
- `/hire-insurance-virtual-assistants/index.html`
- `/hire-real-estate-virtual-assistant/index.html`
- `/hire-virtual-legal-assistant/index.html`
- `/privacy-policy/index.html`
- `/cookie-policy/index.html`

Shared project directories:

- `/assets/vendor/` third-party runtime assets used by the mirrored frontend
- `/assets/styles/` generated page-level CSS
- `/assets/core/` shared core JS and CSS
- `/assets/fonts/` local webfonts and font sources
- `/assets/media/` local images, uploads, and static media
- `/assets/js/` project-specific JavaScript

Important project files:

- `/form-handler.php` email-backed form submission endpoint
- `/robots.txt` crawler rules for the static deployment
- `/sitemap.xml` static sitemap for deployed routes
- `/.prettierrc.json` formatting preferences
- `/.prettierignore` formatting exclusions for bundled assets

## Forms and submissions

The site uses static HTML forms enhanced by:

- `/assets/js/site-enhancements.js`
- `/form-handler.php`

What this setup does:

- intercepts frontend form submissions
- posts them to the local PHP endpoint
- supports careers file uploads
- writes a backup copy of each submission to local storage
- attempts to send notification email

Default form email settings:

- recipient: `info@seekoe.com`
- fallback sender: `no-reply@<current-host>`

Optional environment variables:

- `SEEKOE_FORM_TO_EMAIL`
- `SEEKOE_FORM_FROM_EMAIL`

Runtime submission storage:

- `/storage/form-submissions/`

This folder is generated at runtime and should not be committed.

## Local preview

For visual-only review, opening files directly can work for some pages.

For working form submissions, use a PHP-enabled local server instead of `file://`.

Example:

```bash
php -S localhost:8000
```

Then open:

```text
http://localhost:8000/
```

Important note:

- when opened over `file://`, forms intentionally do not submit
- `site-enhancements.js` shows an error in that case because PHP is unavailable

## Deployment notes

This project is intended to be deployed as a static HTML site plus PHP form handling.

Deployment requirements:

- serve the route folders as normal web pages
- preserve relative asset paths exactly as committed
- support PHP execution for `/form-handler.php`
- allow write access for `/storage/form-submissions/`

SEO and branding details already wired into the project:

- Seekoe titles and descriptions
- Seekoe contact details
- local asset paths instead of remote WordPress asset URLs
- static sitemap and robots rules

## Editing guidance

This codebase contains a mix of:

- project-owned HTML, PHP, and helper JS
- generated Elementor CSS
- mirrored third-party vendor assets

Recommended editing rules:

- prefer editing route pages, `/assets/js/`, and `/form-handler.php`
- avoid manually modifying files under `/assets/vendor/` unless necessary
- avoid broad search-replace operations inside minified vendor bundles

## Formatting

The repo has basic Prettier config, but not every mirrored file is valid enough for strict formatting tools.

Current state:

- project-owned files were beautified where safe
- bundled and mirrored vendor assets are intentionally excluded
- some generated HTML/CSS may be malformed but still required for visual fidelity

If you run formatting again, keep these exclusions in place:

- `/assets/vendor/`
- `/assets/core/`
- `/assets/media/`
- `/assets/fonts/`

## Notes

- There is no build step.
- There is no package-based app runtime.
- This is a static site snapshot adapted for deployment and maintenance.
