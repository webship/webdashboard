# Web Dashboard

Dashboards built with [Display Builder](https://www.drupal.org/project/display_builder).

Maintained by [Webship](https://www.drupal.org/project/webship). Webship and the
[Website Starter](https://www.drupal.org/project/website_starter) use the
[UI Suite UIkit](https://www.drupal.org/project/ui_suite_uikit) theme, with [UIkit](https://getuikit.com) and
[HTMX](https://htmx.org), on top of Drupal and [Display Builder](https://www.drupal.org/project/display_builder).

Web Dashboard keeps the entity and permission model of the
[Dashboards](https://www.drupal.org/project/dashboards) module, and builds the
dashboards with Display Builder instead of Layout Builder.

## Features

* **Dashboards are configuration.** Each dashboard is a `webdashboard` config
  entity: administrative label, category, weight, "show always in frontend
  theme", and the Display Builder profile and sources. Export and import them
  with the site configuration.
* **Built with Display Builder.** The **Default Dashboard** tab opens the
  builder on the dashboard. Publishing saves the sources to the dashboard.
* **Personalize.** People allowed to personalize a dashboard get a
  **Personalize** action. They build their own copy, saved in their user data,
  and a **Reset to default** action brings the default dashboard back.
* **Permissions per dashboard.** Every dashboard adds a *Can view* and a *Can
  personalize* permission, managed from the dashboard **Permissions** tab.
* **Administration theme.** Dashboards show in the administration theme for
  people allowed to view it, unless a dashboard is set to always show in the
  frontend theme.
* **Toolbar.** A **Dashboards** toolbar tray lists the dashboards the user can
  view, ordered by weight.
* **Dashboard items.** Every dashboard item plugin is a block in the Display
  Builder block library: current user, add content links, error report, node
  statistics, top 404 pages, RSS news, module update status, system info and
  embedded views.
* **Charts.** Dashboard items can draw their table as a Chart.js chart. The
  colormap, transparency and number of colors are set in
  *Configuration > System > Web Dashboard settings*.
* **Dashboard layouts.** Three components lay out a dashboard: *2 and one
  column*, *3 columns* and *2 columns*, the last with a "Reverse columns"
  option.

## Submodules

* **Web Dashboard Comments**: comments per content type, and a last comments
  view.
* **Web Dashboard Statistics**: most visited content, from the Statistics
  module.
* **Web Dashboard Views**: embed views of the last content, and of the content
  of the current user.
* **Web Dashboard Webform**: webform submissions over time.
* **Web Dashboard Matomo**: visits, browsers, countries, operating systems and
  top URLs, from Matomo.

## Install

```bash
composer require drupal/webdashboard
drush en webdashboard -y
```

## Use

1. Go to *Structure > Web Dashboards* and add a dashboard. Pick the Display
   Builder profile to build it with.
2. Open the **Default Dashboard** tab, place a dashboard layout and dashboard
   items, then publish.
3. On the **Permissions** tab, grant *Can view* to the roles that see the
   dashboard.
4. To let people personalize the dashboard, grant *Can personalize*, and the
   permission to use the Display Builder profile of the dashboard.

## Reach the dashboards

* **Dashboard link.** *Dashboard* in the administration menu, at
  `/admin/webdashboard`, leads to the first dashboard, ordered by weight, the
  user can view. The link is hidden from people who can view none. The
  administration theme toolbar shows it, with or without the core Toolbar
  module.
* **After login.** Turn on *Redirect to the dashboard after login* in
  *Configuration > System > Web Dashboard settings* to send people to their
  dashboard when they log in. Off by default. A destination in the login link
  still wins, and one-time login links keep leading to the account form.

## From Layout Builder to Display Builder

| Dashboards (Layout Builder)          | Web Dashboard (Display Builder)                  |
| ------------------------------------ | ------------------------------------------------ |
| `sections` of the dashboard entity   | `profile` and `sources` of the dashboard entity  |
| Default section storage              | `webdashboard` buildable plugin                  |
| User override section storage        | `webdashboard_override` buildable plugin         |
| Dashboard plugins as blocks          | Dashboard item plugins as blocks                 |
| Dashboard layouts                    | Dashboard layout components                      |
| Layout Builder Restrictions          | Display Builder profiles                         |

## Requirements

* Drupal 12
* [Display Builder](https://www.drupal.org/project/display_builder)
* [laminas/laminas-feed](https://github.com/laminas/laminas-feed), for RSS news
