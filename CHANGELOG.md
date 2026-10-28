# Changes

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [3.9.3] - 2026-10-28

### Fixes

- Public season summary page regatta links use trailing slash

## [3.9.2] - 2026-10-26

### Features

- Support for S3 pages without index.html (when deploying to private bucket + CloudFront)

#### Docker

- Update base image for PHP2Shell
- Single container for all of Techscore
- Add mariadb-client and nano for ECS Exec
- Grant PHP process all memory in task
- Do not expose PHP

#### CDK

- Support for ECS Fargate
- Support for all periodic tasks
- Allow manual DNS updates (to migrate existing applications)
- Add "skip-public-distribution-domain-name" CDK flag

### Fixes

- CDK: add missing permissions (CloudWatch, SES)
- Docker: ensure fresh /tmp directory on every execution
- Always queue e-mail message sending
- Process outbox every 12 minutes instead of once an hour
- Return 403 early for Apache dummy connection requests
- Do not set "Domain" attribute in cookie

### Breaking changes

- Removed S3Writer.conf.local.php fallback

## [3.9.1] - 2026-06-14

### Fixes

- Revert "DBDelegate: match type hints from ArrayIterator" for PHP 7.3.30 compat

## [3.9.0] - 2026-06-13

### Features

#### CDK support

- upload favicon.ico
- bundle src and lib; support MigrateDB.php
- add database; use secrets for password
- add basic Lambda function (and conf.aws-lambda.php)
- upload app static assets
- app CFN distro, too

#### AWS Lambda support

- allow environment overrides in CLI mode
- add lambda-main.php entry point
- parse POST requests; close session
- TSSessionHandler: register only once
- expose Conf::initUser() and re-invoke in Lambda
- use Router like index.php
- script name as first argument when using CLI

#### Docker support

- add StderrMailSender; use it in conf.docker-local.php

#### General

- WS: add linkBack() utility method
- scripts/ExecDbQuery: script to run DB queries
- AbstractScript: allow overriding error stream
- Add HttpRequest, HttpResponse abstraction to avoid raw WS::go methods

### Fixes

- DBDelegate: match type hints from ArrayIterator
- DBQuery: deprecated usage of "${}"
- CSS: larger buttons for finishes on mobile
- CSS: re-indent default.css
- CSS: last row of finish unclickable in mobile layout
- ManualTweakPane: handle combined scoring correctly
- No headers for WS::go methods (multiple pages)
- PageWhiz: whitespace
- AbstractAccountPane: protect against missing ts_role
- DBCondTrue: wrap in parentheses missing
- add RedirectException for control flow
- splat operator not available for assoc arrays
- check-session.js: add responseType="document" to ensure functionality
- fix: cleanup Email_Token methods; enforce order
- RegisterPane: fix multi-token bug
- GlobalSettings: allow unsetting reCAPTCHA
- PasswordRecoveryPane: namespaces; fix multi-token bug
- RpManager: use sailor_season to inactivate roles
- Add REGEXP as possible DBCond.

### Removed

- remove backwards compat check for RegisterPane
- remove unused src/pdflatexd
- remove BurgeePane (all burgees loaded from public site)

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
