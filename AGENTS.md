# Repository Guidelines

## Project Structure & Module Organization
- Root: `alt-by-chatgpt-one.php` — plugin bootstrap, hooks, and public entry points.
- Assets: `assets/altgpt.js` — frontend/admin script. Add more assets under `assets/`.
- If adding PHP modules, prefer `inc/` for helpers/classes and include them from the main file.

## Build, Test, and Development Commands
- No compile step required. PHP and JS run as-is.
- PHP lint: `php -l alt-by-chatgpt-one.php` (run for any changed PHP file).
- Local run: place this folder in `wp-content/plugins/` of a local WordPress and activate via Admin › Plugins or `wp plugin activate alt-by-chatgpt-one-context`.
- Debugging: enable WP debug in `wp-config.php` (`define('WP_DEBUG', true);`).

## Coding Style & Naming Conventions
- PHP: 4-space indent, PSR-12-ish layout, early returns, strict comparisons.
- Namespacing: prefer `AltGpt\\...` for new classes; for functions, use a unique prefix like `altgpt_` to avoid collisions.
- JS: 2-space indent, `camelCase` variables/functions, strict `===`.
- Files: use `kebab-case` for assets, `StudlyCaps` for class files if introduced.

## Testing Guidelines
- Current repo has no automated tests. Validate changes by:
  - Activating the plugin locally and exercising affected flows (Admin + frontend).
  - Checking PHP errors (`WP_DEBUG_LOG`) and browser console.
- If adding tests, use WordPress PHPUnit. Place tests under `tests/` (e.g., `tests/TestSomething.php`).

## Commit & Pull Request Guidelines
- Commits: imperative mood, concise scope. Example: "Add nonce check for AJAX save".
- PRs: include a clear description, linked issues, steps to verify, and screenshots/video of UI changes.
- Keep diffs focused; avoid unrelated reformatting.

## Security & Configuration Tips
- Always check capabilities (`current_user_can`), verify nonces, and bail if `! defined('ABSPATH')`).
- Sanitize input (`sanitize_text_field`, `intval`) and escape output (`esc_html`, `esc_attr`).
- Enqueue assets with dependencies and footer loading, e.g.:
  ```php
  wp_enqueue_script(
    'altgpt',
    plugins_url('assets/altgpt.js', __FILE__),
    ['jquery'],
    null,
    true
  );
  ```
