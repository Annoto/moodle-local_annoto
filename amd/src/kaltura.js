// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * javscript for component 'local_annoto'.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    return {
            init: function(params) {console.dir('kaltura amd');
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
                      this.kdp.kBind('doPlay', function(){console.dir('play')});

                  },

                  annotoSetup: function(params) {console.dir('setup');
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

                      var config = params.config;
                      var widget = config.widgets[0];
                      var playerConfig = widget.player;
                      var ux = config.ux || {};
                      config.ux = ux;

                      ux.ssoAuthRequestHandle = ssoAuthRequestHandle;
                      playerConfig.mediaDetails = this.enrichMediaDetails.bind(this);
                      // config.locale = 'en';
                  },

                  annotoReady: function(api) {console.dir('ready');
                      // api is the API to be used after Annoot is setup
                      // It can be used for SSO auth.
                      // https://github.com/Annoto/widget-api/blob/master/lib/index.d.ts#L12
                      pluginApi = api;

                      /**
                       * This should be essentially same as here:
                       * https://github.com/Annoto/moodle-local_annoto/blob/master/amd/src/annoto.js#L167
                       * below is simplified version.
                       */
                      pluginApi.auth(token)
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

            /**
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
        }
    };
});
