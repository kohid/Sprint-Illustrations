#!/usr/bin/env bash
# Run `wp sprint-illustrations requests …` on a live site over SSH (called by bin/claude/wp.sh in remote mode).
# Only the piece-request commands are allowed, so a session can't run anything else on the server.
# Settings: bin/claude/remote.conf (see remote.conf.example) or the same variables in the environment.
set -euo pipefail

dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[ -f "$dir/remote.conf" ] && . "$dir/remote.conf"
: "${SI_SSH_TARGET:?SI_SSH_TARGET is not set (bin/claude/remote.conf)}"
: "${SI_REMOTE_WP_PATH:?SI_REMOTE_WP_PATH is not set (bin/claude/remote.conf)}"
wp_bin="${SI_REMOTE_WP_BIN:-wp}"

case "${1:-} ${2:-} ${3:-}" in
	"sprint-illustrations requests list" | "sprint-illustrations requests start" | "sprint-illustrations requests draft" | \
	"sprint-illustrations requests decline" | "sprint-illustrations requests watch") ;;
	*)
		echo "Remote mode only runs: wp sprint-illustrations requests list|start|draft|decline|watch (got: $*)" >&2
		exit 2
		;;
esac
sub="$3"

ssh_opts=(-o BatchMode=yes -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=2)
# shellcheck disable=SC2206
[ -n "${SI_SSH_OPTS:-}" ] && ssh_opts+=( ${SI_SSH_OPTS} )
scp_opts=(-q "${ssh_opts[@]}")

# Single-quote a string for the remote (POSIX) shell.
sq() { printf "'%s'" "${1//\'/\'\\\'\'}"; }

remote_tmp=""
cleanup() { [ -z "$remote_tmp" ] || ssh "${ssh_opts[@]}" "$SI_SSH_TARGET" "rm -rf $(sq "$remote_tmp")" >/dev/null 2>&1 || true; }
trap cleanup EXIT

args=()
for a in "$@"; do
	case "$a" in
		--parent=*) ;; # A local PID means nothing on the server; --max-runtime ends the watcher.
		--file=*)
			f="${a#--file=}"
			base="$(basename "$f")"
			[ -f "$f" ] || { echo "File not found: $f" >&2; exit 2; }
			[[ "$base" =~ ^[A-Za-z0-9._-]+\.svg$ ]] || { echo "Use a plain file name like taxi.svg (got: $base)" >&2; exit 2; }
			if [ -z "$remote_tmp" ]; then
				remote_tmp="$(ssh "${ssh_opts[@]}" "$SI_SSH_TARGET" 'mktemp -d')"
			fi
			scp "${scp_opts[@]}" "$f" "$SI_SSH_TARGET:$remote_tmp/$base"
			args+=( "--file=$remote_tmp/$base" )
			;;
		*) args+=( "$a" ) ;;
	esac
done

cmd="cd $(sq "$SI_REMOTE_WP_PATH") && $(sq "$wp_bin")"
for a in "${args[@]}"; do cmd+=" $(sq "$a")"; done

case "$sub" in
	list | watch)
		# Reference images live on the server; fetch them so the session can open them from a local path.
		ssh "${ssh_opts[@]}" "$SI_SSH_TARGET" "$cmd" \
			| SI_SCP="scp ${scp_opts[*]}" SI_REMOTE_ROOT="$SI_REMOTE_WP_PATH" php "$dir/remote-filter.php" "$SI_SSH_TARGET"
		;;
	*)
		ssh "${ssh_opts[@]}" "$SI_SSH_TARGET" "$cmd"
		;;
esac
