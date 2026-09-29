# Phase 7: automatic drawing while a Claude Code session is open

## 1. Goal

When a Claude Code session is open in this plugin's folder, piece requests are drawn automatically. The owner files a request on the Library page and watches it move to Drawing and then Ready for review, without typing "make the requested pieces". Keep and Discard stay with the requester, so the library only grows with approved pieces. No API credit is used.

## 2. Approach

When the session starts, a background **watcher** begins running. It stays idle until a request is waiting, then prints one event line. A Claude Code **Monitor** on the watcher wakes the session for each event.

Two alternatives were rejected:
- `/loop` polling spends tokens on every check, even when the queue is empty.
- Server-side drawing through the API key costs API credit and doesn't need a session, which the owner doesn't want.

## 3. Components

### 3.1 Pure (unit-tested, no WordPress calls)

**`Library\DrawerStatus`**
- `ONLINE_SECONDS = 30`.
- `is_online( int $last_seen, int $now ): bool` returns true when `0 < $last_seen` and `$now - $last_seen <= 30`.

**`Library\WatchQueue`**
- `STALE_SECONDS = 1800`.
- `announce( array $rows, array $seen ): array{rows, seen}`:
  - It announces every `queued` row whose key `"<id>|<changed or date>"` isn't in `$seen`, oldest first.
  - A Try again sets a new `changed` time, so the request is announced again.
- `stale( array $rows, int $now ): int[]` returns the IDs of `drawing` rows whose `changed` (a GMT `Y-m-d H:i:s` string) is more than 1800 s old.

### 3.2 WordPress-facing

**`Storage\DrawerHeartbeat`**
- It stores the option `sprint_illustrations_drawer` as `{ last_seen: int }`, not autoloaded.
- `beat()` records the current time.
- `last_seen(): int` reads it.
- `online(): bool` uses `DrawerStatus`.

**`wp sprint-illustrations requests watch [--interval=<s>] [--once]`**, in `Cli\RequestsCommand`. The default interval is 5 seconds, clamped to 2–60.
- **On start**, it returns every stale `drawing` request to `queued` through `move()`. That transition is already allowed. It prints `Requeued <id> (drawing stalled).` to STDERR.
- **Each tick**, it:
  - calls `beat()`;
  - lists `queued` requests;
  - prints one JSON line to STDOUT for each request that `WatchQueue::announce` returns: `{"event":"request","id":…,"category":…,"description":…,"feedback":…}`.
- It flushes after each line.
- It also clears WordPress's object cache between ticks (`wp_cache_flush()`), because the process runs for a long time.
- `--once` runs a single tick and exits, which is useful for tests and the hook.

**`Admin\PieceRequestPanel`**
- `ajax_states` returns `{ states: {id: state}, online: bool }`.
- The panel root gets `data-si-online`.

### 3.3 Claude Code wiring (committed, `export-ignore` in releases)

**`bin/claude/wp.sh <args>`**
- It finds the Local site whose `path` in `%APPDATA%/Local/sites.json` contains this plugin folder.
- It sets `PHPRC` to that site's `run/<id>/conf/php`, uses Local's PHP 8.2 binary, and runs the WP-CLI phar from the site's `app/public`.
- If the site isn't found, or it isn't running, the script prints a plain error and exits non-zero.

**`bin/claude/watch-requests.sh`** runs `wp.sh sprint-illustrations requests watch` and restarts it after 10 s if it exits.

**`bin/claude/session-start.sh`** is the SessionStart hook.
- It prints a short instruction to the session:
  - Start a persistent Monitor on `bin/claude/watch-requests.sh`.
  - For each `request` event, follow the "Piece requests" recipe in CLAUDE.md fully and without asking: `requests start`, draw, render and check, `requests draft`, or `decline` when the flat style can't do it.
  - Then report one line in the session.
- It also lists the number of requests already queued, using `requests list --format=json`.
- If the site isn't running, it says so and suggests starting the site in Local and then running `/draw-requests`.

**`.claude/settings.json`** holds the SessionStart hook plus `permissions.allow` entries for `bin/claude/*.sh`.

**`.claude/commands/draw-requests.md`** does the same as the hook on demand, for example after the site is started.

**Two sessions at once:** `requests start` refuses anything that isn't `queued`, so a second session skips a request that's already taken.

## 4. Library page

The design follows the existing WordPress admin look (`library.css`), and it spends its boldness in one place only.

**Status strip** at the top of the queue column:
- **Online:** a solid brand-blue dot, **Claude Code is watching**, "New requests start within a few seconds."
- **Offline:** a hollow grey dot, **No Claude Code session open**, "Requests wait here until you open one in this project."

**Status lines:**
- **Queued, session online:** "Claude Code will start this in a moment."
- **Queued, session offline:** "It'll be drawn the next time you open a Claude Code session."
- **Drawing:** "Claude Code started drawing this %s ago."
- **Form hint:** "Claude Code draws it while a session is open. You'll see it here before it joins the library."

**Signature:** the active "Drawing" step of the track has a pen line under its label that draws itself on a 1.6 s loop. It's a CSS `stroke-dashoffset` on an inline SVG path. Under `prefers-reduced-motion` it's a static line.

**Live updates (`library.js`):**
- It polls every 10 s while the tab is visible, whether or not any request is active.
- It toggles the strip when `online` changes, updates the queued status lines, and reloads the page when any state changes.

## 5. Errors

- **The session closes or the watcher dies.** The heartbeat is stale after 30 s and the strip shows offline. A request left in `drawing` is re-queued by the next watcher once it's 30 minutes old.
- **A draft has warnings.** `requests draft` refuses it, and the session fixes the SVG and resubmits.
- **A request the flat style can't draw.** The session runs `decline` with a note, as it does today.
- **The site isn't running.** `wp.sh` fails clearly, and the watcher script retries every 10 s.
- **An unreadable queue (DB error).** The watcher logs it to STDERR and keeps ticking.

## 6. Testing

- **PHPUnit:** `DrawerStatusTest` covers the threshold edges and a never-seen drawer. `WatchQueueTest` covers:
  - announcing once;
  - re-announcing after a re-queue;
  - ignoring non-queued rows;
  - oldest-first order;
  - the stale cut-off.
- `composer lint` must be clean. `library.js` is a plain file with no build step, so it gets a `node --check` syntax check.
- **Live check:**
  - `requests watch --once` against the site;
  - the strip goes online while a watcher runs and offline 30 s after it stops;
  - the owner's pending **Instructor** request is drawn automatically to Ready for review.

## 7. Out of scope

- Drawing without an open session.
- Pushing notifications to the browser.
- Choosing which session draws when several are open.
