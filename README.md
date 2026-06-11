# Daybreak

Self-hosted security news aggregator (LAMP, PHP 8.3, server-rendered). See
`docs/SPEC.md` for the specification and `docs/IMPLEMENTATION.md` for the build plan.
`CLAUDE.md` is the Claude Code project memory — read it first.

## Quick start
```bash
cp config/.env.example config/.env      # then edit DB + SMTP
# create DB + least-priv user, then:
php migrations/run.php
php bin/fetch.php --force                # populate articles
# point Apache DocumentRoot at public/ (see deploy/apache-vhost.conf)
```

## Cron
```
*/5 * * * * php /srv/vhosts/daybreak.silverday.de/bin/fetch.php
```

## Layout
Main news feed (primary column) + ransomlook widget + CVE/NVD widget (SPEC §11).

Data from ransomlook.io is CC BY 4.0 and is attributed in the UI.
