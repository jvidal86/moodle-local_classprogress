# Development environment: how to work on `local_classprogress`

Written 2026-10-18. **Verified** means checked on this machine today. **Planned** means taken from the `moodle-docker` README and not yet run here; M0 (see `PLAN.md` section 6) turns every "planned" item into "verified".

## 1. Where Moodle runs

| Place | What it is | Use it for | Never use it for |
|---|---|---|---|
| **Local lab**, `http://localhost:8080` (verified) | Docker containers `moodle-webserver-1` and `moodle-db-1` (MariaDB 11). Moodle 4.5.12+, PHP 8.3.32. Code lives in the Docker volume `moodle_moodle_code`. Set up from `~/CFGS/moodle`. | Manual end-to-end check of the finished plugin. | PHPUnit or Behat (their init creates test tables and test data), and any experiment you cannot undo. It holds your restored courses. |
| **Remote lab** on a separate server (address and access in `~/PROJECTS/moodle-lab/README.md`, deliberately kept out of this public repository) | Moodle 4.5 plus MoodlIA, managed with `~/PROJECTS/moodle-lab/bin/lab`. README: no real student data. | Seeing how restored `.mbz` files look. | Plugin development. Its image is immutable, plugins are added by rebuilding it. |
| **Test stack 4.5** (planned) | `moodle-docker`, Moodle `MOODLE_405_STABLE`, PHP 8.1, PostgreSQL 17, web port 8000. | Daily development, PHPUnit, Behat on the minimum supported version. | |
| **Test stack 5.3** (planned) | `moodle-docker`, Moodle `MOODLE_503_STABLE`, PHP 8.3, MariaDB 11.4, web port 8001. | The same checks on the newest LTS; catches 5.x differences (`public/` layout, PHPUnit 11). | |

Rule of thumb: tests and experiments go to the test stacks; the labs are only for looking at the result.

## 2. Your machine (verified 2026-10-18)

| Item | State |
|---|---|
| Docker | 29.9.1, Compose v5.6.0, user is in the `docker` group |
| Registry | `moodlehq/moodle-php-apache:8.1` and `:8.3`, `postgres:17`, `mariadb:11.4`, `selenium/standalone-chrome` reachable |
| Resources | 20 CPUs, 31 GB RAM, 219 GB free disk |
| Host tools | PHP 8.3.6 CLI, Composer 2.10, Node 24, npm 11, git 2.43, `unzip`, `zip`, Python 3.12 with PyYAML and python-docx |
| Free ports | 8000, 8001, 3306, 5432, 4444 (8080 is the local lab, 8081 the VPL jail) |
| Host PHP gaps | no `zip`, `gd`, `pgsql`, `mysql`, `opcache`, `soap`, Xdebug; no phpcs, phpunit or phpmd. **Not a problem** if all PHP runs in the containers. |

Optional, only if you want linters or tests to run directly on the host or in an editor (needs `sudo`, run it yourself):

```bash
sudo apt install php8.3-zip php8.3-gd php8.3-pgsql php8.3-mysql php8.3-xdebug
```

## 3. Repository layout (proposed)

The Marketplace release is built with `git archive` of the repository, so **the project folder becomes the plugin root** and everything that is not part of the plugin is excluded from the zip with `.gitattributes` (`export-ignore`):

```
class-progress-tracking/          = local/classprogress inside Moodle
  version.php  settings.php  README.md  CHANGES.md      (plugin, from M1)
  db/  classes/  templates/  lang/  tests/  ...         (plugin)
  PLAN.md                         export-ignore
  docs/                           export-ignore   (this file, MOODLE_STANDARDS.md, moodle-rules/)
  prototype/  test-data/  tools/  export-ignore   (tests copy test-data into tests/fixtures)
  .github/workflows/ci.yml        export-ignore
```

Run `git init` in the project folder at the start of M0 (it is not a repository yet).

## 4. Create the test stacks (planned, M0)

