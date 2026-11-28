# Content Schedule

`mage-os/module-content-schedule` (`MageOS_ContentSchedule`) — a module for Mage-OS / Magento Open Source.

Adds *Publish at* and *Unpublish at* date-time fields to CMS pages and CMS blocks.

- A cron job runs every minute. When a date is reached, the page or block is enabled or disabled, and the date is cleared, so a later manual change is never overridden. If both dates have passed (cron was down), the later one decides.
- Dates are entered in the admin time zone and stored in UTC. Saving a page or block with a date already past shows a warning: it is applied at the next cron run. A value that is not a date is refused.
- The CMS page and block grids show *Publish at* and *Unpublish at* columns, with date-range filters, and two mass actions: *Schedule* sets the same *Publish at* and/or *Unpublish at* on the selected items (a date left empty keeps each item's own), *Cancel schedule* clears both dates.
- *Content > Elements > Scheduled Content* is a grid of every date still to apply, one row per date, soonest first, with filters (date range, action, type, title, identifier, status), paging and an *Edit* link to each page or block.
- *Content > Elements > Content Schedule Log* is a grid of every date applied: when, run by the cron job or the command line, which page or block, publish or unpublish, and the result with the error message. A page or block that keeps failing is logged when it starts failing, not at every run. Entries are deleted after *Keep Log For (Days)*.
- Optional: a disabled CMS page can redirect (301 or 302) to another page instead of answering with a 404.
- Optional: an e-mail summary after each cron run that publishes or unpublishes something, or fails to, and a daily e-mail reminder of the dates of the next 24 hours.
- `bin/magento content-schedule:run` applies the due dates now, as the cron job does, and exits with an error if a page or block could not be saved.

It schedules the enabled/disabled state only. For scheduled content versions, see `mage-os/module-content-versions`.

## Compatibility

Mage-OS 3.6 and 4.x, and Magento Open Source 2.4.9: all share the Magento 2.4.9 base (`magento/framework` ^103.0.9). PHP 8.3 to 8.5. CI runs the check-extension workflow on the currently supported Mage-OS versions (today Mage-OS 3.6.0 with PHP 8.4; Mage-OS 4.0 joins once the check-extension actions know it); developed on Mage-OS 4.0.0 with PHP 8.5. No storefront templates, so the theme does not matter.

| Target | Status |
|---|---|
| Luma, Hyvä Theme | Not applicable: admin only, no storefront rendering besides the redirect of a disabled page, which is a server-side response. |
| Hyvä Checkout, Loki Checkout | Not applicable: the module does not touch the checkout. |
| Elasticsuite | Not applicable: CMS pages and blocks only, no catalog or search. |

The 1.3 admin screens (*Schedule* mass action, *Scheduled Content* grid, *Content Schedule Log*) are written from the Magento source and covered by unit tests and integration tests that only CI runs; they have not been clicked through in a running store yet.

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
| Redirect Disabled Pages | No | Adds *Redirect when disabled* to the CMS page form. While a page is disabled and has a target, its URL redirects to that target, in the store views the page belongs to: a path of the store (`summer-sale`) or a full `http(s)://` URL; anything else, and the page's own URL key, is refused on save. |
| Redirect Type | Permanent (301) | Permanent (301) or Temporary (302). Browsers and search engines remember a 301, so a page that is published again later can stay redirected for visitors who saw it: choose 302 for pages that come back. |
| E-mail a Summary | No | After each cron run that changes something, one e-mail lists the pages and blocks published, unpublished or not saved. A page or block that keeps failing is tried again every minute, but e-mailed once; it is e-mailed again if it fails after having worked. Changes whose e-mail could not be sent (mail server down) are added to the next e-mail (the last 100 at most; all are in the log). |
| Send To | — | Comma-separated addresses. |
| Sender | General Contact | Store e-mail identity used as sender. |
| E-mail Template | Content Schedule Summary | Can be overridden under *Marketing > Email Templates*. |
| E-mail a Daily Reminder | No | Shown when *E-mail a Summary* is on. Every day at 7:00 (time zone of *Stores > Configuration > General > General > Locale Options*), one e-mail to the same addresses lists the dates that will be applied in the next 24 hours; nothing is sent on a day without any. A reminder that could not be sent is tried again every hour, night included, until it is sent, and sent once; it then lists the 24 hours from the time it is sent. |
| Reminder E-mail Template | Content Schedule Reminder | Can be overridden under *Marketing > Email Templates*. |
| Keep Log For (Days) | 90 | Every night at 3:30, entries of *Content Schedule Log* older than this are deleted. 0 keeps them forever. |

The *Scheduled Content* page uses its own ACL resource, *Content > Elements > Scheduled Content*, and the log its own, *Content > Elements > Content Schedule Log*. Both list pages only to roles allowed to manage CMS pages, and blocks only to roles allowed to manage CMS blocks. The *Schedule* and *Cancel schedule* mass actions use the same ACL resources as the core mass actions of each grid.

## Getting started

1. Open a disabled CMS page under *Content > Elements > Pages*.
2. Set *Publish at* to a time a few minutes from now and save.
3. After the next cron run (every minute) the page is enabled and the date is cleared.

![Publish at / Unpublish at on a CMS page](docs/images/cms-page-dates.png)

### Scheduling many pages or blocks at once

1. In *Content > Elements > Pages* (or *Blocks*), select the items and choose *Actions > Schedule*.
2. Enter *Publish at*, *Unpublish at* or both, in the admin time zone, and click *Schedule*.
3. The dates are saved on each selected item; an item that cannot be saved is counted in an error message and logged in `var/log/system.log`.

## Troubleshooting

- **Nothing happens**: Magento cron must run (`bin/magento cron:run` every minute from the system crontab). Check `cron_schedule` for `mageos_content_schedule_apply`. `bin/magento content-schedule:run` applies the due dates without waiting for cron; pages or blocks that cannot be saved are logged.
- **The redirect does not happen**: check that *Redirect Disabled Pages* is on and the page is disabled. A target that is the requested URL itself is never followed (it would loop): the page stays a 404.
- **A republished page still redirects**: the browser remembered a permanent redirect. Set *Redirect Type* to *Temporary (302)* for the next times; nothing on the server can clear a visitor's browser. A 404 served from the full page cache before the target was set stays until that cache entry expires: flush the full page cache.
- **No summary or reminder e-mail**: check *Send To*, and that e-mail sending is enabled (*Stores > Configuration > Advanced > System > Mail Sending Settings*). A failed send is logged. A page or block that cannot be saved is e-mailed once, then only logged (`var/log/system.log`, every minute) until it is fixed. The reminder leaves at 7:00, or at the first hour it can be sent, and lists nothing already due. The e-mails are written in the locale of the default scope (*Stores > Configuration > General > General > Locale Options*).
- **What happened to a page?** *Content > Elements > Content Schedule Log* shows each date applied and each failure, with its message; filter on the title or ID.
- **Page still served as before**: the full page cache is cleaned for the changed page or block; if you use Varnish, check that purging works.

## More

- [CHANGELOG](CHANGELOG.md)
- [Security policy](SECURITY.md)
- [License](LICENSE.txt): Open Software License 3.0. This module contains no code from Adobe Commerce.
