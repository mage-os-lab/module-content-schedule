# Changelog

All notable changes to this module are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-17

### Added

- *Publish at* and *Unpublish at* date-time fields on CMS pages and CMS blocks, entered in the admin time zone and stored in UTC.
- A cron job (every 5 minutes) that enables or disables the page or block when its date is reached and clears the date; when both dates have passed, the later one decides. A page or block that cannot be saved is logged and skipped.
- Admin labels translated into French, German, Spanish and Italian.
- Supports Mage-OS 4 / Magento Open Source 2.4.9 (`magento/framework` ^103.0.9), PHP 8.3 to 8.5.
