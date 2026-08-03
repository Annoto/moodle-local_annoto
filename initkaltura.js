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
     * (window.Annoto), which is created as a side effect of the widget bootstrap UMD's factory
     * running. The plugin loads that bootstrap with a plain <script> tag. On a Moodle page
     * RequireJS is present, so window.define.amd is truthy and the UMD takes its AMD branch
     * (`define([], factory)`) - an anonymous define with no require context whose factory RequireJS
     * never executes, so window.Annoto is never set. The plugin then calls Annoto.boot() on an
     * undefined global -> "ReferenceError: Annoto is not defined".
     *
     * Fix: load the same bootstrap through Moodle's AMD loader (require([url], cb)). That gives it a
     * proper require context, so the factory runs and sets window.Annoto - the exact mechanism the
     * non-Kaltura path and amd/src/annoto.js already rely on. We then boot the plugin ourselves once
     * the global is guaranteed present (see annotoKalturaV7HookSetup), turning a timing race into a
     * strict happens-before. This URL matches the plugin's own baked-in widgetUrl, so both share the
     * browser cache and the same widget version.
     */
    var ANNOTO_WIDGET_BOOTSTRAP_URL = 'https://cdn.annoto.net/widget/latest/bootstrap.js';
    var annotoWidgetGlobalReqRetry = 0;

    function annotoEnsureWidgetGlobal() {
        if (window.Annoto || window.moodleAnnoto.widgetGlobalRequested) {
            return;
        }
        if (!window.require) {
            // Moodle's AMD loader not ready yet - retry briefly (it is normally present already).
            if (annotoWidgetGlobalReqRetry < 50) {
                annotoWidgetGlobalReqRetry++;
                setTimeout(annotoEnsureWidgetGlobal, 100);
            }
            return;
        }
        window.moodleAnnoto.widgetGlobalRequested = true;
        annotoDebugLog('loading widget global via AMD: ', ANNOTO_WIDGET_BOOTSTRAP_URL);
        try {
            window.require([ANNOTO_WIDGET_BOOTSTRAP_URL], function (AnnotoExport) {
                // The bootstrap factory sets window.Annoto itself; keep the module export as a
                // fallback in case a future build stops self-assigning the global.
                if (!window.Annoto && AnnotoExport) {
                    window.Annoto = AnnotoExport;
                }
                annotoDebugLog('widget global ready: ', !!window.Annoto);
            }, function (err) {
                annotoDebugLog('widget global load failed: ', err);
            });
        } catch (err) {
            annotoDebugLog('widget global require threw: ', err);
        }
    }

    function annotoWhenWidgetGlobalReady(cb) {
        var retry = 0;
        (function poll() {
            if (window.Annoto) {
                cb();
                return;
            }
            if (retry < 100) {
                retry++;
                setTimeout(poll, 50);
                return;
            }
            annotoDebugLog('widget global not ready in time, skipping boot');
        })();
    }

    // Force the Annoto playkit plugin to manual boot so it does not auto-boot (and crash on the
    // not-yet-defined window.Annoto) before we have loaded the widget. Only touches existing
    // annoto plugin configs; returns whether one was present.
    function annotoForceManualBoot(conf) {
        try {
            var plugins = conf && conf.plugins;
            if (!plugins) {
                return false;
            }
            var found = false;
            Object.keys(plugins).forEach(function (key) {
                if (/annoto/i.test(key) && plugins[key] && typeof plugins[key] === 'object') {
                    plugins[key].manualBoot = true;
                    found = true;
                }
            });
            return found;
        } catch (err) {
            annotoDebugLog('forceManualBoot error: ', err);
            return false;
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

                // Load the widget global, then boot the plugin ourselves. On Moodle the plugin's
                // own plain-<script> widget load is swallowed by RequireJS, so window.Annoto is
                // never set and its auto-boot throws "Annoto is not defined" (which is why we set
                // manualBoot in the setup wrap). Booting only after the global is ready guarantees
                // Annoto.boot() never sees an undefined global, and also recovers a player that
                // already auto-boot-crashed (the crash is before isWidgetBooted is set, so boot()
                // runs again).
                annotoEnsureWidgetGlobal();
                annotoWhenWidgetGlobalReady(function () {
                    if (typeof entry.service.boot !== 'function') {
                        return;
                    }
                    try {
                        entry.service.boot();
                    } catch (err) {
                        annotoDebugLog('service.boot error: ', err);
                    }
                });
            },
        };

        var players = window.KalturaPlayer.getPlayers();
        Object.keys(players).forEach(function (pid) {
            maKV7App.playerReady(players[pid]);
        });

        var origSetup = window.KalturaPlayer.setup;
        window.KalturaPlayer.setup = function (conf) {
            // Before the player (and its Annoto plugin) is constructed: stop the plugin from
            // auto-booting on the not-yet-defined window.Annoto, and start loading the widget
            // global so it is ready by the time we boot the plugin in playerReady.
            if (annotoForceManualBoot(conf)) {
                annotoEnsureWidgetGlobal();
            }
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
