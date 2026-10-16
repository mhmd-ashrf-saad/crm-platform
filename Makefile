# Makefile - the single documented command surface for this Krayin CRM checkout.
#
# `make` (or `make help`) lists every target with a one-line description. The runbook behind these
# targets -- provisioning, credentials, backup/restore, production hardening -- is scripts/README.md.
#
# Conventions this file holds itself to:
#   * Secrets stay in .env (gitignored). These targets read them at runtime and hand them to
#     commands through the environment, never as a `-p<password>` command line.
#   * No target invents a credential: `make admin` requires them from the environment.
#   * `make db-reset` is the only destructive target; it refuses unless CONFIRM is exactly the name of
#     the database it would destroy (`CONFIRM=krayin`), KRAYIN_ADMIN_EMAIL and KRAYIN_ADMIN_PASSWORD are
#     both set, and every other guard passes. It resolves the connection once (DB_DATABASE, DB_HOST,
#     DB_PORT, DB_CONNECTION -- the caller's environment wins over .env, because Laravel reads those
#     same variables) and hands exactly that to artisan, so the guard and the action can never
#     disagree about which database is at stake. It refuses while bootstrap/cache/config.php exists,
#     because a cached config beats .env and artisan would then wipe whatever the cache names. A
#     permitted run ends fully installed: the wipe, migrate and seed, then the administrator from
#     scripts/create-admin.php (one admin row, storage/installed, installation.completed=1).
#   * No target uses `pkill -f 'php artisan serve'`. That pattern is unanchored, re-reads the full
#     command line of every process, and has already killed the shell that mentioned it. Server
#     processes are identified by the socket they own plus /proc/<pid>/cwd pointing into this
#     checkout.
#
# This file is a Makefile, not a shell script. Anything that pipes it through shellcheck with
# `-s bash` reports make-only syntax, `$`-doubled shell expansions (shellcheck reads the double
# dollar as an unexpanded PID, so every guard looks like a literal string), make variables inside
# single quotes, and the deliberate word splitting in `for p in $$pids` as defects. Those are false
# positives for a Makefile, and the recipes are exercised directly instead (`make help`, `make
# status`, `make serve`, `make stop`, `make test-db`). They are disabled here, once, with the reason,
# rather than sprinkled line by line.
# shellcheck disable=SC2015,SC2016,SC2028,SC2034,SC2035,SC2046,SC2157,SC2170,SC2171,SC2193,SC2195,SC2219,SC2276,SC2288,SC2317
#
# Overridable from the command line: PORT, HOST, DB_CONTAINER, LOG.
#   make serve PORT=8001
#   make logs LOG=storage/logs/laravel.log
#   make db-reset KRAYIN_ADMIN_EMAIL=... KRAYIN_ADMIN_PASSWORD=... DB_DATABASE=krayin_scratch CONFIRM=krayin_scratch   # a scratch target, never krayin

SHELL := /bin/bash
.ONESHELL:
.DEFAULT_GOAL := help

ROOT := $(patsubst %/,%,$(dir $(realpath $(lastword $(MAKEFILE_LIST)))))
ENV_FILE := $(ROOT)/.env

# Display-only copies of .env, read once when this file is parsed. The destructive target resolves the
# connection inside its own recipe instead, at run time, and then hands those resolved values to
# artisan -- so a DB_* variable in the caller's environment moves the guard and the action together and
# can never make one check one database while the other wipes a second.
ENV_APP_ENV := $(shell sed -nE 's/^APP_ENV=(.*)/\1/p' $(ENV_FILE) | tail -1)
ENV_APP_URL := $(shell sed -nE 's/^APP_URL=(.*)/\1/p' $(ENV_FILE) | tail -1)
ENV_DB_NAME := $(shell sed -nE 's/^DB_DATABASE=(.*)/\1/p' $(ENV_FILE) | tail -1)
ENV_DB_USER := $(shell sed -nE 's/^DB_USERNAME=(.*)/\1/p' $(ENV_FILE) | tail -1)
ENV_DB_HOST := $(shell sed -nE 's/^DB_HOST=(.*)/\1/p' $(ENV_FILE) | tail -1)
ENV_DB_PORT := $(shell sed -nE 's/^DB_PORT=(.*)/\1/p' $(ENV_FILE) | tail -1)

