# Phase 7: Automatic Drawing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** While a Claude Code session is open in the plugin folder, queued piece requests are drawn automatically, and the Library page shows whether a session is watching.

**Architecture:** A long-running `requests watch` WP-CLI command beats a heartbeat option and prints one JSON line per waiting request. A SessionStart hook tells the session to run it under a persistent Monitor and draw each event using the existing recipe. The panel reads the heartbeat to show online or offline.

**Tech Stack:**
- PHP 8.1+ and WP-CLI, with PHPUnit 10.5 for the pure classes.
- Plain JS and CSS for the admin, with no build step.
- Bash scripts for the Claude Code hooks, run in Git Bash.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-09-29-phase-7-auto-drawing-design.md`.
- `declare( strict_types=1 );` in every PHP file, in WPCS style. `composer lint` must report zero errors and zero warnings.
- `Library\*` stays pure: no WordPress calls.
- Constants: online within **30 s** of the last beat, stale drawing after **1800 s**, watch interval default **5 s** clamped to **2–60**, watcher restart delay **10 s**, pen loop **1.6 s**.
- Copy (exact):
  - "Claude Code is watching" / "New requests start within a few seconds."
  - "No Claude Code session open" / "Requests wait here until you open one in this project."
  - "Claude Code will start this in a moment."
  - "It'll be drawn the next time you open a Claude Code session."
  - "Claude Code started drawing this %s ago."
  - "Claude Code draws it while a session is open. You'll see it here before it joins the library."
- Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

---

### Task 1: Pure status and queue rules

**Files:**
- Create: `src/Library/DrawerStatus.php`, `src/Library/WatchQueue.php`
- Test: `tests/Unit/Library/DrawerStatusTest.php`, `tests/Unit/Library/WatchQueueTest.php`

**Interfaces:**
- Produces:
  - `DrawerStatus::is_online( int $last_seen, int $now ): bool`
  - `WatchQueue::announce( array $rows, array $seen ): array{rows: list<array>, seen: list<string>}`
  - `WatchQueue::stale( array $rows, int $now ): list<int>`
- Rows use the `PieceRequestRepository` row shape (`id`, `state`, `date`, `changed`, …).

- [ ] **Step 1: Write failing tests**

```php
// tests/Unit/Library/DrawerStatusTest.php
final class DrawerStatusTest extends TestCase {
	public function test_never_seen_is_offline(): void {
		$this->assertFalse( DrawerStatus::is_online( 0, 1000 ) );
	}
	public function test_threshold(): void {
		$this->assertTrue( DrawerStatus::is_online( 1000, 1030 ) );
		$this->assertFalse( DrawerStatus::is_online( 1000, 1031 ) );
	}
}
```

```php
// tests/Unit/Library/WatchQueueTest.php
final class WatchQueueTest extends TestCase {
	private function row( int $id, string $state, string $date, string $changed = '' ): array {
		return [ 'id' => $id, 'state' => $state, 'date' => $date, 'changed' => $changed ];
	}
	public function test_announces_queued_once_oldest_first(): void {
		$rows  = [ $this->row( 2, 'queued', '2026-09-29 10:00:00' ), $this->row( 1, 'queued', '2026-09-29 09:00:00' ), $this->row( 3, 'drawing', '2026-09-29 08:00:00' ) ];
		$first = WatchQueue::announce( $rows, [] );
		$this->assertSame( [ 1, 2 ], array_column( $first['rows'], 'id' ) );
		$this->assertSame( [], WatchQueue::announce( $rows, $first['seen'] )['rows'] );
	}
	public function test_requeue_is_announced_again(): void {
		$seen = WatchQueue::announce( [ $this->row( 1, 'queued', '2026-09-29 09:00:00' ) ], [] )['seen'];
		$next = WatchQueue::announce( [ $this->row( 1, 'queued', '2026-09-29 09:00:00', '2026-09-29 11:00:00' ) ], $seen );
		$this->assertSame( [ 1 ], array_column( $next['rows'], 'id' ) );
	}
	public function test_stale_drawing(): void {
		$now  = (int) strtotime( '2026-09-29 12:00:00 UTC' );
		$rows = [
			$this->row( 1, 'drawing', '2026-09-29 09:00:00', '2026-09-29 11:29:59' ),
			$this->row( 2, 'drawing', '2026-09-29 09:00:00', '2026-09-29 11:30:00' ),
			$this->row( 3, 'queued', '2026-09-29 09:00:00', '2026-09-29 08:00:00' ),
		];
		$this->assertSame( [ 1 ], WatchQueue::stale( $rows, $now ) );
	}
}
```

- [ ] **Step 2:** Run `composer test -- tests/Unit/Library`. Expected: FAIL (classes missing).
- [ ] **Step 3: Implement**

```php
final class DrawerStatus {
	public const ONLINE_SECONDS = 30;
	public static function is_online( int $last_seen, int $now ): bool {
		return 0 < $last_seen && $now - $last_seen <= self::ONLINE_SECONDS;
	}
}
```

```php
final class WatchQueue {
	public const STALE_SECONDS = 1800;
	// Key changes when a request is re-queued (Try again sets `changed`).
	public static function key( array $row ): string {
		return $row['id'] . '|' . ( '' !== $row['changed'] ? $row['changed'] : $row['date'] );
	}
	public static function announce( array $rows, array $seen ): array {
		$queued = array_values( array_filter( $rows, static fn( array $row ): bool => 'queued' === $row['state'] ) );
		usort( $queued, static fn( array $a, array $b ): int => strcmp( $a['date'], $b['date'] ) );
		$new = array_values( array_filter( $queued, static fn( array $row ): bool => ! in_array( self::key( $row ), $seen, true ) ) );
		return [ 'rows' => $new, 'seen' => array_map( [ self::class, 'key' ], $queued ) ];
	}
	public static function stale( array $rows, int $now ): array {
		$ids = [];
		foreach ( $rows as $row ) {
			$changed = strtotime( $row['changed'] . ' UTC' );
			if ( 'drawing' === $row['state'] && '' !== $row['changed'] && false !== $changed && $now - $changed > self::STALE_SECONDS ) {
				$ids[] = (int) $row['id'];
			}
		}
		return $ids;
	}
}
```

- [ ] **Step 4:** Run `composer test` and `composer lint`. Expected: all tests pass and lint is clean.
- [ ] **Step 5:** Commit `feat(library): drawer status and watch queue rules`.

### Task 2: Heartbeat and the `requests watch` command

**Files:**
- Create: `src/Storage/DrawerHeartbeat.php`
- Modify: `src/Plugin.php` (add a `drawer_heartbeat()` accessor next to `piece_requests()`), `src/Cli/RequestsCommand.php` (add `watch`)

**Interfaces:**
- Consumes: Task 1.
- Produces: `DrawerHeartbeat::OPTION = 'sprint_illustrations_drawer'`, `beat(): void`, `last_seen(): int`, `online(): bool`, and `Plugin::drawer_heartbeat(): DrawerHeartbeat`.
- Stdout format: `{"event":"request","id":int,"category":string,"description":string,"feedback":string}`.

- [ ] **Step 1: Implement `DrawerHeartbeat`**

```php
final class DrawerHeartbeat {
	public const OPTION = 'sprint_illustrations_drawer';
	public function beat(): void {
		update_option( self::OPTION, [ 'last_seen' => time() ], false );
	}
	public function last_seen(): int {
		$value = get_option( self::OPTION, [] );
		return is_array( $value ) ? (int) ( $value['last_seen'] ?? 0 ) : 0;
	}
	public function online(): bool {
		return DrawerStatus::is_online( $this->last_seen(), time() );
	}
}
```

- [ ] **Step 2: Add the `watch` subcommand**

```php
public function watch( array $args, array $assoc_args ): void {
	$interval = max( 2, min( 60, (int) ( $assoc_args['interval'] ?? 5 ) ) );
	$once     = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'once', false );
	$requests = $this->plugin->piece_requests();

	foreach ( WatchQueue::stale( $requests->list( 'drawing', 200 ), time() ) as $id ) {
		if ( $requests->move( $id, 'queued' ) ) {
			\WP_CLI::warning( sprintf( 'Requeued %d (drawing stalled).', $id ) );
		}
	}

	$seen = [];
	while ( true ) {
		try {
			$this->plugin->drawer_heartbeat()->beat();
			$next = WatchQueue::announce( $requests->list( 'queued', 200 ), $seen );
			$seen = $next['seen'];
			foreach ( $next['rows'] as $row ) {
				\WP_CLI::line( (string) wp_json_encode( [ 'event' => 'request', 'id' => $row['id'], 'category' => $row['category'], 'description' => $row['description'], 'feedback' => $row['feedback'] ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			}
		} catch ( \Throwable $e ) {
			\WP_CLI::warning( 'Queue check failed: ' . $e->getMessage() );
		}
		if ( $once ) {
			return;
		}
		sleep( $interval );
		wp_cache_flush(); // Long-running: read fresh options and posts each tick.
	}
}
```

- [ ] **Step 3: Verify in the site.** Run `bin/claude/wp.sh sprint-illustrations requests watch --once` (from Task 4), or the manual environment described in CLAUDE.md. It should print the Instructor request as one JSON line. Then `wp option get sprint_illustrations_drawer` should show a recent `last_seen`.
- [ ] **Step 4:** Run `composer lint`, then commit `feat(cli): requests watch keeps a heartbeat and announces waiting requests`.

### Task 3: Library panel shows the session and draws the pen

**Files:**
- Modify: `src/Admin/PieceRequestPanel.php` (`render`, `render_form` hint, `render_active` status, `render_track`, `ajax_states`), `assets/admin/library.css`, `assets/admin/library.js`

**Interfaces:**
- Consumes: `Plugin::drawer_heartbeat()->online()`.
- Produces: the AJAX response `{ states: {id: state}, online: bool }`, the class `is-drawer-online` on `.si-requests`, and the elements `.si-drawer`, `.si-when-online`, `.si-when-offline` and `.si-track__pen`.

- [ ] **Step 1: PHP**
  - In `render()`, add the `is-drawer-online` class when online. Right after the queue `<div>` opens, output:

```php
echo '<div class="si-drawer" role="status">'
	. '<span class="si-drawer__dot" aria-hidden="true"></span>'
	. '<span class="si-when-online"><strong>' . esc_html__( 'Claude Code is watching', 'sprint-illustrations' ) . '</strong> ' . esc_html__( 'New requests start within a few seconds.', 'sprint-illustrations' ) . '</span>'
	. '<span class="si-when-offline"><strong>' . esc_html__( 'No Claude Code session open', 'sprint-illustrations' ) . '</strong> ' . esc_html__( 'Requests wait here until you open one in this project.', 'sprint-illustrations' ) . '</span>'
	. '</div>';
```

  - The queued status becomes two spans, `si-when-online` "Claude Code will start this in a moment." and `si-when-offline` "It'll be drawn the next time you open a Claude Code session."
  - The drawing status is `sprintf( __( 'Claude Code started drawing this %s ago.' ), $this->ago( changed ?: date ) )`.
  - `render_track( $state )` appends `<svg class="si-track__pen" viewBox="0 0 60 6" aria-hidden="true" focusable="false"><path d="M1 4C10 1 18 6 28 3S46 1 59 4" pathLength="100"/></svg>` inside the current step when `$state` is `drawing`.
  - `ajax_states` sends `[ 'states' => (object) $this->states(), 'online' => $this->plugin->drawer_heartbeat()->online() ]`.

- [ ] **Step 2: CSS.** Remove the old `si-pulse` animation rule for `.is-drawing .si-track__step.is-current`, then add:

```css
.si-drawer{display:flex;align-items:baseline;gap:8px;margin:0 0 16px;padding:8px 12px;border-radius:4px;background:#f6f7f7;color:#50575e;font-size:12px}
.si-drawer strong{color:#1d2327;font-weight:600}
.si-drawer__dot{flex:none;width:8px;height:8px;border-radius:50%;border:2px solid #8c8f94;transform:translateY(1px)}
.si-requests.is-drawer-online .si-drawer{background:#f0f6fc}
.si-requests.is-drawer-online .si-drawer__dot{border-color:#2271b1;background:#2271b1}
.si-requests .si-when-online,.si-requests.is-drawer-online .si-when-offline{display:none}
.si-requests.is-drawer-online .si-when-online{display:inline}
.si-track__pen{position:absolute;left:10px;right:10px;bottom:-5px;width:calc(100% - 20px);height:6px;overflow:visible}
.si-track__pen path{fill:none;stroke:#2271b1;stroke-width:1.5;stroke-linecap:round;stroke-dasharray:100;stroke-dashoffset:0}
@media (prefers-reduced-motion:no-preference){.si-track__pen path{animation:si-pen 1.6s ease-in-out infinite}}
@keyframes si-pen{0%{stroke-dashoffset:100}60%{stroke-dashoffset:0}100%{stroke-dashoffset:0;opacity:0}}
```

- [ ] **Step 3: JS.** `library.js` polls every 10 s whenever the tab is visible:
  - It reads `result.data.states` and reloads the page when any state changes.
  - It toggles `is-drawer-online` from `result.data.online`.
- [ ] **Step 4: Verify.**
  - `node --check assets/admin/library.js` and `composer lint` both pass.
  - Render the panel with `wp --user=<admin> eval` to confirm it shows offline.
  - Run a watcher, then confirm the panel shows online.
  - Do a visual check at 1440 and 782 px.
- [ ] **Step 5:** Commit `feat(library): show whether Claude Code is watching; pen line while drawing`.

### Task 4: Claude Code wiring

**Files:**
- Create: `bin/claude/local-env.php`, `bin/claude/wp.sh`, `bin/claude/watch-requests.sh`, `bin/claude/session-start.sh`, `.claude/settings.json`, `.claude/commands/draw-requests.md`
- Modify: `.gitattributes` (`/.claude export-ignore`, `/bin/claude export-ignore`), `CLAUDE.md` (Commands and Piece requests)

- [ ] **Step 1: `local-env.php`**
  - It finds the site in `%APPDATA%/Local/sites.json` whose expanded `path` is a prefix of the plugin folder, comparing case-insensitively with `/` separators.
  - It prints `PHPRC=…`, `PHP=…` and `PUBLIC=…`.
  - `PHP` is the newest `lightning-services/php-<version>+*/bin/win64` directory. `PUBLIC` is `<path>/app/public`.
  - The WP-CLI phar comes from `WP_CLI_PHAR`, otherwise from `%ProgramFiles(x86)%` or `%ProgramFiles%` plus `/Local/resources/extraResources/bin/wp-cli/wp-cli.phar`, printed as `PHAR=…`.
  - On any failure it prints a plain reason to stderr and exits 1.
- [ ] **Step 2: Scripts**
  - `wp.sh` evaluates the `local-env.php` output, exports `PHPRC`, `cd`s into `PUBLIC`, and runs `exec "$PHP/php" "$PHAR" "$@"`.
  - `watch-requests.sh` loops `wp.sh sprint-illustrations requests watch "$@"`, with the restart note sent to stderr and `sleep 10`.
  - `session-start.sh` counts the queued requests through `wp.sh … requests list --format=json`, then prints the instruction block from spec §3.3. It prints the "site isn't reachable" line instead when `wp.sh` fails. It always exits 0.
- [ ] **Step 3: `.claude/settings.json`**

```json
{
	"hooks": {
		"SessionStart": [ { "hooks": [ { "type": "command", "command": "bash \"$CLAUDE_PROJECT_DIR/bin/claude/session-start.sh\"", "timeout": 60 } ] } ]
	},
	"permissions": {
		"allow": [ "Bash(bin/claude/wp.sh *)", "Bash(bash bin/claude/wp.sh *)", "Bash(bash bin/claude/watch-requests.sh)", "Bash(bash bin/claude/session-start.sh)" ]
	}
}
```

- [ ] **Step 4:** `.claude/commands/draw-requests.md` says: "Run `bash bin/claude/session-start.sh` and follow its instructions." Then update `CLAUDE.md` to cover `wp.sh`, the automatic drawing, and `/draw-requests`.
- [ ] **Step 5: Verify.** `bash bin/claude/session-start.sh` prints the block with the right count, and `bin/claude/wp.sh option get siteurl` works. Commit `feat(claude): draw piece requests automatically while a session is open`.

### Task 5: Live check

- [ ] Start the Monitor on `bash bin/claude/watch-requests.sh`. The panel should show **Claude Code is watching**.
- [ ] Draw the owner's pending **Instructor** request from the event, taking it through `start`, drawing, checking and `draft`. It should reach **Ready for review**.
- [ ] Stop the watcher. The panel should show offline within about 40 s.
- [ ] Run `composer test` and `composer lint`, then add execution notes to this plan and commit.

## Execution notes

- **Tasks 1–4:** built as planned; 5 new unit tests (252 total), and `composer lint` is clean.
- **Change from the plan: `watch` has `--max-runtime` and `--parent`.**
  - A Claude Code Monitor lasts at most 30 minutes, so `watch-requests.sh` runs for 29 minutes and the session re-arms it.
  - On Windows, stopping the Monitor left `php.exe` orphaned, and the orphan kept the heartbeat "online". The watcher now gets the script's Windows PID (`/proc/$$/winpid`) and exits within one tick once the script is gone. Verified by stopping the task: the process was gone within 8 s.
- **Startup noise:** `wp.sh` passes `-d display_startup_errors=0`. Local's php.ini names `php_imagick.dll`, and the startup warning went to STDOUT, where it would wake the Monitor.
- **Panel:** the visual check used a static render with the admin CSS (the page needs a login), at 1440 px and at 782 px with reduced motion. The pen line shows under Drawing.
- **Review:** feature-dev's code-reviewer reported no high-confidence issues. Its note about `--max-runtime` possibly becoming 0 is fixed. Two notes are accepted:
  - Each new watcher re-announces requests that are still queued. That's harmless, because `start` refuses a request that isn't queued.
  - There can be a short "offline" gap while the Monitor is re-armed.
- **Live:** the Monitor was started exactly as the hook instructs. The owner's **Instructor** request (#456) was announced, started, drawn as `char-iona-instructor` (with a lanyard badge, one hand explaining and the other free to hold, zero warnings), and submitted. It's at **Ready for review**, and both previews render.
