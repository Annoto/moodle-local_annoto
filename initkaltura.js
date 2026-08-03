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
     * The Annoto playkit plugin boots the widget by referencing the global `Annoto`
     * (window.Annoto). That global is set as a side effect of the widget bootstrap UMD running -
     * but only when the UMD takes its plain-global branch. The plugin loads the bootstrap with
     * `KalturaPlayer.core.utils.Dom.loadScriptAsync` (a plain <script> tag). On a Moodle page
     * RequireJS is present, so window.define.amd is truthy and the UMD instead takes its AMD branch
     * (`define([], factory)`) - an anonymous define with no require context. RequireJS never runs
     * that factory (and logs "Mismatched anonymous define()"), so window.Annoto is never set and the
     * plugin's boot throws "ReferenceError: Annoto is not defined" (annoto.tsx:295).
     *
     * The plugin's own config carries manualBoot/clientId etc. from the *server-side* uiConf (written
     * by the kaltura-v7-player-configurator), not from the client KalturaPlayer.setup() config, so it
     * cannot be forced to manual boot from here. Instead we wrap Dom.loadScriptAsync so that, only
     * while the widget bootstrap script loads and runs, window.define.amd is hidden. The UMD then
     * takes its plain-global branch and assigns window.Annoto exactly as on a non-AMD page - no
     * RequireJS involvement, no mismatched define, no duplicate download, and the plugin's own load
     * sets the global (deterministic, no race). define.amd is restored as soon as the script has run,
     * so Moodle's RequireJS is otherwise untouched.
     */
    var annotoAmdHideDepth = 0;
    var annotoSavedAmd;

    // Hide window.define.amd (once, ref-counted for concurrent loads). Returns false when not on a
    // RequireJS page, so callers fall back to loading unchanged.
    function annotoHideAmd() {
        if (!window.define) {
            return false;
        }
        if (annotoAmdHideDepth === 0) {
            if (!window.define.amd) {
                return false;
            }
            annotoSavedAmd = window.define.amd;
            window.define.amd = undefined;
        }
        annotoAmdHideDepth++;
        return true;
    }

    function annotoRestoreAmd() {
        if (annotoAmdHideDepth === 0) {
            return;
        }
        annotoAmdHideDepth--;
        if (annotoAmdHideDepth <= 0) {
            annotoAmdHideDepth = 0;
            if (window.define) {
                window.define.amd = annotoSavedAmd;
            }
        }
    }

    // Wrap KalturaPlayer.core.utils.Dom.loadScriptAsync so the Annoto widget bootstrap loads with
    // define.amd hidden (see block comment above). Idempotent; every other Kaltura script load is
    // passed through untouched.
    function annotoWrapKalturaScriptLoader() {
        try {
            var core = window.KalturaPlayer && window.KalturaPlayer.core;
            var dom = core && core.utils && core.utils.Dom;
            if (!dom || typeof dom.loadScriptAsync !== 'function' || dom.annotoAmdWrapped) {
                return;
            }
            dom.annotoAmdWrapped = true;
            var origLoad = dom.loadScriptAsync;
            dom.loadScriptAsync = function (url) {
                // The widget bootstrap is the load that defines window.Annoto, so hide AMD for it
                // whether we recognise its URL (the standard cdn.annoto.net widget) or simply
                // because the global is not set yet - the latter also covers a self-hosted
                // config.bootstrapUrl. Once window.Annoto exists, every load is passed through.
                var isWidgetLoad = typeof url === 'string' &&
                    (/\/widget\/.*bootstrap|annoto/i.test(url) || !window.Annoto);
                if (!isWidgetLoad || !annotoHideAmd()) {
                    return origLoad.apply(this, arguments);
                }
                annotoDebugLog('loading widget with define.amd hidden: ', url);
                var restored = false;
                var restore = function () {
                    if (restored) {
                        return;
                    }
                    restored = true;
                    annotoRestoreAmd();
                };
                // Failsafe: never leave AMD hidden if the load never settles.
                setTimeout(restore, 30000);
                var result;
                try {
                    result = origLoad.apply(this, arguments);
                } catch (err) {
                    restore();
                    throw err;
                }
                if (result && typeof result.then === 'function') {
                    // loadScriptAsync resolves on the script's onload, i.e. after it has executed,
                    // so window.Annoto is already set by the time we restore define.amd.
                    result.then(restore, restore);
                } else {
                    setTimeout(restore);
                }
                return result;
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
    function annotoKalturaV7HookSetup() {
        annotoDebugLog('annotoKalturaV7HookSetup');
        if (!window.KalturaPlayer || !window.KalturaPlayer.getPlayers || !window.KalturaPlayer.setup) {
            return false;
        }

        annotoDebugLog('annotoKalturaV7HookSetup init done');
        // Wrap the Kaltura script loader before any player (and its Annoto plugin) is constructed,
        // so the widget bootstrap loads with define.amd hidden and sets window.Annoto.
        annotoWrapKalturaScriptLoader();
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
                var annotoService = player.getService('annoto');
                if (!annotoService || typeof annotoService.onSetup !== 'function') {
                    // Player was not prepared with the Annoto plugin (annoto-loader). Skip quietly.
                    annotoDebugLog('playerReady: no annoto service, skipping ', id);
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
                        // Releasing the boot = resolving with the (Moodle-enriched) config.
                        entry.doneCb = function () {
                            resolve(entry.config);
                        };
                        setTimeout(function () {
                            if (window.moodleAnnoto.setupKalturaV7PlayersMap) {
                                window.moodleAnnoto.setupKalturaV7PlayersMap(playersMap);
                            }
                        });
                    });
                });
            },
        };

        var players = window.KalturaPlayer.getPlayers();
        Object.keys(players).forEach(function (pid) {
            maKV7App.playerReady(players[pid]);
        });

        var origSetup = window.KalturaPlayer.setup;
        window.KalturaPlayer.setup = function (conf) {
            // Ensure the loader is wrapped before this player's Annoto plugin runs its widget load
            // inside origSetup (covers players created before the poll installed the wrap above).
            annotoWrapKalturaScriptLoader();
            var player = origSetup.call(window.KalturaPlayer, conf);
            try {
                maKV7App.playerReady(player);
            } catch (err) {
                annotoDebugLog('playerReady error: ', err);
            }
            return player;
        };

        window.moodleAnnoto.kV7App = maKV7App;
        return true;
    }

    var setupV7Retry = 0;
    function annotoKalturaV7HookSetupPoll() {
        if (!window.moodleAnnoto.kV7App && setupV7Retry < 50 && !annotoKalturaV7HookSetup()) {
            setupV7Retry++;
            setTimeout(annotoKalturaV7HookSetupPoll, 100);
        }
    }
    annotoKalturaV7HookSetupPoll();

})();
