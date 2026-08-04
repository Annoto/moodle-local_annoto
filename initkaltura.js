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
        // The Annoto widget bootstrap: annoto-hosted, or a *-bootstrap.js (covers a self-hosted
        // config.bootstrapUrl). Intentionally narrow so no unrelated Kaltura script is rerouted.
        return typeof url === 'string' && (/annoto/i.test(url) || /bootstrap\.js(\?|$)/i.test(url));
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
                var self = this;
                var args = arguments;
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
                        // Fall back to the plugin's original plain-tag load so behaviour is never
                        // worse than without this wrap.
                        annotoDebugLog('widget AMD load failed, falling back to plain load: ', err);
                        try {
                            var p = origLoad.apply(self, args);
                            if (p && typeof p.then === 'function') {
                                p.then(resolve, reject);
                            } else {
                                resolve();
                            }
                        } catch (e) {
                            reject(e);
                        }
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
