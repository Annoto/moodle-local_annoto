(function () {

    if (typeof kWidget == 'undefined') {
      return;
    }
    var jsparams = JSON.parse(document.getElementById('annotojsparam').dataset.jsparam);

    /**
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
    kWidget.addReadyCallback( function(playerId){
        KApps.annotoApp.kWidgetReady(playerId);
    });

    // Will be initialized to https://github.com/Annoto/widget-api/blob/master/lib/index.d.ts#L12
    var pluginApi;

    function ssoAuthRequestHandle() {
        /**
         * The userToken is same user jwt token (params.userToken)
         * if there is no userToken, I guess for moodle it would remain the same (redirct to login page) as done here:
         * https://github.com/Annoto/moodle-local_annoto/blob/master/amd/src/annoto.js#L133
         */
        var userToken = '';
        return pluginApi.auth(userToken);
    }

    window.KApps = window.KApps || {};

    KApps.annotoApp = {
        kdp: null,

        kWidgetReady: function(player_id) {
            this.kdp = window.kdp;
            if (!this.kdp) {
                this.kdp = document.getElementById(player_id);
            }

            this.kdp.kBind('annotoPluginReady', this.annotoReady.bind(this));
            this.kdp.kBind('annotoPluginSetup', this.annotoSetup.bind(this));
        },

        annotoSetup: function(params) {
            /**
             *  config will contain the annoto widget configuration.
             * This hook provides a chance to modify the configuration if required.
             * Below we use this chance to attach the ssoAuthRequestHandle and mediaDetails hooks.
             * https://github.com/Annoto/widget-api/blob/master/lib/config.d.ts#L128
             *
             * For the moodle plugin this the place to make all the configuration as done here
             * https://github.com/Annoto/moodle-local_annoto/blob/master/amd/src/annoto.js#L118
             *
             * NOTICE: config is already setup by the Kaltura Annoto plugin, so we need only to override the required configuration, such as
             * clientId, features, etc. DO NOT CHANGE THE PLAYER TYPE OR PLAYER ELEMENT CONFIG.
            */

            params.config.clientId = jsparams.clientId;

            var config = params.config;
            var widget = config.widgets[0];
            var playerConfig = widget.player;
            var ux = config.ux || {};
            config.ux = ux;

            ux.ssoAuthRequestHandle = ssoAuthRequestHandle;
            playerConfig.mediaDetails = this.enrichMediaDetails.bind(this);
            // config.locale = 'en';

        },

        annotoReady: function(api) {
            // api is the API to be used after Annoot is setup
            // It can be used for SSO auth.
            // https://github.com/Annoto/widget-api/blob/master/lib/index.d.ts#L12
            pluginApi = api;

            /**
             * This should be essentially same as here:
             * https://github.com/Annoto/moodle-local_annoto/blob/master/amd/src/annoto.js#L167
             * below is simplified version.
             */
            pluginApi.auth(jsparams.userToken).catch(function() {
                log.error('Annoto SSO auth error');
            });


        },

        enrichMediaDetails: function(details) {
            // The details contains MediaDetails the plugin has managed to obtain
            // This hook gives a change to enrich the details, for example
            // providing group information for private discussions per course/playlist
            // https://github.com/Annoto/widget-api/blob/master/lib/media-details.d.ts#L6

            var retVal = details || {};

            /**
             * Annoto Kaltura plugin, already has some details about the media like title.
             * But for moodle if the title and description we get from Moodle is the activity and present,
             * we should override it, if it's embedded in places where there is no Moodle media title, don't change it.
             * The group should always be set as done here:
             * https://github.com/Annoto/moodle-local_annoto/blob/master/amd/src/annoto.js#L142
             *
             * retVal.group = { ... };
             */
            return retVal;
        },
    }

})();
