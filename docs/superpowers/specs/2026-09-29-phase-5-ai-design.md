# Sprint Illustrations — Phase 5 Design: AI suggestions

Date: 2026-09-29
Status: Approved design
Parent: `2026-09-28-sprint-illustrations-design.md` §10 (API key), §11.2 (AiSelector), §18 (phase 5). Branch `phase-5-ai`, stacked on `phase-3b-media` (PR #4).

## 1. Core idea

AI does not draw. From the user's text it chooses a **template**, a few **keywords** taken only from the library's own tags, and **alt text**. The existing deterministic composer does the rest, with the same seed and palette as before. The suggestion is the same small shape on every surface, `{ template, keywords, title }`, and it maps straight onto the attributes and settings the Builder, the block and the widget already have.

- AI never runs during a visitor's page view, only from explicit editor actions.
- Every failure falls back to the existing `RulesSelector`.

## 2. API facts (checked 2026-09-29 against platform.claude.com docs)

- **Endpoint:** `POST https://api.anthropic.com/v1/messages`, with the headers `x-api-key`, `anthropic-version: 2023-06-01` and `content-type: application/json`.
- **Structured output:** `output_config.format = { type: "json_schema", schema }`.
  - The schema may use `enum`, `required` and `additionalProperties: false`.
  - It may **not** use `maxItems`, `minLength`/`maxLength`, `pattern` or numeric bounds, so those limits are enforced server-side after parsing.
- **Response:** the JSON arrives in a `type: "text"` content block. Thinking blocks may come first, so the parser takes the first text block.
- **Effort:** `output_config.effort: "low"`, recommended for latency-sensitive, simple tasks. Sonnet 5.5 defaults to `high`.
- **Models** (setting `model`):

  | Model | ID | Note |
  |---|---|---|
  | Claude Sonnet 5.5 | `claude-sonnet-5-5` | **Default.** Fast, $2 / $10 per MTok. |
  | Claude Opus 5.5 | `claude-opus-5-5` | Optional. |

  Haiku 4.5 is excluded because it retires on 2026-10-15.

## 3. Settings: the "AI suggestions" panel (`manage_options`)

It is a new fieldset on the existing Settings page, in the same `si-panel` style.

- **Use AI for Suggest** (checkbox). It's disabled with a hint until a key exists.
- **Anthropic API key:** a password input that is never pre-filled. When a key exists, the panel says "A key is saved." and offers **Remove key**. A new value replaces the old one, and an empty field keeps it.
  - When `SPRINT_ILLUSTRATIONS_API_KEY` is defined, the field is replaced by "Using the key from wp-config.php."
- **Model:** a select listing the models in §2.
- **Privacy help text:** "Suggest sends the text you choose, plus the names of your templates and tags, to Anthropic. Nothing is sent when visitors view pages."
- **Storage:** the option `sprint_illustrations_ai` holds `{ enabled, model, key_cipher }`. `key_cipher` is base64 of nonce plus ciphertext, and the option is registered with autoload off.

## 4. Server

**Pure (PHPUnit):**
- **`Security\SecretStore( string $key_material )`**
  - `encrypt( string $plain ): string` uses sodium `crypto_secretbox` with a random nonce, and returns base64.
  - `decrypt( string $cipher ): ?string` returns null when the input is tampered with, too short, or was made with the wrong key.
  - The key is `sodium_crypto_generichash( $key_material, '', 32 )`.
- **`Selection\AiRequest::body( string $content, array $templates, array $tags, string $model ): array`** returns the Messages request body:
  - The system prompt describes the job and the rules: pick a template, 1–6 keywords from the tag list, and alt text of at most 120 characters describing the scene.
  - The user message holds the content, trimmed to 4,000 characters and stripped of tags, and a compact template list (ID, label, tags, slot categories).
  - `max_tokens` is 1024, and `output_config` sets `effort: low` plus the schema: `template` is an enum of the template IDs, `keywords` is an array of the tag enum, and `title` is a string, all required.
- **`Selection\AiResponse::spec( array $decoded, array $template_ids, array $tags ): ?array`**:
  - It finds the first text block, parses the JSON, and checks that the template is a known ID.
  - Keywords are filtered to known tags, de-duplicated, and capped at 6.
  - Alt text is stripped of tags and capped at 120 characters.
  - It returns `{ template, keywords, title }`, or null when anything is invalid.

**WordPress-facing:**
- **`Ai\Settings`** handles the option, calls `SecretStore( wp_salt( 'auth' ) )`, and provides `has_key()`, `key()` (the constant first), `enabled()` and `model()`. It also sanitizes the settings form: key replace, remove, and the constant.
- **`Ai\AnthropicClient::messages( array $body, string $key ): array|\WP_Error`** calls `wp_remote_post` with a 20-second timeout. A non-2xx response becomes a `WP_Error` carrying the API error type, and the key never appears in the error.
- **`Rest\SuggestController`: `POST /ai/suggest`** (`edit_posts`) takes `{ content, seed? }`.
  1. Empty content returns 400 `sprint_illustrations_empty_content`.
  2. If AI is enabled and a key exists, and the user is under the rate limit (30 calls an hour, per-user transient), it calls the API and runs `AiResponse`. A valid result returns `{ template, keywords, title, source: "ai" }`.
  3. Otherwise it returns `RulesSelector::suggest( content, seed )` as `{ template, keywords, title: "", source: "rules", message }`. The `message` gives a plain reason: "AI suggestions are off", "Claude didn't answer in time", "Claude's answer didn't match the library", "You've reached 30 suggestions this hour", and so on.
  4. Failures are logged through `error_log` when `WP_DEBUG_LOG` is on, with the reason only.
- **Plugin wiring:** `Plugin::ai()` provides the settings, and `register_hooks()` registers the controller.

## 5. Surfaces

- **Builder:** a "Suggest" section at the top of the right panel holds a textarea labelled "Describe it" and a **Suggest** button, which is busy while working.
  - The result sets `spec.template`, `spec.keywords` and `spec.title`, clears `picks` (unlocking every slot), and marks the design dirty.
  - A notice follows: "Suggested by Claude." (success), or "Suggested from keywords. <message>" (info).
- **Block:** **Suggest from post** sits next to "Use post title". It sends the post title plus the post's text content (tags stripped, first 4,000 characters).
  - It sets `template`, `keywords` (comma-joined), `title`, and `illustrationId: 0`.
  - An inline status line appears under the button.
- **Elementor widget:** a new `describe` TEXTAREA control ("Describe it", which defaults to the page title when empty) and a `suggest` BUTTON control whose event is handled by `assets/elementor/editor.js`. It's enqueued on `elementor/editor/after_enqueue_scripts`, with `wp-api-fetch` and a localized REST nonce.
  - The handler posts to `/ai/suggest` and writes `template`, `keywords` and `title` onto the edited element through Elementor's command API.
  - It shows the result in an Elementor notification or toast.
  - The exact event and container API are confirmed against the installed Elementor 4.2.4 source during implementation.
- **Localized data** never includes the key, only `{ aiReady: bool }` for copy ("Suggest uses Claude" versus "Suggest uses keywords").

## 6. Testing

- **PHPUnit:**
  - `SecretStoreTest`: round trip, a different nonce each time, tamper, wrong key and short input.
  - `AiRequestTest`: the enums contain exactly the IDs and tags, effort is low, the content is trimmed and stripped, and there are no unsupported schema keywords.
  - `AiResponseTest`: valid; thinking block first; unknown template; unknown tags filtered; more than 6 keywords capped; bad JSON; no text block; alt text stripped and capped.
- **WP-CLI script** (the HTTP call is stubbed with `pre_http_request`, so no network):
  - **Permission matrix:** anonymous 401, subscriber 403, author 200.
  - **The stubbed request:** the URL and headers, model, `output_config` and schema, and the key appears only in the header.
  - **Fallbacks:** AI success, and each fallback (off, no key, HTTP 500, timeout `WP_Error`, bad JSON, unknown template).
  - **Rate limit:** the 31st call falls back.
  - **The key stays secret:** saved through the settings sanitizer, it's stored encrypted and decrypts; the constant wins; it never appears in REST responses or the Settings page HTML, and the page doesn't pre-fill it.
  - **Cleanup:** the option and users are restored afterwards.
- **Static review:** the Builder with `apiFetch` mocked; the Settings panel markup at 1440 and 782 px (frontend-design pass); the block `editor.js` lint.
- **Needs you:** paste a real key, run Suggest in the Builder, the block and the widget, and try the Elementor button in the real editor.

## 7. Out of scope

AI picking individual pieces or palettes, image generation, AI on page views, bulk suggest, and streaming.
