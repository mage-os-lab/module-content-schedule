# Content Schedule

`mage-os/module-content-schedule` (`MageOS_ContentSchedule`) — a module for Mage-OS / Magento Open Source.

Adds *Publish at* and *Unpublish at* date-time fields to CMS pages and CMS blocks.

- A cron job runs every 5 minutes. When a date is reached, the page or block is enabled or disabled, and the date is cleared, so a later manual change is never overridden. If both dates have passed (cron was down), the later one decides.
- Dates are entered in the admin time zone and stored in UTC.

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

No configuration. The fields appear on the edit forms of CMS pages and CMS blocks.

## Getting started

1. Open a disabled CMS page under *Content > Elements > Pages*.
2. Set *Publish at* to a time a few minutes from now and save.
3. After the next cron run (every 5 minutes) the page is enabled and the date is cleared.

![Publish at / Unpublish at on a CMS page](docs/images/cms-page-dates.png)

## Troubleshooting

- **Nothing happens**: Magento cron must run (`bin/magento cron:run` every minute from the system crontab). Check `cron_schedule` for `mageos_content_schedule_apply`.
- **Page still served as before**: the full page cache is cleaned for the changed page or block; if you use Varnish, check that purging works.

## More

- [CHANGELOG](CHANGELOG.md)
- [Security policy](SECURITY.md)
- [License](LICENSE.txt): Open Software License 3.0. This module contains no code from Adobe Commerce.