# The test database is whichever database phpunit.xml pins. That file is the single source of truth,
# so the suite and `make test-db` cannot disagree about the name.
TEST_DB := $(shell sed -nE 's/.*name="DB_DATABASE"[[:space:]]+value="([^"]+)".*/\1/p' $(ROOT)/phpunit.xml | tail -1)
TEST_DB_PORT := $(shell sed -nE 's/.*name="DB_PORT"[[:space:]]+value="([^"]+)".*/\1/p' $(ROOT)/phpunit.xml | tail -1)

# Port: from the app's own APP_URL unless PORT is given on the command line (`make serve PORT=8001`).
# Recipes fall back to 8000 in the shell when it is empty, so this file stays plain assignments.
PORT ?= $(shell printf '%s' '$(ENV_APP_URL)' | sed -nE 's|^https?://[^:/]+:([0-9]+).*|\1|p')
HOST ?= 127.0.0.1
DB_CONTAINER ?= crm-platform-mysql
LOG ?= storage/logs/serve.log

.PHONY: help setup serve stop restart status test test-db lint lint-check admin logs db-reset db-shell

help: ## List the make targets (default target)
	@printf '\nKrayin CRM dev commands  (checkout: %s)\n\n' '$(ROOT)'
	@awk 'BEGIN { FS = ":.*## " } /^[a-zA-Z0-9_-]+:.*## / { printf "  \033[36m%-11s\033[0m %s\n", $$1, $$2 }' '$(ROOT)/Makefile'
	@port='$(PORT)'
	@[ -n "$$port" ] || port=8000
	@printf '\n  port %s  |  APP_ENV %s  |  live db %s  |  test db %s  |  container %s\n' "$$port" '$(ENV_APP_ENV)' '$(ENV_DB_NAME)' '$(TEST_DB)' '$(DB_CONTAINER)'
	@printf '  override: make <target> PORT=8001 LOG=storage/logs/laravel.log\n\n'

setup: ## Provision this checkout (scripts/provision-dev.sh; admin env vars on a fresh install)
	@cd '$(ROOT)'
	port='$(PORT)'
	[ -n "$$port" ] || port=8000
	bash scripts/provision-dev.sh --port "$$port"

