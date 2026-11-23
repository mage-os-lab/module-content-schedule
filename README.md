# Content Schedule

`mage-os/module-content-schedule` (`MageOS_ContentSchedule`) — a module for Mage-OS / Magento Open Source.

Adds *Publish at* and *Unpublish at* date-time fields to CMS pages and CMS blocks.

- A cron job runs every minute. When a date is reached, the page or block is enabled or disabled, and the date is cleared, so a later manual change is never overridden. If both dates have passed (cron was down), the later one decides.
- Dates are entered in the admin time zone and stored in UTC. Saving a page or block with a date already past shows a warning: it is applied at the next cron run.
- The CMS page and block grids show *Publish at* and *Unpublish at* columns, with date-range filters, and a *Cancel schedule* mass action that clears both dates of the selected items.
- *Content > Elements > Scheduled Content* lists every date still to apply, soonest first, with a link to each page or block.
- Optional: a disabled CMS page can redirect (301) to another page instead of answering with a 404.
- Optional: an e-mail summary after each cron run that publishes or unpublishes something, or fails to.
- `bin/magento content-schedule:run` applies the due dates now, as the cron job does, and exits with an error if a page or block could not be saved.

It schedules the enabled/disabled state only. For scheduled content versions, see `mage-os/module-content-versions`.

## Compatibility

Mage-OS 3.6 and 4.x, and Magento Open Source 2.4.9: all share the Magento 2.4.9 base (`magento/framework` ^103.0.9). PHP 8.3 to 8.5. CI runs the check-extension workflow on the currently supported Mage-OS versions (today Mage-OS 3.6.0 with PHP 8.4; Mage-OS 4.0 joins once the check-extension actions know it); developed on Mage-OS 4.0.0 with PHP 8.5. No storefront templates, so the theme does not matter.

## Installation

```bash
composer config repositories.mageos-content-schedule vcs https://github.com/mage-os-lab/module-content-schedule
composer require mage-os/module-content-schedule
bin/magento module:enable MageOS_ContentSchedule
bin/magento setup:upgrade
```

## Configuration

The date fields need no configuration: they appear on the edit forms of CMS pages and CMS blocks.

The options are under *Stores > Configuration > General > Content Management > Content Schedule* (default scope only):

| Setting | Default | Effect |
|---|---|---|
| Redirect Disabled Pages | No | Adds *Redirect when disabled* to the CMS page form. While a page is disabled and has a target, its URL answers with a 301 to that target, in the store views the page belongs to: a path of the store (`summer-sale`) or a full `http(s)://` URL; anything else is refused on save. |
| E-mail a Summary | No | After each cron run that changes something, one e-mail lists the pages and blocks published, unpublished or not saved. |
| Send To | — | Comma-separated addresses. |
| Sender | General Contact | Store e-mail identity used as sender. |
| E-mail Template | Content Schedule Summary | Can be overridden under *Marketing > Email Templates*. |

The *Scheduled Content* page uses its own ACL resource, *Content > Elements > Scheduled Content*. It lists pages only to roles allowed to manage CMS pages, and blocks only to roles allowed to manage CMS blocks.

## Getting started

1. Open a disabled CMS page under *Content > Elements > Pages*.
2. Set *Publish at* to a time a few minutes from now and save.
3. After the next cron run (every minute) the page is enabled and the date is cleared.

![Publish at / Unpublish at on a CMS page](docs/images/cms-page-dates.png)

## Troubleshooting

- **Nothing happens**: Magento cron must run (`bin/magento cron:run` every minute from the system crontab). Check `cron_schedule` for `mageos_content_schedule_apply`. `bin/magento content-schedule:run` applies the due dates without waiting for cron; pages or blocks that cannot be saved are logged.
- **The redirect does not happen**: check that *Redirect Disabled Pages* is on and the page is disabled. A 404 served from the full page cache before the target was set stays until that cache entry expires: flush the full page cache.
- **No summary e-mail**: check *Send To*, and that e-mail sending is enabled (*Stores > Configuration > Advanced > System > Mail Sending Settings*). A failed send is logged.
- **Page still served as before**: the full page cache is cleaned for the changed page or block; if you use Varnish, check that purging works.

## More

- [CHANGELOG](CHANGELOG.md)
- [Security policy](SECURITY.md)
- [License](LICENSE.txt): Open Software License 3.0. This module contains no code from Adobe Commerce.
