# Changelog

All notable changes to this module are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/).

## [1.3.0] - 2026-11-28

Pages and blocks can be scheduled in bulk, every date applied is logged in the admin, and *Scheduled Content* becomes a grid with filters and paging.

Upgrade: give the roles that should read the log the new ACL resource *Content > Elements > Content Schedule Log*.

### Added

- *Schedule* mass action in the CMS page and block grids: the same *Publish at* and/or *Unpublish at* for all selected items; a date left empty keeps each item's own (ACL: the same resources as the core mass actions).
- *Content > Elements > Content Schedule Log*: every date applied, with when, who ran it (cron job or command line), the page or block, the action and the result with its error message; filters and paging. A page or block that keeps failing is logged once, not every minute.
- *Keep Log For (Days)* setting (*Content Management > Content Schedule*, default 90): a nightly job deletes older log entries; 0 keeps them.

### Changed

- *Content > Elements > Scheduled Content* is now an admin grid: filters on date, action, type, title, identifier and status, sorting, paging, column controls, and an *Edit* link per row.
- Saving many items with a past date shows the "already past" warning once instead of once per item.

### Fixed

- A daily reminder that could not be sent (mail server down) is no longer lost: it is tried again every hour, night included, until it is sent, and sent once. The reminder job now runs every hour; the reminder still leaves at 7:00.
- A summary e-mail that could not be sent is no longer lost: its changes are added to the next summary.
- The lines of the summary and reminder e-mails ("Publish: CMS page …", "published") are in the language of the e-mail, like its subject, instead of English when sent by cron or the command line.
- The error of a page or block that could not be saved is translated in the failure e-mail when Magento has a translation for it, instead of staying in English.
- The subject of the summary e-mail counts changes and failures apart: an e-mail of failures only reads "1 page(s) or block(s) not saved", no longer "1 change(s)". A custom copy of the *Content Schedule* e-mail template keeps its old subject; use the new variable `summary` to get it.
- `bin/magento content-schedule:run` sends the summary e-mail, as the cron job does; it failed with "Required parameter 'theme_dir' was not passed".

## [1.2.0] - 2026-11-24

The redirect of a disabled page can be temporary, a daily e-mail announces the dates of the next 24 hours, and a page that keeps failing is e-mailed once instead of every minute.

### Added

- *Redirect Type* setting (*Content Management > Content Schedule*): the redirect of a disabled page is permanent (301, as before) or temporary (302). Choose 302 for pages that are published again later, since browsers remember a 301.
- *E-mail a Daily Reminder* setting: every day at 7:00, one e-mail lists the dates that will be applied in the next 24 hours. Off by default; it uses the addresses and sender of the summary, and its own template.

### Changed

- A page or block that keeps failing is e-mailed once instead of at every cron run (every minute). It is still tried and logged at each run, and e-mailed again if it fails after having worked.
- A schedule date that is not a valid date now refuses the save, with a message, instead of being cleared silently.
- A page can no longer be saved with its own URL key as *Redirect when disabled*.

### Fixed

- An unexpected error while saving one page or block no longer stops the cron run before the others and the summary e-mail.
- A page or block whose model is replaced by another module is reported as a failure instead of being skipped silently at every run.
- A disabled page whose redirect target is its own URL answers with the 404 instead of redirecting in a loop.

## [1.1.0] - 2026-11-23

### Added

- *Publish at* and *Unpublish at* columns, with date-range filters, in the CMS page and block grids.
- *Cancel schedule* mass action in both grids: clears both dates of the selected items (ACL: the same resources as the core mass actions).
- `bin/magento content-schedule:run` command, which applies the due dates now and exits with an error code when a page or block could not be saved.
- A warning when a page or block is saved with a date already past.
- A note under the date fields: admin time zone, applied by cron within a minute.
- *Content > Elements > Scheduled Content*: the dates still to apply, soonest first (own ACL resource; pages and blocks are listed only to roles that may manage them).
- Optional 301 redirect of a disabled CMS page (*Redirect when disabled* field), turned on in *Content Management > Content Schedule*; only in the page's store views, and the target must be a store path or an http(s) URL.
- Optional e-mail summary of each cron run that publishes, unpublishes or fails to save something.

### Changed

- The cron job runs every minute instead of every 5 minutes.

## [1.0.0] - 2026-11-17

### Added

- *Publish at* and *Unpublish at* date-time fields on CMS pages and CMS blocks, entered in the admin time zone and stored in UTC.
- A cron job (every 5 minutes) that enables or disables the page or block when its date is reached and clears the date; when both dates have passed, the later one decides. A page or block that cannot be saved is logged and skipped.
- Admin labels translated into French, German, Spanish and Italian.
- Supports Mage-OS 3.6 and 4.x / Magento Open Source 2.4.9 (`magento/framework` ^103.0.9), PHP 8.3 to 8.5.
