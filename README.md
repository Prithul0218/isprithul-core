# isprithul core

WordPress plugin for [isprithul.me](https://isprithul.me): the projects, every homepage text, the
“Start a project” form and a one-time starter-content import. It pairs with the
[isprithul theme](https://github.com/Prithul0218/isprithul-theme), which draws the homepage.

Requires WordPress 6.5+ and PHP 8.0+.

## Install

With **Git Updater** (Settings › Git Updater › Install Plugin):

| Field | Value |
|---|---|
| Plugin URI | `https://github.com/Prithul0218/isprithul-core` |
| Repository Branch | `main` |
| Remote Repository Host | GitHub |

Then activate it under Plugins. Later versions show up as normal plugin updates whenever the
`Version` in `isprithul-core.php` goes up.

## What it adds to the admin

- **Projects**: one entry per project card. Drag rows to change the homepage order. Draft = hidden.
  In the Photos box the first photo is the cover; drag to reorder.
- **Site content**: every homepage text, top to bottom, in one form. The availability switch
  (the D1 LED) is also in the admin bar on every page: `● Taking projects` / `○ Booked`.
- **Briefs**: everything sent through the “Start a project” form. Each one is also emailed to you
  with Reply-To set to the sender.
- **Site content › Starter content**: creates the 13 projects and downloads their photos (and the
  About portrait) once. Safe to run again; it skips what already exists.

Saving anything here also empties the LiteSpeed page cache, so changes are live right away.

## REST API (administrators only, except the form)

| Route | |
|---|---|
| `GET /wp-json/isprithul/v1/content` | all Site content fields as they appear in the form |
| `POST /wp-json/isprithul/v1/content` | change fields: `{"values": {"hero.title": "…"}}` |
| `GET` / `POST /wp-json/isprithul/v1/import` | import status / run one import step |
| `POST /wp-json/isprithul/v1/brief` | the public “Start a project” form |
| `/wp-json/wp/v2/isp_project` | projects, with their `isp_*` fields under `meta` |

## Files

| Path | What it does |
|---|---|
| `isprithul-core.php` | plugin header (version, Git Updater), loads the rest |
| `inc/content.php` | Site content: fields, defaults, saving, the data the theme reads |
| `inc/settings-page.php` | the Site content screen |
| `inc/projects.php` | the Projects post type, its edit screen and drag-to-reorder |
| `inc/brief.php` | the form endpoint, the Briefs list, the email |
| `inc/importer.php` | starter content (projects + photos) |
| `data/defaults.json` | default homepage texts |
| `data/starter.json` | the starter projects and their photo list |
| `assets/` | admin styles and scripts |
| `tools/build-data.js` | rebuilds the two data files from the homepage mockup (not shipped) |

The `media` branch holds the starter photos (max 1600 px, camera and location data removed).
It is not part of the plugin package.
