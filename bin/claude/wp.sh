#!/usr/bin/env bash
# Run WP-CLI against the Local (by Flywheel) site that contains this plugin, from any shell.
# Usage: bin/claude/wp.sh <wp-cli args>   e.g. bin/claude/wp.sh sprint-illustrations requests list
set -euo pipefail

dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Remote mode: bin/claude/remote.conf (or SI_SSH_TARGET) sends piece-request commands to a live site over SSH.
# SI_LOCAL=1 forces the Local site for one command.
if [ -z "${SI_LOCAL:-}" ] && { [ -f "$dir/remote.conf" ] || [ -n "${SI_SSH_TARGET:-}" ]; }; then
	exec bash "$dir/wp-remote.sh" "$@"
fi

env_lines="$(php "$dir/local-env.php")"

value() { sed -n "s/^$1=//p" <<<"$env_lines"; }
export PHPRC="$(value PHPRC)"
php_dir="$(value PHP)"
phar="$(value PHAR)"

cd "$(value PUBLIC)"
# Local's php.ini names extensions that may be missing (e.g. imagick); keep that noise off STDOUT.
exec "$php_dir/php" -d display_startup_errors=0 "$phar" "$@"
