# PHP security update — 23 October 2026

PHP's official advisory confirms that PHP 8.4.27 fixes both CVE-2026-109618 and
CVE-2026-109619. They affect the PHP runtime, not just CakePHP dependencies:
https://www.php.net/archive/2026.php#2026-10-21-5

## Render: deployment still required

The Dockerfile now requests the official `php:8.4.27-apache` image and prints
and checks the PHP version during the build. These changes do not patch an
already-running deployment. No commit, push, or deployment was performed.

1. Review and commit/push the changes when ready (never commit `app_local.php`,
   `.env`, or credentials).
2. In the Render web service, choose **Manual Deploy → Clear build cache & deploy**.
3. Check the successful deployment's build logs for `PHP 8.4.27` and confirm that
   deployment, rather than an earlier one, is Live. Check the home page, login,
   and pricing preview. A running-container version check, if shell access is
   available, is `php -v`. Do not publish a `phpinfo()` page.
4. Keep updating this pinned version for subsequent PHP security releases.

Official Docker image listing:
https://github.com/docker-library/official-images/blob/master/library/php

## Local macOS: upgrade not performed

The installed CLI is PHP 8.4.8. Homebrew currently fails with
`unknown or unsupported macOS version: "26.4.1"`; Docker is not installed.
No system packages were changed. First update Homebrew, then install the
supported PHP 8.4 formula (rather than inadvertently changing major versions):

```sh
brew update
brew install php@8.4
/opt/homebrew/opt/php@8.4/bin/php -v
```

If `php@8.4` is already installed, use `brew upgrade php@8.4` instead.
The reported version must be **8.4.27 or a later 8.4 release**. If Homebrew still
rejects macOS after updating, resolve that package-manager error before continuing;
do not spoof the OS version or assume the PHP update succeeded.

Stop the existing local development server, then start it explicitly using the
patched binary, from the project folder (choose an available port):

```sh
/opt/homebrew/opt/php@8.4/bin/php bin/cake.php server -H localhost -p 8765
```

Updating CLI PHP does not automatically update an Apache module or an existing
PHP-FPM process. If another local web server serves this application, configure
it to use the new runtime and restart that service too. Do not expose the old
local runtime to the public internet while upgrading.

Homebrew's macOS upgrade guidance: https://docs.brew.sh/Common-Issues#after-a-macos-upgrade

