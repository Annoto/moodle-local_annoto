# Handoff: Kaltura V7 Player Embed Support

Task branch: `claude/kaltura-v7-embed-support-t87nsy`

## Goal

Add support for the Kaltura V7 player (playkit, `window.KalturaPlayer`) embedded on Moodle pages,
mirroring the existing Kaltura V2 (kWidget) integration flow. The solution must work with players
prepared using the [kaltura-v7-player-configurator](https://github.com/Annoto/kaltura-v7-player-configurator),
which bundles the Annoto loader into the player itself.

## Current architecture (V2 flow — the model to replicate)

The plugin is deliberately thin. Responsibilities are split across two repos:

1. **This repo (`moodle-local_annoto`)**
   - `lib.php` (`local_annoto_get_lib_params` → page hook) injects two scripts on target pages:
     - `initkaltura.js` — early raw JS hook for Kaltura V2.
     - AMD module `amd/src/annoto.js` — calls the `get_jsparams` web service, stores params on
       `window.moodleAnnoto.params`, then loads the CDN bundle
       (`https://cdn.annoto.net/moodle-local-js/latest/annoto.js`) and calls `AnnotoMoodle.setup()`.
   - `get_jsparams` (externallib.php) returns everything the widget needs: `clientId`, `userToken`
     (SSO JWT), `mediaGroupId/Title/Description` (course context), `mediaTitle/Description`,
     `locale`, `loginUrl`, `bootstrapUrl`, `deploymentDomain`.
   - `initkaltura.js` flow (keep as reference — V7 must mirror this):
     1. Poll for `window.kWidget` every 100ms, up to 50 retries.
     2. `kWidget.addReadyCallback(playerId)` → build `kdpMap[playerId] = {id, player}`.
     3. `player.kBind('annotoPluginSetup', params)` → capture `params.config` and a continue
        callback via `params.await(doneCb)`.
     4. Hand off: `window.moodleAnnoto.setupKalturaKdpMap(kdpMap)` (provided by the CDN bundle).
        If the bundle isn't loaded yet, the map waits on `window.moodleAnnoto.kApp` — the bundle's
        `setup()` checks for it on init. This dual handshake makes load order irrelevant.

2. **CDN bundle `moodle-local-js` (separate Annoto repo, deployed to cdn.annoto.net)**
   - `setupKalturaKdpMap(kdpMap)` → per player `setupKalturaKdp(kdp)`:
     - Skip if `!kdp.config || kdp.setupDone || !kdp.doneCb`.
     - `kBind('annotoPluginReady', authKalturaPlayer)` → `api.auth(params.userToken)`.
     - `setupKalturaPlugin(config)` — overrides ONLY: `clientId`, `hooks.getPageUrl`,
       `hooks.ssoAuthRequestHandle` (→ `params.loginUrl`), `hooks.mediaDetails`
       (enrich, don't replace — the Kaltura plugin already knows the entry title),
       `group` (course), `locale`. **Never touches player type/element** — the Kaltura plugin
       set those.
     - `kdp.doneCb()` releases the widget boot.
   - The full pre-CDN version of this logic is preserved in this repo's git history:
     `git show 200e756^:amd/src/annoto.js` — use it as the authoritative reference for the
     bundle's flow (`setupKaltura`, `setupKalturaKdpMap`, `setupKalturaPlugin`,
     `authKalturaPlayer`, `enrichMediaDetails`, and the analogous Wistia iframe flow
     `setupWistiaIframeEmbed`).

Key insight: for Kaltura, the Annoto widget is booted **by the player's own plugin** (configured
in the uiConf). The Moodle side only intercepts setup, injects Moodle context, and SSO-auths.

## V7 facts (researched)

- No `kWidget`. Global is `window.KalturaPlayer`: `KalturaPlayer.setup(config)` creates a player,
  `KalturaPlayer.getPlayers()` returns a map of existing players. There is no
  `addReadyCallback` equivalent — existing players must be enumerated and future ones captured
  by wrapping `KalturaPlayer.setup`.
- Annoto's V7 integration is the playkit plugin ([Annoto/playkit-plugin](https://github.com/Annoto/playkit-plugin)),
  script: `https://cdn.annoto.net/playkit-plugin/latest/plugin.js?auto_boot=1`.