serve: ## Start the dev server on the app port (idempotent: never a second server, never a kill)
	@cd '$(ROOT)'
	set -u
	host='$(HOST)'
	port='$(PORT)'
	[ -n "$$port" ] || port=8000
	# Pids owning the listening socket, matched on the port whatever bind address it is on
	# (127.0.0.1, 0.0.0.0, ::, a specific interface): an anchored host match went blind whenever the
	# server was started with a non-default --host.
	listeners() { ss -ltnpH 2>/dev/null | awk -v p=":$$port" 'length($$4) >= length(p) && substr($$4, length($$4)-length(p)+1) == p' | grep -o 'pid=[0-9]*' | cut -d= -f2; }
	# A process belongs to this checkout when its working directory is inside it: `artisan serve`
	# runs from the checkout root, the php -S child from public/.
	belongs() { cwd="$$(readlink "/proc/$$1/cwd" 2>/dev/null)" || return 1; case "$$cwd" in '$(ROOT)'|'$(ROOT)'/*) return 0;; *) return 1;; esac; }
	pids="$$(listeners)"
	if [ -n "$$pids" ]; then
	    for p in $$pids; do
	        if belongs "$$p"; then
	            echo "serve: already serving on http://$$host:$$port (pid $$p, cwd $$(readlink /proc/$$p/cwd)) - nothing to do"
	            exit 0
	        fi
	        echo "serve: refusing - port $$port is held by pid $$p (cwd $$(readlink /proc/$$p/cwd 2>/dev/null || echo unknown)), which is not this checkout" >&2
	        exit 1
	    done
	fi
	mkdir -p storage/logs
	setsid nohup php artisan serve --host="$$host" --port="$$port" >> '$(LOG)' 2>&1 < /dev/null &
	i=0
	while [ "$$i" -lt 40 ]; do
	    sleep 0.5
	    [ -n "$$(listeners)" ] && break
	    let i+=1
	done
	pids="$$(listeners)"
	if [ -z "$$pids" ]; then
	    echo "serve: nothing is listening on $$host:$$port after 20s - last lines of $(LOG):" >&2
	    tail -n 20 '$(LOG)' >&2
	    exit 1
	fi
	echo "serve: listening on http://$$host:$$port (pid $$pids); log: $(LOG)"

stop: ## Stop this checkout's dev server (port owner + /proc/<pid>/cwd; no blanket pkill)
	@cd '$(ROOT)'
	set -u
	host='$(HOST)'
	port='$(PORT)'
	[ -n "$$port" ] || port=8000
	# Matched on the port regardless of bind address, so `--host=0.0.0.0` (or any interface address)
	# is found too; ownership is still proven by /proc/<pid>/cwd below.
	listeners() { ss -ltnpH 2>/dev/null | awk -v p=":$$port" 'length($$4) >= length(p) && substr($$4, length($$4)-length(p)+1) == p' | grep -o 'pid=[0-9]*' | cut -d= -f2; }
	belongs() { cwd="$$(readlink "/proc/$$1/cwd" 2>/dev/null)" || return 1; case "$$cwd" in '$(ROOT)'|'$(ROOT)'/*) return 0;; *) return 1;; esac; }
	targets=""
	# The socket is owned by the php -S child; its `php artisan serve` parent is found by an anchored
	# pattern (start and end of the command line) that accepts any --host, and kept only when its cwd
	# is this checkout. Without the non-anchored host the parent of a server bound to 0.0.0.0 was
	# invisible to `make stop`.
	pids="$$(listeners)"
	for p in $$pids; do
	    if belongs "$$p"; then
	        targets="$$targets $$p"
	    else
	        echo "stop: not touching pid $$p on port $$port (cwd $$(readlink /proc/$$p/cwd 2>/dev/null || echo unknown)) - not this checkout" >&2
	    fi
	done
	supervisors="$$(pgrep -f "^php artisan serve --host=[^ ]* --port=$$port\$$" || true)"
	for p in $$supervisors; do
	    if belongs "$$p"; then
	        targets="$$targets $$p"
	    fi
	done
	targets="$$(printf '%s\n' $$targets | grep -v '^$$' | sort -u | tr '\n' ' ')"
	if [ -z "$$(printf '%s' $$targets | tr -d ' ')" ]; then
	    echo "stop: nothing of this checkout is listening on port $$port - nothing to stop"
	    exit 0
	fi
	echo "stop: stopping$$targets (port $$port, checkout $(ROOT))"
	kill $$targets 2>/dev/null || true
	i=0
	while [ "$$i" -lt 20 ]; do
	    sleep 0.5
	    [ -z "$$(listeners)" ] && break
	    let i+=1
	done
	still=""
	for p in $$targets; do
	    [ -d "/proc/$$p" ] && still="$$still $$p"
	done
	if [ -n "$$(printf '%s' $$still | tr -d ' ')" ]; then
	    echo "stop: still alive after 10s, sending SIGKILL to$$still" >&2
	    kill -9 $$still 2>/dev/null || true
	    sleep 1
	fi
	if [ -n "$$(listeners)" ]; then
	    echo "stop: port $$port is still held by pid $$(listeners | tr '\n' ' ')" >&2
	    exit 1
	fi
	echo "stop: stopped"

restart: stop serve ## Stop the dev server, then start it again

status: ## Show the server, the databases and the app's health
	@cd '$(ROOT)'
	set -u
	host='$(HOST)'
	port='$(PORT)'
	[ -n "$$port" ] || port=8000
	container='$(DB_CONTAINER)'
	listeners() { ss -ltnpH 2>/dev/null | awk -v p=":$$port" 'length($$4) >= length(p) && substr($$4, length($$4)-length(p)+1) == p' | grep -o 'pid=[0-9]*' | cut -d= -f2; }
	echo "checkout:  $(ROOT)"
	echo "env:       $(ENV_FILE) (APP_ENV=$(ENV_APP_ENV), APP_URL=$(ENV_APP_URL))"
	pids="$$(listeners)"
	if [ -n "$$pids" ]; then
	    echo "server:    listening on $$host:$$port (pid $$pids)"
	else
	    echo "server:    not listening on $$host:$$port (start it with: make serve)"
	fi
	echo "health:    GET / -> $$(curl -s -o /dev/null -w '%{http_code}' http://$$host:$$port/)   /admin/login -> $$(curl -s -o /dev/null -w '%{http_code}' http://$$host:$$port/admin/login)"
	echo "live db:   $(ENV_DB_NAME) on $(ENV_DB_HOST):$(ENV_DB_PORT) as $(ENV_DB_USER)"
	echo "test db:   $(TEST_DB) (pinned in phpunit.xml, port $(TEST_DB_PORT))"
	if docker inspect -f '{{.State.Running}}' "$$container" >/dev/null 2>&1; then
	    echo "container: $$container running"
	    db_pass="$$(sed -nE 's/^DB_PASSWORD=(.*)/\1/p' .env | tail -1)"
	    db_user="$$(sed -nE 's/^DB_USERNAME=(.*)/\1/p' .env | tail -1)"
	    live='$(ENV_DB_NAME)'
	    tables_q="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$$live'"
	    live_tables="$$(docker exec -e MYSQL_PWD="$$db_pass" "$$container" mysql -u"$$db_user" -N -B -e "$$tables_q" 2>/dev/null || echo '?')"
	    counts_q='SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM leads;'
	    live_counts="$$(docker exec -e MYSQL_PWD="$$db_pass" "$$container" mysql -u"$$db_user" -N -B -D "$$live" -e "$$counts_q" 2>/dev/null | tr '\n' '/' | sed 's:/$$::' || true)"
	    echo "live data: $$live_tables tables, users/leads = $$live_counts"
	    test_db='$(TEST_DB)'
	    test_q="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$$test_db'"
	    test_tables="$$(docker exec -e MYSQL_PWD="$$db_pass" "$$container" mysql -u"$$db_user" -N -B -e "$$test_q" 2>/dev/null || echo '?')"
	    echo "test data: $$test_tables tables"
	else
	    echo "container: $$container missing or stopped (start it with: make setup)"
	fi

test-db: ## Create the test database pinned in phpunit.xml and grant the .env user access
	@cd '$(ROOT)'
	set -eu
	test_db='$(TEST_DB)'
	container='$(DB_CONTAINER)'
	db_user="$$(sed -nE 's/^DB_USERNAME=(.*)/\1/p' .env | tail -1)"
	root_pass="$$(sed -nE 's/^DB_ROOT_PASSWORD=(.*)/\1/p' .env | tail -1)"
	live="$$(sed -nE 's/^DB_DATABASE=(.*)/\1/p' .env | tail -1)"
	[ -n "$$test_db" ] || { echo "test-db: phpunit.xml pins no DB_DATABASE - fix that first" >&2; exit 1; }
	[ -n "$$db_user" ] || { echo "test-db: DB_USERNAME is not set in .env" >&2; exit 1; }
	[ "$$test_db" != "$$live" ] || { echo "test-db: refusing - phpunit.xml pins '$$test_db', the live database; the test database must be a separate one" >&2; exit 1; }
	[ -n "$$root_pass" ] || { echo "test-db: DB_ROOT_PASSWORD is empty in .env - it is what creates the database and grants '$$db_user' access" >&2; exit 1; }
	# A cached config file beats .env and phpunit.xml: it would silently pin a different database.
	[ ! -f bootstrap/cache/config.php ] || { echo "test-db: refusing - bootstrap/cache/config.php exists and would ignore .env and phpunit.xml; run: php artisan config:clear" >&2; exit 1; }
	docker inspect -f '{{.State.Running}}' "$$container" >/dev/null 2>&1 || { echo "test-db: container '$$container' is not running - run: make setup" >&2; exit 1; }
	docker exec -e MYSQL_PWD="$$root_pass" "$$container" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS \`$$test_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$$test_db\`.* TO '$$db_user'@'%'; FLUSH PRIVILEGES;"
	tables_q="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$$test_db'"
	tables="$$(docker exec -e MYSQL_PWD="$$root_pass" "$$container" mysql -uroot -N -B -e "$$tables_q")"
	# The suite runs with DatabaseTransactions against a migrated, seeded database -- the same baseline a
	# freshly provisioned instance has (the admin dashboard reads the default lead pipeline, which only
	# the seeders create). DB_DATABASE is exported for these two commands only: Laravel's dotenv reader
	# is immutable, so the exported value wins over .env and nothing else in the checkout changes.
	DB_DATABASE="$$test_db" php artisan migrate --force
	DB_DATABASE="$$test_db" php artisan db:seed --force
	echo "test-db: '$$test_db' ready on $$container ($$tables tables before migrate; migrated and seeded), '$$db_user'@'%' granted access"

test: ## Run the Pest suite against the test database (refuses if it would reach the live one)
	@cd '$(ROOT)'
	set -eu
	test_db='$(TEST_DB)'
	test_port='$(TEST_DB_PORT)'
	container='$(DB_CONTAINER)'
	live="$$(sed -nE 's/^DB_DATABASE=(.*)/\1/p' .env | tail -1)"
	live_port="$$(sed -nE 's/^DB_PORT=(.*)/\1/p' .env | tail -1)"
	root_pass="$$(sed -nE 's/^DB_ROOT_PASSWORD=(.*)/\1/p' .env | tail -1)"
	[ -n "$$test_db" ] || { echo "test: refusing - phpunit.xml pins no DB_DATABASE, so the suite would inherit DB_DATABASE='$$live' from .env and migrate:fresh that database" >&2; exit 1; }
	[ "$$test_db" != "$$live" ] || { echo "test: refusing - phpunit.xml pins DB_DATABASE='$$test_db', the live database" >&2; exit 1; }
	# A cached config file beats both .env and phpunit.xml: it would silently pin the live database.
	[ ! -f bootstrap/cache/config.php ] || { echo "test: refusing - bootstrap/cache/config.php exists and would ignore phpunit.xml; run: php artisan config:clear" >&2; exit 1; }
	docker inspect -f '{{.State.Running}}' "$$container" >/dev/null 2>&1 || { echo "test: container '$$container' is not running - run: make setup" >&2; exit 1; }
	exists_q="SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = '$$test_db'"
	exists="$$(docker exec -e MYSQL_PWD="$$root_pass" "$$container" mysql -uroot -N -B -e "$$exists_q" 2>/dev/null || echo 0)"
	[ "$$exists" = "1" ] || { echo "test: test database '$$test_db' does not exist on $$container - run: make test-db" >&2; exit 1; }
	if [ -n "$$test_port" ] && [ -n "$$live_port" ] && [ "$$test_port" != "$$live_port" ]; then
	    echo "test: note - phpunit.xml pins DB_PORT=$$test_port while .env has DB_PORT=$$live_port; the suite uses $$test_port" >&2
	fi
	# The suite asserts against real tables (DatabaseTransactions) and reads seeded rows (the admin
	# dashboard needs the default lead pipeline), so the test database is migrated and seeded first.
	# Both are idempotent, they touch '$$test_db' only, and they fail loudly when the database is
	# missing rather than letting a test fall back to the live one.
	echo "test: migrating and seeding '$$test_db' (only that database)"
	DB_DATABASE="$$test_db" php artisan migrate --force
	DB_DATABASE="$$test_db" php artisan db:seed --force
	echo "test: Pest suite against '$$test_db' on $$container (the live database '$$live' is not reachable from it)"
	php artisan test --compact

lint: ## Format PHP with Pint (check only: make lint-check)
	@cd '$(ROOT)' && ./vendor/bin/pint

lint-check: ## Check PHP formatting with Pint without changing files
	@cd '$(ROOT)' && ./vendor/bin/pint --test

admin: ## Create/update the admin account (requires KRAYIN_ADMIN_EMAIL and KRAYIN_ADMIN_PASSWORD)
	@cd '$(ROOT)'
	set -u
	if [ -z "$${KRAYIN_ADMIN_EMAIL:-}" ] || [ -z "$${KRAYIN_ADMIN_PASSWORD:-}" ]; then
	    echo "admin: refusing to invent credentials - both are required and neither has a default." >&2
	    echo "admin: KRAYIN_ADMIN_EMAIL='admin@client.test' KRAYIN_ADMIN_PASSWORD='<chosen-password>' make admin" >&2
	    echo "admin: (optional: KRAYIN_ADMIN_NAME='Mohamed Ashraf')" >&2
	    exit 1
	fi
	php scripts/create-admin.php

logs: ## Tail the dev server log (LOG=storage/logs/laravel.log for application errors)
	@cd '$(ROOT)'
	mkdir -p storage/logs
	touch '$(LOG)'
	printf 'tail -n 50 -f %s  (Ctrl-C to stop)\n' '$(LOG)'
	tail -n 50 -f '$(LOG)'

db-reset: ## DESTRUCTIVE: wipe the dev database, re-migrate, re-seed and recreate the admin (needs CONFIRM=<db name> and the KRAYIN_ADMIN_* credentials)
	@cd '$(ROOT)'
	set -eu
	# The connection is resolved once, here, at run time: the caller's environment first (Laravel reads
	# those same variables, so artisan would use them anyway), .env otherwise. This one resolved set feeds
	# the guards AND every command below, so the guard and artisan cannot disagree about which database is
	# at stake. APP_ENV is deliberately .env-only: a caller must not be able to declare a production
	# instance a development one.
	db="$${DB_DATABASE-$$(sed -nE 's/^DB_DATABASE=(.*)/\1/p' .env | tail -1)}"
	db_host="$${DB_HOST-$$(sed -nE 's/^DB_HOST=(.*)/\1/p' .env | tail -1)}"
	db_port="$${DB_PORT-$$(sed -nE 's/^DB_PORT=(.*)/\1/p' .env | tail -1)}"
	db_conn="$${DB_CONNECTION-$$(sed -nE 's/^DB_CONNECTION=(.*)/\1/p' .env | tail -1)}"
	app_env="$$(sed -nE 's/^APP_ENV=(.*)/\1/p' .env | tail -1)"
	test_db='$(TEST_DB)'
	container='$(DB_CONTAINER)'
	refuse() { echo "db-reset: refusing - $$1" >&2; exit 1; }
	# A cached config file beats .env and the resolved connection: artisan would read the database
	# name from the cache while these guards read the environment, and the wipe would land on whatever
	# database the cache names (demonstrated: a cache for A made `DB_DATABASE=B CONFIRM=B make
	# db-reset` print B and wipe A). Refuse until the cache is gone.
	[ ! -f bootstrap/cache/config.php ] || refuse "bootstrap/cache/config.php exists and would ignore .env and the resolved connection; run: php artisan config:clear"
	# The credentials are required before anything is destroyed, not after. A permitted reset always
	# ends with a real administrator (below), so an operator with no credentials to give has no
	# database they may fire this target at.
	[ -n "$${KRAYIN_ADMIN_EMAIL:-}" ] || refuse "KRAYIN_ADMIN_EMAIL is not set - KRAYIN_ADMIN_EMAIL and KRAYIN_ADMIN_PASSWORD are both required, so a reset can recreate the administrator"
	[ -n "$${KRAYIN_ADMIN_PASSWORD:-}" ] || refuse "KRAYIN_ADMIN_PASSWORD is not set - KRAYIN_ADMIN_EMAIL and KRAYIN_ADMIN_PASSWORD are both required, so a reset can recreate the administrator"
	# The confirmation names the target, so it cannot be typed out of habit while testing a guard
	# (CONFIRM=reset was, and that is how the development database was destroyed). No resolved name means
	# nothing can be confirmed.
	[ -n "$$db" ] || refuse "the resolved database name is empty (DB_DATABASE in .env or the environment), so there is no database to confirm"
	[ -n "$${CONFIRM:-}" ] && [ "$${CONFIRM:-}" = "$$db" ] || refuse "this drops every table in '$$db'. The confirmation must name that database: make db-reset CONFIRM=$$db"
	[ "$$app_env" = "local" ] || refuse "APP_ENV in .env is '$$app_env', not 'local', so this is not a development instance"
	[ "$$db" != "$$test_db" ] || refuse "the target '$$db' is the test database"
	[ "$$db" != "mysql" ] && [ "$$db" != "information_schema" ] && [ "$$db" != "performance_schema" ] && [ "$$db" != "sys" ] || refuse "'$$db' is a MySQL system database"
	case "$$db_host" in 127.0.0.1|localhost|::1) ;; *) refuse "DB_HOST is '$$db_host', not a loopback address, so the target may be a live server" ;; esac
	case "$$db_conn" in mysql|mariadb) ;; *) refuse "DB_CONNECTION is '$$db_conn', not mysql or mariadb" ;; esac
	docker inspect -f '{{.State.Running}}' "$$container" >/dev/null 2>&1 || refuse "container '$$container' is not running"
	echo "db-reset: DESTROYING database '$$db' on $$db_host:$$db_port (container $$container), APP_ENV=$$app_env"
	# Hand artisan exactly the connection the guards just approved. Laravel's dotenv repository is
	# immutable, so a DB_* variable inherited from the caller's shell would otherwise win inside artisan
	# and wipe a database the guard never saw.
	export DB_DATABASE="$$db" DB_HOST="$$db_host" DB_PORT="$$db_port" DB_CONNECTION="$$db_conn"
	php artisan db:wipe --force
	php artisan migrate --force
	php artisan db:seed --force
	echo "db-reset: '$$db' rebuilt from migrations and seeders; creating the administrator (the seeder's placeholder is replaced)"
	# Always runs, because the credentials were required up front: a permitted reset must not leave a
	# half-installed instance. create-admin.php writes all three of the admin row, storage/installed
	# and the core_config installation.completed=1 row.
	php scripts/create-admin.php
	echo "db-reset: '$$db' is fully installed (administrator for $${KRAYIN_ADMIN_EMAIL}, storage/installed, installation.completed=1)"

db-shell: ## Open a MySQL shell on the live database as the .env user
	@cd '$(ROOT)'
	set -u
	db='$(ENV_DB_NAME)'
	db_user='$(ENV_DB_USER)'
	container='$(DB_CONTAINER)'
	db_pass="$$(sed -nE 's/^DB_PASSWORD=(.*)/\1/p' .env | tail -1)"
	# -t only when there is a terminal to attach to: a piped or agent-driven call would otherwise fail
	# with "the input device is not a TTY".
	tty='-i'
	[ -t 0 ] && tty='-it'
	echo "db-shell: $$db_user@$$db on $$container ('exit' or Ctrl-D to leave)" >&2
	docker exec $$tty -e MYSQL_PWD="$$db_pass" "$$container" mysql -u"$$db_user" "$$db"
