(function () {
    /*
     *
     * For Kaltura player, Annoto widget is loaded and bootstrapped by Annoto Kaltura plugin.
     * So the Moodle plugin should not do it. Instead Annoto Kaltura plugin will expose the annoto API when it's ready.
     * So this API should be used for authetication and other stuff.
     * So this part of the moodle plugin should not be executed:
     * bootstrap: function() {
            if (this.bootsrapDone) {
                return;
            }
            this.bootsrapDone = true;
            require([this.params.bootstrapUrl], this.bootWidget.bind(this));
        },
     *
     * Below is some reference code to show how to work with the Annoto Kaltura plugin.
     * Kaltura Embed, works by first loading Kaltura script.
     * The scripts set a global kWidget object.
     *
     * If Annoto plugin is run before, the Kaltura script is loaded, them kWidget would not be available.
     * In that case let's discuss a different approach.
     */

    if (typeof kWidget == 'undefined') {
        return;
    }

    kWidget.addReadyCallback( function(playerId){
        KApps.annotoApp.kWidgetReady(playerId);
    });

    window.KApps = window.KApps || {};

    KApps.annotoApp = {
        kdpMap: {},

        kWidgetReady: function(player_id) {
            if (!this.kdpMap[player_id]) {
                var p = document.getElementById(player_id);
                this.kdpMap[player_id] = {
                    player: p
                };
                p.kBind('annotoPluginSetup', function(params) {
                    KApps.annotoApp.annotoSetup(player_id, params);
                });
            }
        },

        annotoSetup: function(id, params) {
            var kdp = this.kdpMap[id];
            kdp.config = params.config;

            params.await = function(doneCb){
                kdp.doneCb = doneCb;
            };
        },
    }

})();
