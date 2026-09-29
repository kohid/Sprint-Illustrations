#!/usr/bin/env bash
# Run WP-CLI against the Local (by Flywheel) site that contains this plugin, from any shell.
# Usage: bin/claude/wp.sh <wp-cli args>   e.g. bin/claude/wp.sh sprint-illustrations requests list
set -euo pipefail

dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Remote modes send piece-request commands to a live site. SI_LOCAL=1 forces the Local site for one command.
#  - SI_REST_URL (env or remote.conf): over the site's REST API (a sandbox with no SSH; see wp-rest.php).
#  - SI_SSH_TARGET (env or remote.conf): over SSH (see wp-remote.sh).
if [ -z "${SI_LOCAL:-}" ]; then
	[ -f "$dir/remote.conf" ] && . "$dir/remote.conf"
	if [ -n "${SI_REST_URL:-}" ]; then
		export SI_REST_URL SI_REST_USER SI_REST_PASSWORD
		exec php "$dir/wp-rest.php" "$@"
	fi
	if [ -n "${SI_SSH_TARGET:-}" ]; then
		exec bash "$dir/wp-remote.sh" "$@"
	fi
fi

env_lines="$(php "$dir/local-env.php")"

value() { sed -n "s/^$1=//p" <<<"$env_lines"; }
export PHPRC="$(value PHPRC)"
php_dir="$(value PHP)"
phar="$(value PHAR)"

cd "$(value PUBLIC)"
# Local's php.ini names extensions that may be missing (e.g. imagick); keep that noise off STDOUT.
exec "$php_dir/php" -d display_startup_errors=0 "$phar" "$@"
