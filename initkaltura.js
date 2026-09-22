(function () {
    /*
     * Kaltura Embed, works by first loading Kaltura script. The scripts set a global kWidget object.
     * If Annoto plugin is run before, the Kaltura script is loaded, then kWidget would not be available.
     * and the script below will poll until it is, every 100msec, giving up after 50 retries.
     */

    window.moodleAnnoto = window.moodleAnnoto || {};

    var annotoDebugLog = function() {};
    try {
        if (window.sessionStorage.getItem('moodleAnnotoDebugKaltura')) {
            annotoDebugLog = function(msg, arg) {
                console.info('AnnotoMoodle | Kaltura: ' + msg, arg || '');
            }
        }
    } catch(err) {}

    /*
     * Kaltura V7 (playkit) widget-global bootstrap for Moodle pages.
     *
     * The Annoto playkit plugin boots the widget by referencing the global `Annoto` (window.Annoto),
     * set as a side effect of the widget bootstrap UMD's factory running. The plugin loads that
     * bootstrap with `KalturaPlayer.core.utils.Dom.loadScriptAsync(widgetUrl)` - a plain <script>
     * tag. On a Moodle page RequireJS is present (window.define.amd truthy), so the UMD takes its AMD
     * branch (`define([], factory)`). From a plain tag that is not part of a require() call that
     * anonymous define has no context: RequireJS never runs the factory (and logs "Mismatched
     * anonymous define()"), so window.Annoto is never set and the plugin's boot throws
     * "ReferenceError: Annoto is not defined" (annoto.tsx:295).
     *
     * Fix: wrap Dom.loadScriptAsync so the widget bootstrap is loaded through Moodle's AMD loader
     * (require([url])) instead of a plain tag. require() gives the anonymous define a proper context,
     * so the factory runs and sets window.Annoto - exactly how amd/src/annoto.js loads the CDN bundle
     * and the Vimeo API today. It is a drop-in replacement for the plugin's single widget load, so
     * only one load happens (no plain-tag/require collision). Crucially, unlike temporarily hiding
     * window.define.amd globally, this never disturbs any other concurrent RequireJS module load -
     * in particular the moodle-local-js CDN bundle, itself an anonymous UMD loaded via require at
     * roughly the same time, which a global define.amd toggle would break. Every non-widget Kaltura
     * script load, and the load when RequireJS is somehow unavailable, is passed straight through.
     */
    function annotoIsWidgetUrl(url) {
        // Match ONLY the widget bootstrap (a *-bootstrap.js, or a /widget/.../bootstrap path). Kept
        // deliberately narrow - a bare /annoto/ match would also reroute the Annoto plugin bundle
        // (cdn.annoto.net/playkit-plugin/...) and other annoto-hosted assets through require(),
        // breaking scripts that read their own document.currentScript (e.g. ?auto_boot detection).
        return typeof url === 'string' &&
            (/\/widget\/.*bootstrap/i.test(url) || /bootstrap\.js(\?|$)/i.test(url));
    }

    // Wrap KalturaPlayer.core.utils.Dom.loadScriptAsync so the Annoto widget bootstrap loads via
    // require() (see block comment above). Idempotent; every other Kaltura script load is passed
    // through untouched.
    function annotoWrapKalturaScriptLoader() {
        try {
            var core = window.KalturaPlayer && window.KalturaPlayer.core;
            var dom = core && core.utils && core.utils.Dom;
            if (!dom || typeof dom.loadScriptAsync !== 'function' || dom.annotoWidgetLoaderWrapped) {
                return;
            }
            dom.annotoWidgetLoaderWrapped = true;
            var origLoad = dom.loadScriptAsync;
            dom.loadScriptAsync = function (url) {
                if (!annotoIsWidgetUrl(url) || !window.require) {
                    return origLoad.apply(this, arguments);
                }
                annotoDebugLog('loading widget via AMD loader: ', url);
                return new Promise(function (resolve, reject) {
                    window.require([url], function (widgetExport) {
                        // require() ran the UMD factory in a proper context, so window.Annoto is set;
                        // keep the export as a fallback in case a build stops self-assigning it.
                        if (!window.Annoto && widgetExport) {
                            window.Annoto = widgetExport;
                        }
                        annotoDebugLog('widget loaded, window.Annoto set: ', !!window.Annoto);
                        resolve();
                    }, function (err) {
                        // Do NOT fall back to the plugin's plain-tag load: on a RequireJS page it
                        // would re-trigger the anonymous-define collision (window.Annoto stays
                        // unset + "Mismatched anonymous define()"). Reject so the plugin's own
                        // catch runs its bootstrapDone() path cleanly.
                        annotoDebugLog('widget AMD load failed: ', err);
                        reject(err);
                    });
                });
            };
            annotoDebugLog('wrapped Kaltura Dom.loadScriptAsync');
        } catch (err) {
            annotoDebugLog('wrapKalturaScriptLoader error: ', err);
        }
    }

    function annotoKalturaHookSetup() {
        annotoDebugLog('annotoKalturaHookSetup');
        if (!window.kWidget) {
            return false;
        }

        annotoDebugLog('annotoKalturaHookSetup init done');
        var maKApp = {
            kdpMap: {},

            kWidgetReady: function (player_id) {
                if (!this.kdpMap[player_id]) {
                    annotoDebugLog('kWidgetReady: ', player_id);
                    var p = document.getElementById(player_id);
                    this.kdpMap[player_id] = {
                        id: player_id,
                        player: p
                    };
                    p.kBind('annotoPluginSetup', function (params) {
                        maKApp.annotoPluginSetup(player_id, params);
                    });
                }
            },

            annotoPluginSetup: function (id, params) {
                annotoDebugLog('annotoPluginSetup: ', id);
                var kdpMap = this.kdpMap;
                var kdp = kdpMap[id];
                kdp.config = params.config;

                params.await = function (doneCb) {
                    kdp.doneCb = doneCb;
                };

                setTimeout(function() {
                    if (window.moodleAnnoto.setupKalturaKdpMap) {
                        window.moodleAnnoto.setupKalturaKdpMap(kdpMap);
                    }
                });
            },
        }

        kWidget.addReadyCallback(function (playerId) {
            maKApp.kWidgetReady(playerId);
        });
        window.moodleAnnoto.kApp = maKApp;
        return true;
    }

    var setupRetry = 0;
    function annotoKalturaHookSetupPoll() {
        if (!window.moodleAnnoto.kApp && setupRetry < 50 && !annotoKalturaHookSetup()) {
            setupRetry++;
            setTimeout(annotoKalturaHookSetupPoll, 100);
        }
    }
    annotoKalturaHookSetupPoll();

    /*
     * Kaltura V7 (playkit / window.KalturaPlayer) embed hook.
     *
     * V7 has no kWidget. Players are created via KalturaPlayer.setup(config) and can be listed
     * with KalturaPlayer.getPlayers(). There is no addReadyCallback, so existing players are
     * enumerated and future ones captured by wrapping KalturaPlayer.setup.
     *
     * A player prepared with the kaltura-v7-player-configurator bundles the Annoto playkit
     * plugin, which registers an 'annoto' service (player.getService('annoto')). The service
     * exposes onSetup(handler): the handler receives the widget config and returns a Promise
     * that, when resolved with the config, releases the widget boot. This mirrors the V2
     * annotoPluginSetup + params.await(doneCb) handshake: here doneCb resolves that Promise.
     *
     * As with V2, the captured player map is published on window.moodleAnnoto.kV7App so the CDN
     * bundle can pick it up on its own init even if it loads after the players are captured, and
     * the bundle publishes window.moodleAnnoto.setupKalturaV7PlayersMap for the reverse order.
     * This dual handshake makes load order irrelevant.
     */
    // Builds the V7 app object (the player map + capture logic). Created once and published on
    // window.moodleAnnoto.kV7App; the wrapping and enumeration around it are re-appliable, so
    // that they can be repaired if a later Kaltura bundle redefines window.KalturaPlayer.
    function annotoCreateKV7App() {
        var maKV7App = {
            playersMap: {},

            playerReady: function (player) {
                if (!player || typeof player.getService !== 'function') {
                    return;
                }
                var id = (player.config && player.config.targetId) || player.id;
                if (!id || this.playersMap[id]) {
                    return;
                }
                // Guard on the player object, not on playersMap: a player that has no Annoto
                // plugin never lands in playersMap, and the sweep below re-enumerates every player
                // on every tick - without this each tick would start another capture poll for it.
                if (player.annotoMoodleWatched) {
                    return;
                }
                player.annotoMoodleWatched = true;

                // Make the player preload its media so it resolves the media/entry at page load.
                // The Annoto playkit plugin boots the widget once the media is ready; without this
                // the widget would only appear after the user's first play. Applied via configure()
                // because the player is created (by the embed) before our KalturaPlayer.setup wrap.
                try {
                    if (typeof player.configure === 'function') {
                        player.configure({ playback: { preload: 'auto' } });
                        annotoDebugLog('configured preload=auto: ', id);
                    }
                } catch (err) {
                    annotoDebugLog('configure preload error: ', err);
                }

                // The Annoto plugin is configured via the player's uiConf, which Kaltura fetches
                // ASYNCHRONOUSLY - so getService('annoto') is usually not available yet at the
                // moment the player is created, and we must register onSetup BEFORE the plugin boots
                // the widget (a warm-cached widget bootstrap can boot in well under a poll interval).
                var self = this;
                var tryCapture = function () {
                    if (self.playersMap[id]) {
                        return true;
                    }
                    var annotoService = null;
                    try {
                        annotoService = player.getService && player.getService('annoto');
                    } catch (err) {
                        annotoDebugLog('getService threw, will retry: ', err);
                    }
                    if (annotoService && typeof annotoService.onSetup === 'function') {
                        self.capturePlayer(id, player, annotoService);
                        return true;
                    }
                    return false;
                };

                // 1. Already constructed (e.g. player created before this hook ran) - capture now.
                if (tryCapture()) {
                    return;
                }

                // 2. Primary signal: the plugin dispatches 'annotoserviceready' on the player bus
                //    synchronously the moment it registers its service (in its constructor, before
                //    init/boot). Catching it guarantees onSetup is registered before the widget's
                //    setup hook fires, regardless of how fast a warm-cached bootstrap loads.
                if (typeof player.addEventListener === 'function') {
                    try {
                        player.addEventListener('annotoserviceready', function () {
                            tryCapture();
                        });
                    } catch (err) {
                        annotoDebugLog('addEventListener failed: ', err);
                    }
                }

                // 3. Fallback poll (event unavailable, or service registered in the gap before the
                //    listener attached). Players genuinely without the Annoto plugin time out here.
                var retries = 0;
                var pollService = function () {
                    if (tryCapture()) {
                        return;
                    }
                    if (retries < 100) {
                        retries++;
                        setTimeout(pollService, 100);
                    } else {
                        annotoDebugLog('playerReady: no annoto service after retries, skipping ', id);
                    }
                };
                pollService();
            },

            capturePlayer: function (id, player, annotoService) {
                if (this.playersMap[id]) {
                    return;
                }
                annotoDebugLog('playerReady: ', id);
                var entry = {
                    id: id,
                    player: player,
                    service: annotoService
                };
                this.playersMap[id] = entry;
                var playersMap = this.playersMap;

                annotoService.onSetup(function (config) {
                    annotoDebugLog('annotoServiceSetup: ', id);
                    entry.config = config;
                    return new Promise(function (resolve) {
                        var settled = false;
                        // Releasing the boot = resolving with the (Moodle-enriched) config. Guarded
                        // so the CDN bundle and the fallback timeout below can't double-resolve.
                        entry.doneCb = function () {
                            if (settled) {
                                return;
                            }
                            settled = true;
                            resolve(entry.config);
                        };
                        setTimeout(function () {
                            if (window.moodleAnnoto.setupKalturaV7PlayersMap) {
                                window.moodleAnnoto.setupKalturaV7PlayersMap(playersMap);
                            }
                        });
                        // Fallback: if the CDN bundle at moodlejsurl is missing/stale/never completes
                        // the handshake, don't hang the widget forever - boot with the un-enriched
                        // config. SSO/Moodle context is then absent (diagnosable via this log).
                        setTimeout(function () {
                            if (!settled) {
                                annotoDebugLog('CDN handshake timed out, booting widget un-enriched: ', id);
                                entry.doneCb();
                            }
                        }, 10000);
                    });
                });
                // Hand the player to the bundle right away, not only from inside the onSetup
                // handler above. If the plugin's setup hook already fired before we registered
                // (a warm cache boots the widget in well under a poll interval) that handler is
                // never called, so without this ping the bundle would never learn the player
                // exists - and the widget would stay booted on its bare uiConf config, leaving
                // the user anonymous ("Log in to the site") with no course group. The bundle
                // recovers from a missed hook on its own, but only if it is handed the entry.
                setTimeout(function () {
                    if (window.moodleAnnoto.setupKalturaV7PlayersMap) {
                        window.moodleAnnoto.setupKalturaV7PlayersMap(playersMap);
                    }
                });
                try {
                    if (annotoService.plugin && annotoService.plugin.isWidgetBooted) {
                        annotoDebugLog('captured after widget boot, setup hook missed: ', id);
                    }
                } catch (err) {
                    annotoDebugLog('isWidgetBooted probe failed: ', err);
                }
                // We do not force service.boot() here: the playkit plugin boots the widget itself
                // once the player has resolved its media, and booting before the media/entry is
                // known leaves the widget with nothing to attach to. Loading without a first play is
                // instead achieved by preloading the media (see player.configure above).
            },
        };

        return maKV7App;
    }

    // Wrap KalturaPlayer.setup so every player created from here on is handed to playerReady.
    // Idempotent (marked on the wrapper) and re-appliable: a second Kaltura bundle redefining
    // window.KalturaPlayer replaces setup with an unwrapped one, and the sweep re-wraps it.
    function annotoWrapKalturaSetup(app) {
        var kp = window.KalturaPlayer;
        if (!kp || typeof kp.setup !== 'function' || kp.setup.annotoSetupWrapped) {
            return;
        }
        var origSetup = kp.setup;
        var wrapped = function () {
            // Ensure the loader is wrapped before this player's Annoto plugin runs its widget load
            // inside origSetup (covers players created before the poll installed the wrap above).
            annotoWrapKalturaScriptLoader();
            var player = origSetup.apply(kp, arguments);
            try {
                app.playerReady(player);
            } catch (err) {
                annotoDebugLog('playerReady error: ', err);
            }
            return player;
        };
        wrapped.annotoSetupWrapped = true;
        kp.setup = wrapped;
        annotoDebugLog('wrapped KalturaPlayer.setup');
    }

    function annotoKalturaV7Enumerate(app) {
        try {
            var players = window.KalturaPlayer.getPlayers();
            Object.keys(players).forEach(function (pid) {
                app.playerReady(players[pid]);
            });
        } catch (err) {
            annotoDebugLog('getPlayers failed: ', err);
        }
    }

    // Install (or repair) the whole V7 hook. Safe to call repeatedly.
    function annotoKalturaV7HookSetup() {
        if (!window.KalturaPlayer || !window.KalturaPlayer.getPlayers || !window.KalturaPlayer.setup) {
            return false;
        }
        var app = window.moodleAnnoto.kV7App;
        if (!app) {
            annotoDebugLog('annotoKalturaV7HookSetup init done');
            app = annotoCreateKV7App();
            window.moodleAnnoto.kV7App = app;
        }
        // Wrap the Kaltura script loader before any player (and its Annoto plugin) is constructed,
        // so the widget bootstrap loads via require() (setting window.Annoto).
        annotoWrapKalturaScriptLoader();
        annotoWrapKalturaSetup(app);
        annotoKalturaV7Enumerate(app);
        return true;
    }

    /*
     * Keep sweeping instead of stopping at the first success. Two things can still go wrong after
     * the hook is installed:
     *  - a second Kaltura bundle (another uiConf) redefines window.KalturaPlayer, dropping both the
     *    KalturaPlayer.setup wrap and the Dom.loadScriptAsync wrap with it;
     *  - a player is created in the gap before the wrap is installed, so its 'annotoserviceready'
     *    has already fired and nothing ever calls playerReady for it.
     * Either way the player is never captured, the widget boots on its bare uiConf config, and the
     * user is left anonymous with no course group - the failure is silent, because everything that
     * logs hangs off the capture. Re-wrapping and re-enumerating on a timer costs a few property
     * reads per tick and closes both gaps.
     *
     * The first 5s tick fast (the capture has to beat the widget boot, which on a warm cache
     * happens in well under a second); after that a 1s tick keeps watching for the rest of a
     * minute, which covers a player added by a late AJAX render.
     */
    var SWEEP_FAST_TICKS = 50; // 50 x 100ms = 5s
    var SWEEP_TOTAL_TICKS = 105; // + 55 x 1000ms = 60s in total
    var sweepTicks = 0;
    function annotoKalturaV7Sweep() {
        annotoKalturaV7HookSetup();
        sweepTicks++;
        if (sweepTicks < SWEEP_TOTAL_TICKS) {
            setTimeout(annotoKalturaV7Sweep, sweepTicks < SWEEP_FAST_TICKS ? 100 : 1000);
        }
    }
    annotoKalturaV7Sweep();

})();
