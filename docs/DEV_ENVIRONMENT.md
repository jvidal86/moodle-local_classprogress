# Development environment: how to work on `local_classprogress`

Written 2026-10-18, updated the same day by M0. **Verified** means run on this machine and seen to work, with the result noted. **Path checked** means the file exists where stated but the command has not been run yet (it has nothing to act on before M1). Nothing in this file is "planned" any more.

## 1. Where Moodle runs

| Place | What it is | Use it for | Never use it for |
|---|---|---|---|
| **Local lab**, `http://localhost:8080` (verified) | Docker containers `moodle-webserver-1` and `moodle-db-1` (MariaDB 11). Moodle 4.5.12+, PHP 8.3.32. Code lives in the Docker volume `moodle_moodle_code`. Set up from `~/CFGS/moodle`. | Manual end-to-end check of the finished plugin. | PHPUnit or Behat (their init creates test tables and test data), and any experiment you cannot undo. It holds your restored courses. |
| **Remote lab** on a separate server (address and access in `~/PROJECTS/moodle-lab/README.md`, deliberately kept out of this public repository) | Moodle 4.5 plus MoodlIA, managed with `~/PROJECTS/moodle-lab/bin/lab`. README: no real student data. | Seeing how restored `.mbz` files look. | Plugin development. Its image is immutable, plugins are added by rebuilding it. |
| **Test stack 4.5**, `http://localhost:8000` (verified) | `moodle-docker`, compose project `cp405`. Moodle 4.5.15+ (Build: 20261005, `MOODLE_405_STABLE@215f4438`), PHP 8.1.34, PostgreSQL 17.11, PHPUnit 9.6.34. | Daily development, PHPUnit, Behat on the minimum supported version. | |
| **Test stack 5.3**, `http://localhost:8001` (verified) | `moodle-docker`, compose project `cp503`. Moodle 5.3+ (Build: 20261013, `MOODLE_503_STABLE@344c92d5b`), PHP 8.3.35, MariaDB 11.4.13, PHPUnit 11.5.56. | The same checks on the newest LTS; catches 5.x differences (`public/` layout, PHPUnit 11). | |

Rule of thumb: tests and experiments go to the test stacks; the labs are only for looking at the result.

## 2. Your machine (verified 2026-10-18)

| Item | State |
|---|---|
| Docker | 29.9.1, Compose v5.6.0, user is in the `docker` group |
| Registry | `moodlehq/moodle-php-apache:8.1` and `:8.3`, `postgres:17`, `mariadb:11.4`, `selenium/standalone-chrome` reachable |
| Resources | 20 CPUs, 31 GB RAM, 219 GB free disk |
| Host tools | PHP 8.3.6 CLI, Composer 2.10, Node 24, npm 11, git 2.43, `unzip`, `zip`, Python 3.12 with PyYAML and python-docx |
| Free ports | Before M0: 8000, 8001, 3306, 5432, 4444 (8080 is the local lab, 8081 the VPL jail). Since M0, 8000 and 8001 are the test stacks (bound to 127.0.0.1); the database and Selenium ports stay unpublished. |
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

The project folder is already a git repository (branch `main`) and `.gitattributes` holds the `export-ignore` lines. Verified: `git archive HEAD | tar -t` lists only plugin paths (at the first commit, just `tests/fixtures/`); `PLAN.md`, `docs/`, `prototype/`, `test-data/`, `tools/`, `.github/`, `.gitattributes` and `.gitignore` are left out.

## 4. Create the test stacks (verified 2026-10-18)

