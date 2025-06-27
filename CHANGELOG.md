# Changes

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [3.8.3] - 2025-06-27

### Fixes

- RpManager->replaceSailor() returns num, not array
- Deprecate `S3Writer::PARAM_HOST_BASE`
- countable() bug around $round->race_order
- Add support compose watch
- Bug when searching sailors
- RP validation and number of crews
- Display sessions of logged-in users only

### Added

- Dead-letter queues for updates (https://github.com/openwebsolns/techscore/issues/12)
- Support for AWS-backed metrics

## [3.6.1] - 2024-03-18

### Added

- Dead-letter queues for updates (https://github.com/openwebsolns/techscore/issues/12)
- Support for AWS-backed metrics

## [3.6] - 2024-03-13

### Removed

- Download RP form function; no need to include any `rpwriter` files
- PDF RP form generation
- `rp_form` table

## [3.5] - 2024-03-05

> This is the first entry in the CHANGELOG.md encapsulating the state of the application as of the
> date. The version numbers used from this point forward are those published in the Docker images.

### Added

- Application version numbers are fixed.
- Docker support
