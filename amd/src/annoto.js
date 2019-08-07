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

    return {
        init: function(courseid, pageurl, modid) {

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
                        log.warn('Empty params. Annoto will not start.');
                        return;
                    }

                    // Return if plugin works in Global scope or is present in ACL or has <annoto> tag - continue this script.
                    if (!this.params.isGlobalScope) {
                        if (!(this.params.isACLmatch || this.hasAnnotoTag())) {
                            log.debug('Annoto is disabled for this page.');
                            return;
                        }
                    }

                    if (typeof kWidget == 'undefined') {
                        this.findPlayer();
                    }

                }.bind(this),
                fail: notification.exception
            }]);
        },
        hasAnnotoTag: function() {
            return ($('annoto').length > 0 && $('annotodisable').length === 0);
        },
        findPlayer: function() {

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
                window.Annoto.on('ready', function(api) {
                    var jwt = params.userToken;
                    if (api && jwt && jwt !== '') {
                        api.auth(jwt).catch(function() {
                            log.error('Annoto SSO auth error');
                        });
                    }
                });
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
                log.warn('Annoto not loaded');
            }
        }
    };
});