Base folder for everything outside the project: `~/moodle-dev`. Each stack gets its own copy of `moodle-docker` (its `local.yml` holds that stack's plugin mount) and its own Moodle checkout. What it holds after M0:

```
~/moodle-dev/
  env-405.sh  env-503.sh              stack variables (below)
  moodle-docker-405/  moodle-docker-503/   moodlehq/moodle-docker@f4c2324, each with its own local.yml
  moodle405/  moodle503/              Moodle checkouts (about 440 MB and 700 MB)
  ci/                                 moodle-plugin-ci 4.5.11 for local checks (section 5, step 6)
```

```bash
mkdir -p ~/moodle-dev && cd ~/moodle-dev

# one moodle-docker clone per stack
git clone https://github.com/moodlehq/moodle-docker.git moodle-docker-405
git clone https://github.com/moodlehq/moodle-docker.git moodle-docker-503

# one Moodle checkout per stack (shallow: fast and small)
git clone --depth 1 -b MOODLE_405_STABLE https://github.com/moodle/moodle.git moodle405
git clone --depth 1 -b MOODLE_503_STABLE https://github.com/moodle/moodle.git moodle503
```

Each clone took under a minute. Environment per stack (source the right file in each terminal; the variables are documented in the `moodle-docker` README). These two files exist with exactly this content:

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

Mount the plugin with a `local.yml` inside each `moodle-docker` clone (bind mount, not a symlink; Docker does not follow symlinks). The target path differs by layout. The Selenium container gets the same mount read-only, because `moodle-docker` mounts the Moodle code into Selenium too and Behat file uploads (the `.md` plan upload, M2) read the file from there:

```yaml
# ~/moodle-dev/moodle-docker-405/local.yml
services:
  webserver:
    volumes:
      - "/home/jvidal/PROJECTS/class-progress-tracking:/var/www/html/local/classprogress"
  selenium:
    volumes:
      - "/home/jvidal/PROJECTS/class-progress-tracking:/var/www/html/local/classprogress:ro"

# ~/moodle-dev/moodle-docker-503/local.yml   (5.x keeps plugins under public/)
services:
  webserver:
    volumes:
      - "/home/jvidal/PROJECTS/class-progress-tracking:/var/www/html/public/local/classprogress"
  selenium:
    volumes:
      - "/home/jvidal/PROJECTS/class-progress-tracking:/var/www/html/public/local/classprogress:ro"
```

Every `moodle-docker-compose` call prints `Including local options from .../local.yml`, which confirms the file is picked up.

Start a stack and install Moodle (4.5 shown; for 5.3 use `env-503.sh`, `moodle-docker-503` and `public/local`). The commands are the same on both versions, including the `admin/cli/` path:

```bash
source ~/moodle-dev/env-405.sh
cd ~/moodle-dev/moodle-docker-405
mkdir -p $MOODLE_DOCKER_WWWROOT/local/classprogress                # 5.3: $MOODLE_DOCKER_WWWROOT/public/local/classprogress
cp config.docker-template.php $MOODLE_DOCKER_WWWROOT/config.php   # config.php stays at the Moodle root, also on 5.x
moodle-docker-compose up -d
moodle-docker-wait-for-db
moodle-docker-compose exec webserver php admin/cli/install_database.php \
  --agree-license --fullname="Class progress dev" --shortname="cpdev" \
  --summary="Development site" --adminpass="test" --adminemail="admin@example.com"
```

The `mkdir` creates the mount point as your user; without it Docker creates an empty root-owned folder there. In scripts and other shells without a terminal, write `exec -T` instead of `exec`. Results on 2026-10-18: `up -d` starts five containers per stack (`webserver`, `db`, `selenium`, `mailpit`, `exttests`, named `cp405-*` or `cp503-*`; only the web port is published, on 127.0.0.1); `install_database.php` ends with `Installation completed successfully.` after about 1.5 minutes and installs `local_classprogress` with the rest (`-->local_classprogress` in its output).

Then open `http://localhost:8000/` (4.5) or `http://localhost:8001/` (5.3), user `admin`, password `test` (verified: both reach the Dashboard, and `admin/plugins.php` lists "Class progress"). These sites are throw-away: no real data, never exposed.

Check the plugin from the command line (verified, both stacks print `2026101800`):

```bash
moodle-docker-compose exec webserver php admin/cli/cfg.php --component=local_classprogress --name=version
```

**Confirmed on 5.3** (these were the open M0 questions):

| Question | Answer on `MOODLE_503_STABLE` |
|---|---|
| Where are the admin CLI scripts? | At the **root**, as on 4.5: `admin/cli/install_database.php`, `upgrade.php`, `purge_caches.php`, `cfg.php`. There is no `public/admin/cli/`. The root `admin/` folder holds nothing but `cli/`. |
| Where is the plugin? | `/var/www/html/public/local/classprogress` (`core_component::get_component_directory()`); `$CFG->dirroot` is `/var/www/html/public`. |
| Document root | `/var/www/html/public`, chosen by the image itself: the webserver log says `Detected /public directory. Using /var/www/html/public` (`docker logs cp503-webserver-1`). |
| PHPUnit and Behat scripts | `public/admin/tool/phpunit/cli/init.php` and `util.php`; `public/admin/tool/behat/cli/init.php`, `run.php` and `util.php`. |
| `config.php`, `vendor/`, `phpunit.xml` | At the root: `config.php` (copied template, it loads `lib/setup.php`, a root shim that loads `public/lib/setup.php`), `vendor/bin/phpunit`, and the `phpunit.xml` that init writes. |

Stop without losing data: `moodle-docker-compose stop` and later `start`. Destroy a stack: `moodle-docker-compose down` (data gone; add `-v` to remove the database volume too). Both act only on the compose project named by `COMPOSE_PROJECT_NAME` (`cp405` or `cp503`), so **always source the stack's `env-*.sh` first**. Without it the wrapper stops with `Error: $MOODLE_DOCKER_WWWROOT is not set or not an existing directory`; never run plain `docker compose down` or any `prune` here, since the local lab (`moodle` project) and other projects share the same Docker. `stop` and `start` verified on 5.3: afterwards the login and the PHPUnit run work without a new init. `down` has not been run yet.

**Where the data lives** (from `docker inspect`; the recreate case has not been tried): the database is in an anonymous Docker volume of the `db` container, but `moodledata`, `phpunitdata` and `behatdata` live inside the `webserver` container itself (no volume). Anything that recreates that container (a change to `local.yml` or to a variable in `env-*.sh`, followed by `up -d`) loses them while the database survives, and the site breaks. With throw-away sites the fix is to start again: `down -v`, `up -d`, `install_database.php`, then the PHPUnit and Behat init.

## 5. Daily workflow

1. Edit the plugin in `~/PROJECTS/class-progress-tracking`. The container sees the change at once through the mount.
2. After changing `db/hooks.php`, `db/caches.php`, `db/access.php`, `db/events.php`, `db/install.xml` or `db/upgrade.php`: **raise `$plugin->version`** in `version.php` and run the upgrade, because those files are cached (path checked on both versions, root `admin/cli/`; not run yet, there is nothing to upgrade before M1):
   ```bash
   moodle-docker-compose exec webserver php admin/cli/upgrade.php --non-interactive
   moodle-docker-compose exec webserver php admin/cli/purge_caches.php
   ```
3. Run the unit tests (verified on both stacks):
   ```bash
   # once per stack, and again after adding test classes or generators
   moodle-docker-compose exec webserver php admin/tool/phpunit/cli/init.php        # 5.x: public/admin/tool/phpunit/cli/init.php
   moodle-docker-compose exec webserver vendor/bin/phpunit --fail-on-warning --testsuite local_classprogress_testsuite
   # one file: 4.5 local/classprogress/tests/x_test.php, 5.3 public/local/classprogress/tests/x_test.php
   ```
   `init.php` takes about 1.5 minutes on 4.5 and 2 minutes on 5.3 and ends with `PHPUnit test environment setup complete.` It writes `phpunit.xml` at the Moodle root with a `local_classprogress_testsuite` over `[public/]local/classprogress/tests` (excluding `tests/fixtures` and `tests/classes`). Result with `tests/installation_test.php` (2 tests, 7 assertions):
   - **4.5** (PHPUnit 9.6.34): `OK (2 tests, 7 assertions)`, exit 0.
   - **5.3** (PHPUnit 11.5.56): `OK, but there were issues! Tests: 2, Assertions: 7, PHPUnit Deprecations: 1.`, **exit 0**. With `--display-phpunit-deprecations` the message is: `Metadata found in doc-comment for class local_classprogress\installation_test. Metadata in doc-comments is deprecated and will no longer be supported in PHPUnit 12. Update your test code to use attributes instead.` (one line per class or method that carries annotations).

   **Annotations versus attributes, settled:** docblock annotations work on 5.3 and do not fail the run. PHPUnit 11 counts them as *PHPUnit runner* deprecations, a category that the core `phpunit.xml` setting `failOnDeprecation="true"`, `--fail-on-warning` and `--fail-on-deprecation` all leave alone; only `--fail-on-phpunit-deprecation` or `--fail-on-all-issues` turn them into exit 1. `moodle-plugin-ci phpunit --fail-on-warning` runs exactly `vendor/bin/phpunit --fail-on-warning --testsuite local_classprogress_testsuite`, so CI passes too (verified, step 6). A probe outside the repository also showed that on both versions `@dataProvider` (with a `public static` provider) and `@group` are honoured (`--group` with the tag runs the tests, with an unknown tag `No tests executed!`), and that putting the annotation **and** the matching attribute on the same class or method gives zero deprecations on 11.5 and still passes on 9.6, which ignores the attributes. No plugin `phpunit.xml` is needed.
4. Run Behat (init and run verified on both stacks; with no features yet the run prints `No scenarios`, so the Selenium browser itself has not been exercised):
   ```bash
   moodle-docker-compose exec webserver php admin/tool/behat/cli/init.php          # 5.x: public/admin/tool/behat/cli/init.php
   moodle-docker-compose exec -u www-data webserver php admin/tool/behat/cli/run.php --tags=@local_classprogress   # 5.x: public/admin/tool/behat/cli/run.php
   ```
   `init.php` takes about 1.5 minutes and ends with `Acceptance tests environment enabled on http://webserver`. Failure screenshots appear at `http://localhost:8000/_/faildumps/` (5.3: port 8001; both URLs answer 200).
5. Check the plan parser and test data on the host, no Moodle needed (**verified**, all 14 checks pass):
   ```bash
   cd ~/PROJECTS/class-progress-tracking
   python3 tools/check_plan.py --selftest
   python3 tools/check_traceability.py          # which requirements have tests; --strict M<n> at the end of a phase
   ```
   The test-first loop and what goes in which test layer are in `docs/TESTING_STRATEGY.md`.
6. Style, PHPDoc, savepoints, Mustache, `validate`: run with `moodle-plugin-ci` (details in `docs/moodle-rules/03-testing-ci-and-release.md`, section C). The GitHub Actions workflow `.github/workflows/ci.yml` is the reference; locally the same tool runs without its `install` step (verified on both stacks, all exit 0):
   ```bash
   # once, on the host (about 10 s; host PHP is enough to install it)
   cd ~/moodle-dev && composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci ci ^4

   # The ignore list is committed in .moodle-plugin-ci.yml (tools/, test-data/, docs/, prototype/), so the checks
   # run on the repository itself (verified in R0, 2026-10-18: phplint checks only the 3 plugin files, all exit 0).
   cd ~/PROJECTS/class-progress-tracking
   ~/moodle-dev/ci/bin/moodle-plugin-ci phplint .
   ~/moodle-dev/ci/bin/moodle-plugin-ci phpcs --max-warnings 0 .
   ~/moodle-dev/ci/bin/moodle-plugin-ci mustache .
   ~/moodle-dev/ci/bin/moodle-plugin-ci savepoints .

   # validate, phpdoc and phpunit load Moodle's config.php, so they run in the webserver container, on the mounted
   # plugin (4.5 shown; 5.3: cp503-webserver-1 and /var/www/html/public/local/classprogress).
   # The tool copy in /opt/ci is lost when the container is recreated; repeat the docker cp then.
   docker exec cp405-webserver-1 rm -rf /opt/ci
   docker cp -q ~/moodle-dev/ci cp405-webserver-1:/opt/
   moodle-docker-compose exec webserver php /opt/ci/bin/moodle-plugin-ci validate --moodle /var/www/html /var/www/html/local/classprogress
   moodle-docker-compose exec webserver php /opt/ci/bin/moodle-plugin-ci phpdoc --max-warnings 0 --moodle /var/www/html /var/www/html/local/classprogress
   moodle-docker-compose exec webserver php /opt/ci/bin/moodle-plugin-ci phpunit --fail-on-warning --moodle /var/www/html /var/www/html/local/classprogress
   ```
   Results on the skeleton: `phplint` and `phpcs` check 3 files with no problems; `mustache` and `savepoints` report `No relevant files found to process, free pass!`; `validate` finds `version.php`, `lang/en/local_classprogress.php` and `pluginname` and only skips the optional `db/upgrade.php` and `db/install.xml`; `phpdoc` reports no problems; `phpunit` gives the same results as step 3. Not run locally: `phpmd`, `grunt` (needs Node inside the container) and `behat` through the tool. On the host, `validate` and `phpdoc` stop with `$CFG->dataroot is not configured properly`, which is why they run in the container.

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
| `port is already allocated` | Another stack or the local lab uses that port. Change `MOODLE_DOCKER_WEB_PORT` in the `env-*.sh`, source it, then `moodle-docker-compose down` and `moodle-docker-compose up -d`, and reinstall (section 4, "Where the data lives"). |
| Changes to `db/hooks.php` or `access.php` have no effect | Cached. Raise `$plugin->version` and run the upgrade, or purge caches. |
| New test class not found | Re-run `phpunit/cli/init.php` so the component test suite is rebuilt (`util.php --upgrade` does not exist on 4.5; verified in M1). New files in an existing `tests/` subfolder are found without it; a version change or new generator needs it. |
| Behat is very slow | Do not log in through the dashboard; start scenarios with `I am on the "..." page logged in as "teacher1"` and use generators. |
| `permission denied` on `docker` | Log out and in again so the `docker` group applies. |
| 5.3 shows a blank page or 404 | The document root must be `public/`; the `moodle-php-apache` image selects it itself when that folder exists. The start of `docker logs cp503-webserver-1` must say `Using /var/www/html/public`. |
| 5.3 PHPUnit says `OK, but there were issues!` with `PHPUnit Deprecations: N` | Expected: one per test class or method with docblock annotations (section 5, step 3). Exit code is still 0. It fails only if you add `--fail-on-phpunit-deprecation` or `--fail-on-all-issues`; don't. |
| 5.3 PHPUnit fails with a real deprecation (`Deprecations:` without `PHPUnit`) | Code under test called something deprecated; core's `phpunit.xml` has `failOnDeprecation="true"`. Run with `--display-deprecations` to see it, and fix the code. |
| Site broken, files or caches missing, after editing `local.yml` or `env-*.sh` | `up -d` recreated the webserver container and `moodledata` lived inside it (section 4). Start again: `down -v`, `up -d`, install, PHPUnit and Behat init. |
| `moodle-plugin-ci phplint` or `phpcs` checks hundreds of files | It is reading `tools/host-phpunit/vendor`. Run it on the copy with `.moodle-plugin-ci.yml` (section 5, step 6). |

## 9. M0 checklist

M0 is followed by review round R0 (`PLAN.md` section 6): every command in this file must have been run and shown to work before M1 starts. Reports go in `docs/reviews/`.

- [x] `git init` in the project folder, `.gitattributes` with the `export-ignore` lines above (both existed before M0 started; `git archive` checked, section 3)
- [x] Both test stacks start; both Moodle sites install and log in
- [x] An empty `local_classprogress` (`version.php` + `lang/en/local_classprogress.php`) installs on 4.5 (PHP 8.1) and on 5.3 (PHP 8.3)
- [x] The plugin path inside the 5.3 container is confirmed (`public/local/classprogress`)
- [x] `phpunit/cli/init.php` works on both and one trivial test passes (`tests/installation_test.php`)
- [x] First `phpunit --fail-on-warning` on 5.3 settles annotations versus attributes (annotations pass with exit 0 and print PHPUnit deprecation notices; section 5, step 3)
- [x] `moodle-plugin-ci` runs somewhere (container, host or Actions) and its `validate` step passes (`validate`, `phpdoc`, `phpunit` in both containers; `phplint`, `phpcs`, `mustache`, `savepoints` on the host; section 5, step 6)
- [x] Behat init and run work on both stacks (no scenarios yet)
- [x] This file updated: every "planned" above becomes "verified", with the real paths and commands
- [x] `.github/workflows/ci.yml` green on all four jobs (verified 2026-10-18: run 39121373112 on commit c9881ca at github.com/jvidal86/moodle-local_classprogress, every step including PHPUnit and Behat passed on 4.5/8.1/pgsql, 4.5/8.3/mariadb, 5.3/8.3/pgsql, 5.3/8.4/mariadb)
- [x] R0 check that a deliberately failing test turns CI red (verified 2026-10-18: run 39122800125 failed at the PHPUnit step on all four jobs; see `docs/reviews/R0-M0.md`)
