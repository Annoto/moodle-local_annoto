# Kaltura V7 (playkit) Player Embed Support — Reference

**Status: DONE — embed + SSO + group/course scoping + load-on-page-load working end-to-end.**

- Plugin (`moodle-local_annoto`): `5.5.2`, branch `feat-Kaltura-V7-(playkit)-player-embed-support`.
- CDN bundle (`moodle-local-js`): branch `claude/kaltura-v7-embed-support`.

## Goal

Support the Kaltura V7 player (playkit, `window.KalturaPlayer`) embedded on Moodle pages, for players
prepared with the [kaltura-v7-player-configurator](https://github.com/Annoto/kaltura-v7-player-configurator)
(which bundles the Annoto [playkit-plugin](https://github.com/Annoto/playkit-plugin) into the player's
uiConf). The plugin is thin; work is split across two repos:

- **`moodle-local_annoto`** — `lib.php` injects `initkaltura.js` (raw ES5, no build) + the AMD module
  `amd/src/annoto.js` on target pages. `amd/src/annoto.js` calls `get_jsparams`, stores the result on
  `window.moodleAnnoto.params`, then `require([annotoMoodleCdnUrl], AnnotoMoodle => AnnotoMoodle.setup())`.
  `get_jsparams` (`lib.php`) returns `clientId`, `userToken` (SSO JWT), `mediaGroupId/Title/Description`
  (course context), `mediaTitle/Description`, `locale`, `loginUrl`, `bootstrapUrl`, `deploymentDomain`,
  `annotoMoodleCdnUrl` (the bundle URL, admin setting `local_annoto/moodlejsurl`).
- **`moodle-local-js`** — the `AnnotoMoodle` CDN bundle served from `moodlejsurl`.

## How it works (the final flow)

The V7 widget is booted by the player's **own playkit plugin** (from the uiConf). The Moodle side only
captures the player, injects Moodle context, applies group, and SSO-auths.

1. **`initkaltura.js`** polls for `window.KalturaPlayer`; on it:
   - **Crash fix — load the widget via `require()`.** The playkit plugin loads the widget bootstrap via
     `KalturaPlayer.core.utils.Dom.loadScriptAsync(widgetUrl)` — a plain `<script>`. On a Moodle page
     RequireJS makes `window.define.amd` truthy, so the widget UMD takes its `define([],factory)` branch;
     from a plain tag that anonymous define has no context, RequireJS never runs the factory, `window.Annoto`
     is never set, and the plugin's `Annoto.boot()` throws `ReferenceError: Annoto is not defined`
     (annoto.tsx:295). Fix: wrap `Dom.loadScriptAsync` so the widget bootstrap is loaded through
     `require([url])` instead — `require` gives the anonymous define a proper context, the factory runs, and
     `window.Annoto` is set (exactly how `amd/src/annoto.js` loads the bundle and Vimeo). It's a drop-in
     replacement for the plugin's single widget load (no plain-tag/require collision, no "Mismatched
     anonymous define()"), and it **never mutates `define.amd`** (a global toggle would break the bundle's
     own `require([...])` load). Only widget-bootstrap URLs are rerouted; everything else passes through.
   - **Player capture — on the `annotoserviceready` event.** The plugin is configured from the *async*
     uiConf, so `getService('annoto')` is usually empty when `KalturaPlayer.setup()` returns. The plugin
     dispatches `annotoserviceready` on the player bus synchronously when it registers its service (before it
     boots), so `playerReady` listens for that (poll fallback), then `capturePlayer` registers
     `service.onSetup(handler)` and adds an entry to `window.moodleAnnoto.kV7App.playersMap`. The handler
     stores `config` on the entry, returns a pending Promise whose `resolve` is `entry.doneCb`, and pings the
     bundle (`setupKalturaV7PlayersMap`) if it's loaded. A 10s fallback resolves `doneCb` un-enriched if the
     bundle never completes the handshake (so a missing bundle degrades instead of hanging).
   - **Load on page load (no first play required).** The playkit plugin boots the widget only once the
     player has resolved its media, which defaults to first play. `playerReady` calls
     `player.configure({ playback: { preload: 'auto' } })` on the (already-created) player so the media
     resolves at page load and the plugin boots the widget without a click. (We do NOT force
     `service.boot()` — booting before the media/entry is known leaves the widget nothing to attach to.)
2. **`moodle-local-js`** — `kalturaV7Init()` publishes `setupKalturaV7PlayersMap` and processes any
   already-captured `kV7App.playersMap` (load-order independent via the `setupDone`/`doneCb` guards).
   `setupKalturaV7Player(entry)` → `setupKalturaPlugin(entry.config)` (applies `configOverride`: `clientId`,
   `backend`, `hooks`, `group`, `locale`, `ssoToken`) → `entry.doneCb()` → `fixKalturaV7Overflow(entry)` →
   `finalizeKalturaV7Player(entry)`.
   - **`fixKalturaV7Overflow`** — the playkit player renders inline inside Moodle's `.no-overflow` activity
     wrapper (`overflow:auto`), which clips the widget panel opening beside the video. Sets
     `overflow:visible` on the player's `.no-overflow` ancestors, re-applied at several delays + on resize
     (Moodle's layout JS re-sets it on the page-load path).
   - **Double-boot guard** — once the media preloads, the `<video>` is present at page load, so the bundle's
     generic `bootstrap()` skips entirely whenever a `.kaltura-player-container` is on the page (otherwise it
     would find the video and boot the widget the plugin already booted → "already running").

### Key playkit insight: apply Moodle config explicitly on the ready widget API

**The playkit widget does NOT reliably apply what you put in the setup-hook config** — neither `ssoToken`
nor `group` took effect that way (V2's kWidget flow did, but playkit doesn't). So `finalizeKalturaV7Player`
gets the ready widget API and applies them explicitly, mirroring what V2 effectively did:

```
service.getApi()
  → api.load(entry.config)   // (re)applies the Moodle-enriched config incl. IGroupDetails group
  → api.auth(userToken)      // SSO — runs last so it sticks; load failure is caught, never blocks auth
```

`api.load()` is the widget API's supported way to (re)apply a config (the bundle already uses it for media
changes). `IGroupDetails` = `{ id: courseid, title: course.fullname, description: course.summary }`.

## SSO configuration (not code — a real gotcha)

`invalid sso token` (SSO_AUTH_ERR 205, 401 from `auth.<region>.annoto.net`) is a **config** issue:

- Moodle **`clientID`** must be the **raw clientId UUID** (e.g. `a95…f`), NOT a signed-clientId JWT
  (`eyJ…`). The token is built by `local_annoto_get_user_token()` with `iss => clientid` (HS256, signed
  with `ssosecret`); a signed-clientId JWT as `iss` isn't resolvable by the backend.
- **`ssosecret`** must exactly match the SSO secret Annoto has registered for that clientId (right account
  + region; the auth call goes to the region in `deploymentDomain`).
- The token is generated by the shared `local_annoto_get_user_token()`, so **V2 and V7 share this
  requirement** — if V2 SSO works, V7 will too once the code path is right.

## Branches, build, conventions

- **Bundle** → `claude/kaltura-v7-embed-support` (only). Build: `npm run build` (lint + webpack prod →
  `dist/annoto.js`). Serve `dist/annoto.js` and point `moodlejsurl` at it.
- **Plugin** → `feat-Kaltura-V7-(playkit)-player-embed-support`. `initkaltura.js` is a plain ES5 script
  (no build). Bump `version.php` for any shipped change. Do not touch `main` unless asked.
- Debug logs: `sessionStorage.setItem('moodleAnnotoDebug','1')` (bundle `AnnotoMoodle:` logs) and
  `moodleAnnotoDebugKaltura` (initkaltura `AnnotoMoodle | Kaltura:` logs).

## Verified at runtime

Widget boots (no `Annoto is not defined` / `Mismatched anonymous define()`); player captured
(`kV7App.playersMap` non-empty); `api.load` applies the course group; `api.auth` authenticates the Moodle
user; V2 / non-Kaltura paths unaffected. A full multi-agent adversarial review (both repos) validated the
architecture; its one HIGH finding (poll losing the capture race on warm cache) is closed by the
`annotoserviceready` event capture.

## Failure mode: the setup hook is missed (fixed 5.5.4)

**Symptom:** on some pages the widget renders but the user is anonymous ("Log in to the site")
even though SSO works on other pages — typically the *second* video visited in a session, i.e. the
first one loaded with a warm cache. Nothing is logged: the console shows `setup Kaltura V7 players`
with no `setup Kaltura V7 player: <id>` line after it.

**Cause:** every Moodle-side effect hung off the setup-hook handshake. If the capture lost the race
(the widget bootstrap and app chunks come from cache and boot before `service.onSetup` is
registered, or `window.KalturaPlayer` was redefined by a second Kaltura bundle and dropped our
`setup` wrap), `onSetupHandler` is never called, so `entry.config`/`entry.doneCb` are never set,
`initkaltura.js` never pings the bundle, and `setupKalturaV7Player` returned early — no
`api.load()`, no `api.auth()`. The widget stays booted on its bare uiConf config: anonymous, and
with no course group.

**Fix, part 1 — load the hook before any embed (the root cause).** `local_annoto_init()` queued
`initkaltura.js` with `$PAGE->requires->js($url)`, and without `$inhead` Moodle emits that at the
**end of the body** — after every Kaltura embed has created its player, and on a warm cache after
the plugin has already booted the widget. `local_annoto_kaltura_hook_html()` now emits the script
tag from the top-of-body callback (`local_annoto_before_standard_top_of_body_html` pre-4.4, the
`before_standard_top_of_body_html_generation` hook after), ahead of all page content, for every
theme — `LOCAL_ANNOTO_TOP_OF_BODY_THEMES` still only controls the early full `local_annoto_init()`.
`local_annoto_init()` falls back to the footer require only if that callback did not run. With the
wrap installed first, capture happens synchronously as each player is created, which is always
before the widget can boot (the plugin's `init()` has to load the widget bootstrap first).

**Fix, part 2 — recover if it is still missed.** `service.getApi()` resolves once the widget is
ready whether or not the setup hook reached us (`annotoApi` just awaits the plugin's `awaitBoot`),
so:

- `initkaltura.js` pings `setupKalturaV7PlayersMap` from `capturePlayer` as well as from inside the
  `onSetup` handler, so the bundle always learns the player exists;
- it keeps sweeping (100ms for 5s, then 1s for a minute) instead of stopping at the first success,
  re-wrapping `Dom.loadScriptAsync` / `KalturaPlayer.setup` and re-enumerating `getPlayers()`, so a
  redefined global (a second uiConf bundle) or a player created before the wrap is still picked up;
- `setupKalturaV7Player` runs `finalizeKalturaV7Player` even with no `config`/`doneCb`, and
  `finalizeKalturaV7Player` reads `entry.config` *inside* the `getApi().then()` (by which time a
  handshake still in flight has stored it) and falls back to `recoverKalturaV7Config` — the live
  config off the plugin behind the service, merged with `configOverride`.

**Fix, part 3 — seed the group so a group-less boot is unreachable.** The setup hook is the
plugin's intended way to ask for the config, but it fires exactly once and can be missed. The
widget API has no group setter (`IAnnotoApi` is `auth`/`samlAuth`/`load`/`destroy`/`logout`/
`show`/`hide`/`getMetadata`/`getWidgetState`; the internal set-commands are `thread_tag` and
`group_comments_query`), so `api.load()` — a whole `IConfig` — is the only way to change a *running*
widget. Before boot, though, there is a much better one: `bootWidget()` boots with
`plugin.widgetConfig`, so `seedKalturaV7Config()` sets

```
plugin.widgetConfig = plugin.mergeConfigUpdate(configOverride)
```

as the first thing `setupKalturaV7Player` does, before anything can release the boot.
`mergeConfigUpdate` is the plugin's own merge: it deep-merges the override over the existing config
and re-forces the parts only the plugin can supply — `widgets[0].player`
(type/element/adaptorApi) and `hooks.setup`, so the handshake still works. The group is then in the
boot config whether or not the hook ever reaches us, and `recoverKalturaV7Config` (which reads that
same object) inherits it too.

**Fix, part 4 — fail closed, as the last resort.** With seeding in place the only way to end up
without a group is being unable to reach the plugin at all (a build that renames
`service.plugin` / `widgetConfig` / `mergeConfigUpdate`), in which case neither seeding nor recovery
works. So `api.auth()` runs *only* after `api.load()` has applied the enriched config; if no config
can be obtained, or `load` rejects, the user is deliberately left anonymous and an **error** is
logged (`course group not applied, skipping SSO auth to avoid mis-scoped activity`). An
authenticated user on a widget still running the bare uiConf config would file comments against the
wrong scope — under a different clientId and region even — and that is worse than a login prompt.

### Verifying it without a Moodle

`moodle-local-js` has `npm run test:kaltura-v7` (`test/kaltura-v7-plugin-contract.js`). It downloads
the **real published** playkit plugin (`cdn.annoto.net/playkit-plugin/latest/plugin.js`), runs it in
jsdom against stubs for the handful of things it takes from the player library, and asserts the
whole contract — 24 checks over four scenarios:

| scenario | asserts |
| --- | --- |
| capture before boot, seeded | the config `Annoto.boot()` receives carries the course group, title, Moodle clientId, backend and SSO token, **and** keeps `hooks.setup` plus `widgets[0].player` type/element/adaptorApi |
| capture before boot, not seeded | reproduces the bug: no group, falls back to the uiConf clientId |
| capture only after boot | seed reports "already booted", `recoverKalturaV7Config` + `api.load` restore the group and clientId with the player wiring intact, and `api.auth` runs only after |
| plugin internals unreachable | no `load`, **no `auth`** — fail closed |

Run it after any playkit-plugin release: it is what catches a build that renames `service.plugin`,
`plugin.widgetConfig`, `plugin.mergeConfigUpdate` or `plugin.isWidgetBooted`. `PLUGIN_URL=` pins a
specific version, `PLUGIN_BUNDLE=` runs against a local copy offline.

Two incidental confirmations from writing it: `bootWidget()` resolves `Annoto` as a **bare global**
(so the widget bootstrap really must end up assigning `window.Annoto` — hence the `require()` wrap),
and playkit's `Object.mergeDeep` skips class instances (`isClassInstance`), which is why merging a
config that carries `widgets[0].player.adaptorApi` terminates instead of recursing forever.

## Out of scope (phase 2+)

KAF / `browseandembed` iframe embeds (Kaltura Video Package for Moodle) are cross-origin — the host page
can't reach `KalturaPlayer` inside the iframe. There the loader boots Annoto inside the iframe; host-side
config/SSO would follow the Wistia iframe pattern (`setupWistiaIframeEmbed` +
`widget-iframe-api/latest/client.js`: `onSetup(next)` → inject config, `onReady(api)` → `api.auth(token)`).

## History (superseded — kept for context)

Approaches that were tried and replaced, so they aren't re-attempted:
- **`config.ssoToken` / setup-hook config for SSO & group** — playkit ignores it; use explicit
  `api.auth` / `api.load` (above).
- **Separate `require([widgetUrl])` preload** (while the plugin also plain-tag-loaded) — the two collided
  on RequireJS's queue ("Mismatched anonymous define()"). Fixed by *replacing* the plugin's load.
- **Forcing `manualBoot` via `KalturaPlayer.setup(conf)`** — the plugin's config comes from the server-side
  uiConf, not the client argument, so the mutation never reached it.
- **Hiding `window.define.amd` globally** during the widget load — a global mutation that intermittently
  broke the concurrently-loading `moodle-local-js` bundle (also an anonymous UMD via `require`). The
  `require()`-replacement touches `define.amd` not at all.
- **100ms `getService` poll for capture** — lost the race on a warm cache / 2nd player. Replaced by the
  `annotoserviceready` event (poll kept only as fallback).