Base folder for everything outside the project: `~/moodle-dev`. Each stack gets its own copy of `moodle-docker` (its `local.yml` holds that stack's plugin mount) and its own Moodle checkout.

```bash
mkdir -p ~/moodle-dev && cd ~/moodle-dev

# one moodle-docker clone per stack
git clone https://github.com/moodlehq/moodle-docker.git moodle-docker-405
git clone https://github.com/moodlehq/moodle-docker.git moodle-docker-503

# one Moodle checkout per stack (shallow: fast and small)
git clone --depth 1 -b MOODLE_405_STABLE https://github.com/moodle/moodle.git moodle405
git clone --depth 1 -b MOODLE_503_STABLE https://github.com/moodle/moodle.git moodle503
```

Environment per stack (source the right file in each terminal; the variables are documented in the `moodle-docker` README):

```bash
# ~/moodle-dev/env-405.sh
export COMPOSE_PROJECT_NAME=cp405
export MOODLE_DOCKER_WWWROOT=$HOME/moodle-dev/moodle405
export MOODLE_DOCKER_DB=pgsql
export MOODLE_DOCKER_PHP_VERSION=8.1
export MOODLE_DOCKER_WEB_PORT=127.0.0.1:8000
export MOODLE_DOCKER_BROWSER=chrome          # same browser as CI
export PATH=$HOME/moodle-dev/moodle-docker-405/bin:$PATH

# ~/moodle-dev/env-503.sh
export COMPOSE_PROJECT_NAME=cp503
export MOODLE_DOCKER_WWWROOT=$HOME/moodle-dev/moodle503
export MOODLE_DOCKER_DB=mariadb
export MOODLE_DOCKER_PHP_VERSION=8.3
export MOODLE_DOCKER_WEB_PORT=127.0.0.1:8001
export MOODLE_DOCKER_BROWSER=chrome
export PATH=$HOME/moodle-dev/moodle-docker-503/bin:$PATH
```

Mount the plugin with a `local.yml` inside each `moodle-docker` clone (bind mount, not a symlink; Docker does not follow symlinks). The target path differs by layout:

```yaml
# ~/moodle-dev/moodle-docker-405/local.yml
services:
  webserver:
    volumes:
      - "/home/jvidal/PROJECTS/class-progress-tracking:/var/www/html/local/classprogress"

# ~/moodle-dev/moodle-docker-503/local.yml   (5.x keeps plugins under public/)
services:
  webserver:
    volumes:
      - "/home/jvidal/PROJECTS/class-progress-tracking:/var/www/html/public/local/classprogress"
```

Start a stack and install Moodle (4.5 shown; for 5.3 `source env-503.sh` first):

```bash
source ~/moodle-dev/env-405.sh
cd ~/moodle-dev/moodle-docker-405
cp config.docker-template.php $MOODLE_DOCKER_WWWROOT/config.php   # config.php stays at the Moodle root, also on 5.x
moodle-docker-compose up -d
moodle-docker-wait-for-db
moodle-docker-compose exec webserver php admin/cli/install_database.php \
  --agree-license --fullname="Class progress dev" --shortname="cpdev" \
  --summary="Development site" --adminpass="test" --adminemail="admin@example.com"
```

Then open `http://localhost:8000/` (4.5) or `http://localhost:8001/` (5.3), user `admin`, password `test`. These sites are throw-away: no real data, never exposed.

**To confirm in M0 on 5.3:** whether `admin/cli/install_database.php` stays at the Moodle root or moves to `public/admin/cli/` (check with `ls admin/cli public/admin/cli`), and that the plugin appears under `public/local/classprogress` inside the container (`moodle-docker-compose exec webserver ls public/local`). On 5.x the PHPUnit and Behat init scripts are reached as `public/admin/tool/...`.

Stop without losing data: `moodle-docker-compose stop` and later `start`. Destroy a stack: `moodle-docker-compose down` (data gone).

## 5. Daily workflow

1. Edit the plugin in `~/PROJECTS/class-progress-tracking`. The container sees the change at once through the mount.
2. After changing `db/hooks.php`, `db/caches.php`, `db/access.php`, `db/events.php`, `db/install.xml` or `db/upgrade.php`: **raise `$plugin->version`** in `version.php` and run the upgrade, because those files are cached:
   ```bash
   moodle-docker-compose exec webserver php admin/cli/upgrade.php --non-interactive
   moodle-docker-compose exec webserver php admin/cli/purge_caches.php
   ```
3. Run the unit tests:
   ```bash
   # once per stack, and again after adding test classes or generators
   moodle-docker-compose exec webserver php admin/tool/phpunit/cli/init.php        # 5.x: public/admin/tool/phpunit/cli/init.php
   moodle-docker-compose exec webserver vendor/bin/phpunit --testsuite local_classprogress_testsuite
   ```
4. Run Behat (needs the Selenium container the stack starts):
   ```bash
   moodle-docker-compose exec webserver php admin/tool/behat/cli/init.php          # 5.x: public/admin/tool/behat/cli/init.php
   moodle-docker-compose exec -u www-data webserver php admin/tool/behat/cli/run.php --tags=@local_classprogress
   ```
   Failure screenshots appear at `http://localhost:8000/_/faildumps/`.
5. Check the plan parser and test data on the host, no Moodle needed (**verified**, all 14 checks pass):
   ```bash
   cd ~/PROJECTS/class-progress-tracking
   python3 tools/check_plan.py --selftest
   python3 tools/check_traceability.py          # which requirements have tests; --strict M<n> at the end of a phase
   ```
   The test-first loop and what goes in which test layer are in `docs/TESTING_STRATEGY.md`.
6. Style, PHPDoc, savepoints, Mustache, `validate`: run with `moodle-plugin-ci` (details in `docs/moodle-rules/03-testing-ci-and-release.md`, section C). M0 decides where it runs locally (inside the container, or on the host after installing the PHP extensions in section 2); until then the GitHub Actions workflow is the reference.

## 6. Using the existing labs

- **Local lab (8080):** open `http://localhost:8080/`. For the finished plugin, install it by copying the folder into the Moodle code volume and running the upgrade. That procedure is not written yet, and installing anything there needs your go-ahead.
- **Remote lab:** use `~/PROJECTS/moodle-lab/bin/lab` (prints all commands); its README describes login, restores and teardown. Its secrets live on that server and in your keyring, never in this repository.

## 7. Rules for secrets and data

- No passwords, tokens or `.env` files in the repository.
- The test stacks use made-up data. Real plans in `test-data/` contain teaching content only, no student data.
- Do not point the plugin tests at a database that holds real courses.

## 8. When something goes wrong

| Symptom | Likely cause and fix |
|---|---|
| `port is already allocated` | Another stack or the local lab uses that port. Change `MOODLE_DOCKER_WEB_PORT`, then `moodle-docker-compose down && up -d`. |
| Changes to `db/hooks.php` or `access.php` have no effect | Cached. Raise `$plugin->version` and run the upgrade, or purge caches. |
| New test class not found | Re-run `phpunit/cli/init.php` (or `util.php --upgrade`) so the component test suite is rebuilt. |
| Behat is very slow | Do not log in through the dashboard; start scenarios with `I am on the "..." page logged in as "teacher1"` and use generators. |
| `permission denied` on `docker` | Log out and in again so the `docker` group applies. |
| 5.3 shows a blank page or 404 | The document root must be `public/`; the `moodle-php-apache` image selects it itself when that folder exists. Check `moodle-docker-compose exec webserver ls /var/www/html/public`. |
| PHPUnit fails only on 5.3 with a deprecation | Probably annotations versus attributes (PHPUnit 11.5). See `PLAN.md` section 2 and rule A.3 in `docs/moodle-rules/03-testing-ci-and-release.md`. |

## 9. M0 checklist

M0 is followed by review round R0 (`PLAN.md` section 6): every command in this file must have been run and shown to work before M1 starts. Reports go in `docs/reviews/`.

- [ ] `git init` in the project folder, `.gitattributes` with the `export-ignore` lines above
- [ ] Both test stacks start; both Moodle sites install and log in
- [ ] An empty `local_classprogress` (`version.php` + `lang/en/local_classprogress.php`) installs on 4.5 (PHP 8.1) and on 5.3 (PHP 8.3)
- [ ] The plugin path inside the 5.3 container is confirmed (`public/local/classprogress`)
- [ ] `phpunit/cli/init.php` works on both and one trivial test passes
- [ ] First `phpunit --fail-on-warning` on 5.3 settles annotations versus attributes
- [ ] `moodle-plugin-ci` runs somewhere (container, host or Actions) and its `validate` step passes
- [ ] This file updated: every "planned" above becomes "verified", with the real paths and commands
