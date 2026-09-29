#!/usr/bin/env bash
# Event source for a Claude Code Monitor: one JSON line per waiting piece request (spec: phase 7).
# Ends after 29 minutes, just inside the Monitor's 30-minute limit; the session then re-arms it.
# The watcher gets this script's PID and exits by itself when the script is stopped, because
# Windows doesn't stop child processes with their parent.
dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
parent="$(cat "/proc/$$/winpid" 2>/dev/null || echo "$$")"
limit=1740

until [ "$SECONDS" -ge "$limit" ]; do
	"$dir/wp.sh" sprint-illustrations requests watch --parent="$parent" --max-runtime=$(( limit - SECONDS )) && break
	echo "Request watcher stopped; restarting in 10 s." >&2
	sleep 10
done
