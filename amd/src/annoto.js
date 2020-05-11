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

define([
  'jquery',
  'core/log',
  'core/notification',
  'core/ajax',
], function($, log, notification, Ajax) {

    window.moodleAnnoto = window.moodleAnnoto || {};

    try {
        if (window.sessionStorage.getItem('moodleAnnotoDebug')) {
            log = console;
        }
    } catch (err) {}

    return {
        init: function(courseid, pageurl, modid) {
            log.info('AnnotoMoodle: plugin init');
            Ajax.call([{
                methodname: 'get_jsparams',
                args: {
                    courseid: courseid,
                    pageurl: pageurl,
                    modid: modid
                },
                done: function(response) {
                    var params = JSON.parse(response);
                    this.params = params;
                    if (!params) {
                        log.error('AnnotoMoodle: empty params. Plugin won`t start.');
                        return;
                    }

                    // Return if plugin works in Global scope or is present in ACL or has <annoto> tag - continue this script.
                    if (!this.params.isGlobalScope) {
                        if (!(this.params.isACLmatch || this.hasAnnotoTag())) {
                            log.info('AnnotoMoodle: plugin is disabled for this page.');
                            return;
                        }
                    } else if (this.hasAnnotoTag()) {
                        log.info('AnnotoMoodle: plugin is disabled for this page using the Atto plugin.');
                        return;
                    }

                    this.setupKaltura();
                    $(document).ready(this.findPlayer.bind(this));

                }.bind(this),
                fail: notification.exception
            }]);
        },
        setupKaltura: function() {
            var maKApp = window.moodleAnnoto.kApp;
            window.moodleAnnoto.setupKalturaKdpMap = this.setupKalturaKdpMap.bind(this);

            if (maKApp) {
                log.info('AnnotoMoodle: Kaltura loaded on init');
                this.setupKalturaKdpMap(maKApp.kdpMap);
            } else {
                log.info('AnnotoMoodle: Kaltura not loaded on init');
            }
        },
        hasAnnotoTag: function() {
            return ($('annoto').length > 0 && $('annotodisable').length === 0);
        },
        findPlayer: function() {
            log.info('AnnotoMoodle: detecting player');
            var h5p = $('iframe.h5p-iframe').first().get(0),
                youtube = $('iframe[src*="youtube.com"]').first().get(0),
                vimeo = $('iframe[src*="vimeo.com"]').first().get(0),
                videojs = $('.video-js').first().get(0),
                jwplayer = $('.jwplayer').first().get(0),
                annotoplayer = '';

            if (videojs) {
                annotoplayer = videojs;
                this.params.playerType = 'videojs';
            } else if (jwplayer) {
                annotoplayer = jwplayer;
                this.params.playerType = 'jw';
            } else if (h5p) {
                annotoplayer = h5p;
                this.params.playerType = 'h5p';
            } else if (youtube) {
                var youtubeSrc = youtube.src;
                if (youtubeSrc.search(/enablejsapi/i) === -1) {
                    youtube.src = (youtubeSrc.search(/[?]/) === -1) ? youtubeSrc + '?enablejsapi=1' : youtubeSrc + '&enablejsapi=1';
                }
                annotoplayer = youtube;
                this.params.playerType = 'youtube';
            } else if (vimeo) {
                annotoplayer = vimeo;
                this.params.playerType = 'vimeo';
            } else {
                return;
            }
            if (!annotoplayer.id || annotoplayer.id === '') {
                annotoplayer.id = this.params.defaultPlayerId;
            }

            this.params.playerId = annotoplayer.id;

            this.bootstrap();
            log.info('AnnotoMoodle: detected ' + this.params.playerType + ':' + this.params.playerId);
        },
        bootstrap: function() {
            if (this.bootsrapDone) {
                return;
            }
            this.bootsrapDone = true;
            require([this.params.bootstrapUrl], this.bootWidget.bind(this));
        },
        bootWidget: function() {
            var params = this.params;
            var nonOverlayTimelinePlayers = ['youtube', 'vimeo'];
            var innerAlignPlayers = ['h5p'];
            var horizontalAlign = 'element_edge';
            if (!params.widgetOverlay || params.widgetOverlay === 'auto') {
                horizontalAlign = (innerAlignPlayers.indexOf(params.playerType) !== -1) ? 'inner' : 'element_edge';
            } else if (params.widgetOverlay === 'inner') {
                horizontalAlign = 'inner';
            }
            var config = {
                clientId: params.clientId,
                position: params.position,
                features: {
                    tabs: params.featureTab,
                    cta: params.featureCTA,
                },
                width: {
                    max: (horizontalAlign === 'inner') ? 320 : 360,
                },
                align: {
                    vertical: params.alignVertical,
                    horizontal: horizontalAlign,
                },
                ux: {
                    ssoAuthRequestHandle: function() {
                        window.location.replace(params.loginUrl);
                    },
                },
                zIndex: params.zIndex ? params.zIndex : 100,
                widgets: [{
                    player: {
                        type: params.playerType,
                        element: params.playerId,
                        mediaDetails: function() {
                            return {
                                title: params.mediaTitle,
                                description: params.mediaDescription,
                                group: {
                                    id: params.mediaGroupId,
                                    type: 'playlist',
                                    title: params.mediaGroupTitle,
                                    privateThread: params.privateThread,
                                }
                            };
                        },
                    },
                    timeline: {
                        overlayVideo: (nonOverlayTimelinePlayers.indexOf(params.playerType) === -1),
                    },
                }],
                demoMode: params.demoMode,
                rtl: params.rtl,
                locale: params.locale,
            };

            if (window.Annoto) {
                window.Annoto.on('ready', this.annotoReady.bind(this));
                if (params.playerType === 'videojs' && window.requirejs) {
                    window.require(['media_videojs/video-lazy'], function(vjs) {
                        config.widgets[0].player.params = {
                            videojs: vjs
                        };
                        window.Annoto.boot(config);
                    });
                } else {
                    window.Annoto.boot(config);
                }
            } else {
                log.warn('AnnotoMoodle: bootstrap didn`t load');
            }
        },

        annotoReady: function(api) {
            // Api is the API to be used after Annoot is setup
            // It can be used for SSO auth.
            var jwt = this.params.userToken;
            log.info('AnnotoMoodle: annoto ready');
            if (api && jwt && jwt !== '') {
                api.auth(jwt).catch(function() {
                    log.error('AnnotoMoodle: SSO auth error');
                });
            } else {
                log.info('AnnotoMoodle: SSO auth skipped');
            }
        },

        setupKalturaKdpMap: function(kdpMap) {
            if (!kdpMap) {
                log.info('AnnotoMoodle: skip setup Kaltura players - missing map');
                return;
            }
            log.info('AnnotoMoodle: setup Kaltura players');
            for (var kdpMapKey in kdpMap) {
                if (kdpMap.hasOwnProperty(kdpMapKey)) {
                    this.setupKalturaKdp(kdpMap[kdpMapKey]);
                }
            }
        },
        setupKalturaKdp: function(kdp) {
            if (!kdp.config || kdp.setupDone || !kdp.doneCb) {
                log.info('AnnotoMoodle: skip Kaltura player: ' + kdp.id);
                return;
            }
            log.info('AnnotoMoodle: setup Kaltura player: ' + kdp.id);
            kdp.setupDone = true;
            kdp.player.kBind('annotoPluginReady', this.annotoReady.bind(this));
            this.setupKalturaPlugin(kdp.config);
            kdp.doneCb();
        },
        setupKalturaPlugin: function(config) {
            /*
             * Config will contain the annoto widget configuration.
             * This hook provides a chance to modify the configuration if required.
             * Below we use this chance to attach the ssoAuthRequestHandle and mediaDetails hooks.
             * https://github.com/Annoto/widget-api/blob/master/lib/config.d.ts#L128
             *
             * NOTICE: config is already setup by the Kaltura Annoto plugin,
             * so we need only to override the required configuration, such as
             * clientId, features, etc. DO NOT CHANGE THE PLAYER TYPE OR PLAYER ELEMENT CONFIG.
            */
            var params = this.params;
            var widget = config.widgets[0];
            var playerConfig = widget.player;
            var ux = config.ux || {};
            var align = config.align || {};
            var features = config.features || {};

            config.ux = ux;
            config.align = align;
            config.features = features;

            config.clientId = params.clientId;
            config.position = params.position;
            config.demoMode = params.demoMode;
            config.locale = params.locale;
            config.rtl = params.rtl;

            features.tabs = params.featureTab;
            features.cta = params.featureCTA;
            align.vertical = params.alignVertical;
            ux.ssoAuthRequestHandle = function() {
                window.location.replace(params.loginUrl);
            };
            playerConfig.mediaDetails = this.enrichMediaDetails.bind(this);
        },

        enrichMediaDetails: function(details) {
            // The details contains MediaDetails the plugin has managed to obtain
            // This hook gives a change to enrich the details, for example
            // providing group information for private discussions per course/playlist
            // https://github.com/Annoto/widget-api/blob/master/lib/media-details.d.ts#L6.
            // Annoto Kaltura plugin, already has some details about the media like title.
            //
            var params = this.params;
            var retVal = details || {};

            retVal.title = retVal.title || params.mediaTitle;
            retVal.description = retVal.description ? retVal.description : params.mediaDescription;
            retVal.group = {
                id: params.mediaGroupId,
                type: 'playlist',
                title: params.mediaGroupTitle,
                privateThread: params.privateThread,
            };

            return retVal;
        },
    };
});
