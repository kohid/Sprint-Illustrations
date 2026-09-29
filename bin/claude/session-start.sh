#!/usr/bin/env bash
# Claude Code SessionStart hook (and /draw-requests): turn on automatic drawing of piece requests.
# Prints instructions for the session; always exits 0 so a stopped site never blocks a session.
dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

remote=""
if [ -z "${SI_LOCAL:-}" ] && { [ -f "$dir/remote.conf" ] || [ -n "${SI_SSH_TARGET:-}" ]; }; then
	remote="$( ( [ -f "$dir/remote.conf" ] && . "$dir/remote.conf"; echo "${SI_SSH_TARGET:-}" ) )"
fi

if ! queued="$("$dir/wp.sh" sprint-illustrations requests list --format=json 2>/dev/null)"; then
	if [ -n "$remote" ]; then
		echo "Sprint Illustrations: the live site ($remote) isn't reachable over SSH, so piece requests can't be drawn yet. Check bin/claude/remote.conf, the SSH key and the network, then run /draw-requests."
		exit 0
	fi
	echo "Sprint Illustrations: the Local site isn't reachable, so piece requests can't be drawn yet. When the owner has started the site in Local, run /draw-requests."
	exit 0
fi
count="$(php -r 'echo count( (array) json_decode( stream_get_contents( STDIN ), true ) );' <<<"$queued")"
# Kept pieces and saved templates live in the plugin's assets/ and need committing to ship.
uncommitted="$(git -C "$dir/../.." status --porcelain -- assets/pieces-src assets/templates 2>/dev/null | wc -l | tr -d ' ')"

cat <<EOF
Sprint Illustrations: automatic piece drawing is on for this session (the owner's standing instruction; don't ask before drawing).
- Piece requests waiting now: $count.
- Start the Monitor tool with command "bash bin/claude/watch-requests.sh", description "piece requests", timeout_ms 1800000. It ends after 29 minutes; start it again each time it ends, for as long as the session is open.
- For each {"event":"request",...} line, draw that piece following "Piece requests" in CLAUDE.md: bin/claude/wp.sh sprint-illustrations requests start <id>; draw the SVG; render and check it; requests draft <id> --file=<svg> (zero warnings); or requests decline <id> --note="..." when the flat style can't do it. Read "feedback" when it isn't empty. When "reference" is a file path, open that image first and draw from it (shape, proportions, pose, livery) in the flat slot-colour style; don't trace it. Then say in one line what you drew.
${remote:+- REMOTE MODE: requests are drawn against the LIVE site $remote over SSH. Only "wp sprint-illustrations requests list|start|draft|decline|watch" runs there (nothing else is allowed), a draft only reaches "Ready for review" and the owner clicks Keep, so never delete or change anything on the server yourself.
}- Use bin/claude/wp.sh for every wp command. Carry on with anything the owner asks in between.
EOF
if [ "${uncommitted:-0}" -gt 0 ]; then
	echo "- $uncommitted kept piece or template file(s) in assets/ aren't committed yet. Mention it once, and commit them (with assets/pieces and assets/manifest.json) when the owner agrees."
fi