- The [kaltura-v7-player-configurator](https://github.com/Annoto/kaltura-v7-player-configurator)
  writes `"playkit-annoto-loader": "{latest}"` into the player uiConf `confVars.versions` and sets
  `config.plugins["annoto-loader"]` (`clientId`, `region`). A player configured this way loads
  Annoto automatically in every embed — the V7 analog of a V2 uiConf with the Annoto plugin.
- Host-page API (per playkit-plugin README): `player.getService('annoto')` returns a service with:
  - `onSetup()` — config customization hook,
  - `getApi()` — promise resolving to the widget API (`api.auth(jwt)` for SSO),
  - `boot()` + plugin config `manualBoot: true` — deferred boot,
  - plugin config keys: `clientId` (omit = demo mode), `manualBoot`.

### ✅ Verified against `Annoto/playkit-plugin` source (`src/annoto.service.ts`, `src/annoto.tsx`)

1. **`onSetup` signature** — `onSetup(handle: (config: IConfig) => Promise<IConfig>): void`. It is
   NOT `(config, next)`. The widget's `hooks.setup` awaits the handler and boots with whatever
   config the returned promise resolves to. So "release the boot" = resolve that promise with the
   (Moodle-enriched) config. Our host handler stores `config` on the entry, keeps the promise
   pending, and exposes its `resolve` as `entry.doneCb` — the exact analog of V2's `params.await(doneCb)`.
2. **Service name** — the plugin does `player.registerService('annoto', service)` unconditionally
   (`src/annoto.tsx`), and `pluginName === 'annoto'` (`src/constants.ts`). So `getService('annoto')`
   is correct regardless of how the configurator names the plugin config block; the loader still
   registers the `'annoto'` service.
3. **Timing** — the service is registered synchronously in the plugin constructor, which runs during
   `KalturaPlayer.setup()`/`configure()`. Actual widget boot is deferred behind an async bootstrap
   script load (`awaitBootstrap`), so registering `onSetup` synchronously right after the player is
   created (existing players enumerated + `KalturaPlayer.setup` wrapped) reliably wins the race.
   Players not prepared with the plugin have no `'annoto'` service → skipped quietly.

SSO: the current V2 flow no longer calls `api.auth()` explicitly — `configOverride` carries
`ssoToken` and the widget authenticates on boot. V7 reuses the same `setupKalturaPlugin` override,
so `ssoToken` covers SSO there too (no `getApi().auth()` needed).

## Proposed implementation

### Step 1 — this repo: V7 hook in `initkaltura.js`

Extend `initkaltura.js` (already injected early via `lib.php:109`; keeps PHP untouched) with an
independent poll for `window.KalturaPlayer` (same 100ms × 50 pattern — a page can have both V2
and V7). Sketch:

```js
function annotoKalturaV7HookSetup() {
    if (!window.KalturaPlayer || !window.KalturaPlayer.getPlayers) {
        return false;
    }
    var maKV7App = {
        playersMap: {},
        playerReady: function (player) {
            var id = player.config && player.config.targetId;
            if (!id || this.playersMap[id]) { return; }
            var annotoService = player.getService && player.getService('annoto');
            if (!annotoService) { return; } // player not configured with annoto-loader
            var entry = { id: id, player: player, service: annotoService };
            this.playersMap[id] = entry;
            var self = this;
            // VERIFY: onSetup signature (see checklist above)
            annotoService.onSetup(function (config, next) {
                entry.config = config;
                entry.doneCb = next;
                setTimeout(function () {
                    if (window.moodleAnnoto.setupKalturaV7PlayersMap) {
                        window.moodleAnnoto.setupKalturaV7PlayersMap(self.playersMap);
                    }
                });
            });
        },
    };
    var players = window.KalturaPlayer.getPlayers();
    Object.keys(players).forEach(function (pid) { maKV7App.playerReady(players[pid]); });
    var origSetup = window.KalturaPlayer.setup;
    window.KalturaPlayer.setup = function (conf) {
        var p = origSetup.call(window.KalturaPlayer, conf);
        maKV7App.playerReady(p);
        return p;
    };
    window.moodleAnnoto.kV7App = maKV7App;
    return true;
}
```

Follow the file's existing conventions: same debug logging via
`sessionStorage 'moodleAnnotoDebugKaltura'`, same poll wrapper, IIFE style, no ES6+ that breaks
old browsers (the file currently uses `var`/ES5).

### Step 2 — CDN repo `moodle-local-js`: `setupKalturaV7`

Mirror of `setupKaltura`, reusing the existing config/auth helpers:

- `setup()` additionally checks `window.moodleAnnoto.kV7App` and exports
  `window.moodleAnnoto.setupKalturaV7PlayersMap` (dual handshake, same as V2's `kApp`).
- `setupKalturaV7PlayersMap(map)` → per entry:
  - skip if `!entry.config || entry.setupDone || !entry.doneCb`;
  - `entry.setupDone = true`;
  - reuse `setupKalturaPlugin(entry.config)` verbatim (clientId, hooks, group, locale —
    do NOT touch player type/element);
  - `entry.doneCb()`;
  - `entry.service.getApi().then(api => authKalturaPlayer(api))` — same JWT SSO.

### Step 3 — optional fallback (phase 2, admin-gated)

For V7 players NOT prepared with the configurator: inject
`https://cdn.annoto.net/playkit-plugin/latest/plugin.js?auto_boot=1` early from `initkaltura.js`
(must be present before `KalturaPlayer.setup()` runs). Gate behind a new admin setting in
`settings.php` (+ lang strings in `lang/en` and `lang/he`), default OFF — the
configurator-prepared player is the primary path.

### Out of scope (phase 2+)

KAF/`browseandembed` iframe embeds (Kaltura Video Package for Moodle) are cross-origin — the host
page cannot reach `KalturaPlayer` inside them. There, the loader boots Annoto inside the iframe;
host-side config/SSO would follow the existing Wistia iframe pattern
(`setupWistiaIframeEmbed` + `https://cdn.annoto.net/widget-iframe-api/latest/client.js`:
`onSetup(next)` → inject config, `onReady(api)` → `api.auth(token)`).

## Build & repo conventions

- `initkaltura.js` is a plain script (NOT an AMD module) — no build step, edit directly.
- If `amd/src/annoto.js` is touched, rebuild `amd/build/annoto.min.js` with Moodle's grunt
  (`grunt amd`); keep `.min.js.map` in sync.
- Bump `version.php` (`$plugin->version`, `$plugin->release`) for any shipped change.
- Commit style in this repo: conventional commits (`feat: ...`, `fix: ...`).
- Push to `claude/kaltura-v7-embed-support-t87nsy` only.

## Test matrix

1. V7 dynamic embed, configurator-prepared player → widget boots, course group set, media title
   from Kaltura entry preserved, SSO auth succeeds (check `api.auth` with `userToken`).
2. Player created after page init (late `KalturaPlayer.setup`) → captured via the setup wrap.
3. V7 player WITHOUT annoto service → graceful skip, no console errors.
4. V2 and V7 on the same page → both flows run independently.
5. Non-Kaltura pages → V7 poll gives up quietly after 50 retries.
6. Debug logging via `sessionStorage.setItem('moodleAnnotoDebugKaltura', '1')`.

## Runtime fix — "ReferenceError: Annoto is not defined" (annoto.tsx:295)

Observed on a live V7 page after Steps 1–2. Root-caused (adversarially verified against source):

- **AMD/UMD collision (the crash).** The playkit plugin loads the widget bootstrap via a plain
  `<script>` tag (`Dom.loadScriptAsync`, annoto.tsx:249). The widget sets `window.Annoto` as a
  side effect of its UMD **factory** running. On a Moodle page RequireJS makes `window.define.amd`
  truthy, so the UMD takes its AMD branch (`define([], factory)`) — a mismatched anonymous define
  whose factory RequireJS never executes — so `window.Annoto` is never set. The plugin has no
  `if (!Annoto)` guard, so `boot()` → `Annoto.boot()` (annoto.tsx:295) throws. (Non-Moodle pages
  have no AMD loader, fall through to `t.Annoto=e()`, and work.)
- **Fix (this branch, `initkaltura.js`, no build step):** load the same bootstrap through Moodle's
  AMD loader — `window.require(['https://cdn.annoto.net/widget/latest/bootstrap.js'], cb)` — which
  runs the factory and sets `window.Annoto` (the mechanism `amd/src/annoto.js:72` and the bundle's
  non-Kaltura path already use). To make it a strict happens-before instead of a race, the
  `KalturaPlayer.setup` wrap sets `manualBoot:true` on the annoto plugin config so the plugin does
  not auto-boot; we then call `service.boot()` only after `window.Annoto` exists. This also recovers
  a player that already auto-boot-crashed (the crash precedes `isWidgetBooted = true`). Verified
  against the deployed `plugin.js`: registers as `pluginName='annoto'`, honors `manualBoot`, exposes
  `service.boot()`, and its baked `widgetUrl` is exactly the URL above. Bumped to `5.5.1` /
  `2026072801` to bust Moodle's JS cache.

> **BLOCKER for the full flow:** the V7 code in `moodle-local-js` is **not yet deployed** to
> `cdn.annoto.net/moodle-local-js/latest/annoto.js` (the live bundle has none of `kalturaV7Init` /
> `setupKalturaV7PlayersMap` / the `.kaltura-player-container` double-boot guard). Until it is
> deployed, the `onSetup`→`doneCb` handshake never fires, so the widget boots but **stalls at the
> setup hook** (no Moodle SSO / course-group context). The crash is gone without it, but the widget
> only appears once the bundle is deployed.

## Status

- [x] Research + solution design (this document)
- [x] Verify playkit-plugin service API details (checklist above — verified against source)
- [x] Implement Step 1 in `initkaltura.js` on this branch
- [x] Implement Step 2 in `moodle-local-js` (branch `claude/kaltura-v7-embed-support` — `kalturaV7Init`,
      `setupKalturaV7PlayersMap`, `setupKalturaV7Player`; typecheck + lint + build pass.)
- [x] Runtime fix: AMD/UMD `window.Annoto` bootstrap + `manualBoot`-gated boot in `initkaltura.js`
- [ ] **Deploy `moodle-local-js` V7 bundle to cdn.annoto.net** (required for SSO/context; see BLOCKER above)
- [ ] Phase 2: fallback injection setting, KAF iframe support
