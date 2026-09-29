#!/usr/bin/env bash
# Claude Code SessionStart hook (and /draw-requests): turn on automatic drawing of piece requests.
# Prints instructions for the session; always exits 0 so a stopped site never blocks a session.
dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if ! queued="$("$dir/wp.sh" sprint-illustrations requests list --format=json 2>/dev/null)"; then
	echo "Sprint Illustrations: the Local site isn't reachable, so piece requests can't be drawn yet. When the owner has started the site in Local, run /draw-requests."
	exit 0
fi
count="$(php -r 'echo count( (array) json_decode( stream_get_contents( STDIN ), true ) );' <<<"$queued")"

cat <<EOF
Sprint Illustrations: automatic piece drawing is on for this session (the owner's standing instruction; don't ask before drawing).
- Piece requests waiting now: $count.
- Start the Monitor tool with command "bash bin/claude/watch-requests.sh", description "piece requests", timeout_ms 1800000. It ends after 29 minutes; start it again each time it ends, for as long as the session is open.
- For each {"event":"request",...} line, draw that piece following "Piece requests" in CLAUDE.md: bin/claude/wp.sh sprint-illustrations requests start <id>; draw the SVG; render and check it; requests draft <id> --file=<svg> (zero warnings); or requests decline <id> --note="..." when the flat style can't do it. Read "feedback" when it isn't empty. When "reference" is a file path, open that image first and draw from it (shape, proportions, pose, livery) in the flat slot-colour style; don't trace it. Then say in one line what you drew.
- Use bin/claude/wp.sh for every wp command. Carry on with anything the owner asks in between.
EOF
