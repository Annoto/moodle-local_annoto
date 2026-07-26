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

### ⚠️ Verify before/while implementing (CDN was unreachable from the research environment)

1. Exact signature of the annoto service `onSetup` hook — does it pass `(config, next)` like V2's
   `annotoPluginSetup` + `await(doneCb)`, or must the host use `manualBoot: true` + `boot()` after
   configuring? Check `playkit-plugin` source (`Annoto/playkit-plugin` repo) or
   `https://cdn.annoto.net/playkit-plugin/latest/plugin.js`.
2. Whether `annoto-loader` (the configurator-installed bundle plugin) registers the same
   `'annoto'` service on `getService`, and whether its runtime config key is `annoto-loader`
   vs `annoto`.
3. Whether `getService('annoto')` is available immediately after `setup()` returns or only after
   a player event (e.g. source/media loaded) — may need to defer per-player hook registration.

Only the ~10 lines that capture config and release the boot depend on these answers; the overall
flow does not change.

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

## Status

- [x] Research + solution design (this document)
- [ ] Verify playkit-plugin service API details (checklist above)
- [ ] Implement Step 1 in `initkaltura.js` on this branch
- [ ] Implement Step 2 in `moodle-local-js` (separate repo — coordinate with Annoto CDN deploy)
- [ ] Phase 2: fallback injection setting, KAF iframe support
