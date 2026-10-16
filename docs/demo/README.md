# Documentation Demo

A standalone Laravel application for the Filament Kanban documentation. It uses **Filament's default theme**, English labels, an isolated SQLite database, and fictional tasks and team members.

The demo contains an 11-task launch project and a separate 3,000-task dataset. Each column displays three records per page. No production application, application theme, API keys, or external images are required.

## Run Locally

Requirements: PHP 8.4+, Composer, and the PDO SQLite extension.

From the package root:

```bash
cd docs/demo
composer install
php setup.php
php -S 127.0.0.1:8879 -t public router.php
```

Open [the project board](http://127.0.0.1:8879/demo/project-board).

Composer installs the package from the repository root using a local path repository. The demo does not need an `.env` file or a JavaScript build. `setup.php` creates the fictional records if the database is empty and publishes the Filament assets into this demo's own `public` directory.

This is a localhost-only screenshot fixture. It automatically uses a fictional demo user and has a public, disposable application key. Keep the server bound to `127.0.0.1`; do not deploy this fixture as a production application.

## Explore the Board

- **Customize view** switches between Default, Compact, Flat, and Accent layouts, compacts actions into a header menu, and enables or disables drag handles. Flat displays continuous rows with subtle dividers and hover feedback.
- **3,000 task demo** switches to 1,000 records in each of three columns.
- **Filters** search titles and filter priority or assignee using native Filament controls.
- The column **+** buttons and empty-state **Add task** buttons use the same form as the edit action.
- Clicking a card title edits that record. The menu includes edit, complete, and delete actions.
- Drag cards across columns or use the dedicated handles. Changes are saved in the demo's SQLite database.
- Previous and next buttons paginate one column at a time. Enable **Progressive loading** in **Customize view** to automatically load more cards as you scroll down a column; the batch size remains three cards.
- The user menu contains Filament's native light and dark theme controls.

## Reset the Fictional Data

Stop the demo server, remove `database/demo.sqlite`, and run `php setup.php` again. This only resets this demo's database. Sessions and compiled views are stored under this demo's `storage` directory.

## Source Files

| File | Purpose |
| --- | --- |
| [src/ProjectBoard.php](src/ProjectBoard.php) | Board configuration, fields, filters, actions, and presentation controls. |
| [src/DemoPanelProvider.php](src/DemoPanelProvider.php) | A separate panel with the default Filament theme and native top navigation. |
| [setup.php](setup.php) | SQLite schema, fictional seed data, and asset publishing. |
| [src/Task.php](src/Task.php) | Eloquent model for the task cards. |
| [config/database.php](config/database.php) | SQLite connection confined to this demo. |

See [the screenshot notes](../SCREENSHOTS.md) for the states used in the package README.
