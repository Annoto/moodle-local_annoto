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
        init: function(courseid, modid) {
            log.info('AnnotoMoodle: plugin init');
            Ajax.call([{
                methodname: 'get_jsparams',
                args: {
                    courseid: courseid,
                    modid: modid
                },
                done: function(response) {
                    if (!response) {
                        log.error('AnnotoMoodle: empty params. Plugin won`t start.');
                        return;
                    }
                    this.params = JSON.parse(response);

                    // Return if has <annoto> tag.
                    if (this.hasAnnotoTag()) {
                        log.info('AnnotoMoodle: plugin is disabled for this page using the Atto plugin.');
                        return;
                    }

                    this.setupKaltura();
                    this.setupWistiaIframeEmbed();
                    $(document).ready(this.bootstrap.bind(this));

                }.bind(this),
                fail: notification.exception
            }]);

        },
        setupKaltura: function() {
            const maKApp = window.moodleAnnoto.kApp;
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
        findPlayer: function(container) {
            log.info('AnnotoMoodle: detecting player');
            const parent = container || document.body,
                h5p = $(parent).find('iframe.h5p-iframe').first().get(0),
                youtube = $(parent).find('iframe[src*="youtube.com"]').first().get(0),
                vimeo = $(parent).find('iframe[src*="vimeo.com"]').first().get(0),
                videojs = $(parent).find('.video-js').first().get(0),
                jwplayer = $(parent).find('.jwplayer').first().get(0),
                wistia = $(parent).find('.wistia_embed').first().get(0),
                html5 = $(parent).find('video').first().get(0);
            let playerElement = null;

            if (videojs) {
                playerElement = videojs;
                this.params.playerType = 'videojs';
            } else if (jwplayer) {
                playerElement = jwplayer;
                this.params.playerType = 'jw';
            } else if (h5p) {
                playerElement = h5p;
                this.params.playerType = 'h5p';
            } else if (youtube) {
                const youtubeSrc = youtube.src;
                if (youtubeSrc.search(/enablejsapi/i) === -1) {
                    youtube.src = (youtubeSrc.search(/[?]/) === -1) ? youtubeSrc + '?enablejsapi=1' : youtubeSrc + '&enablejsapi=1';
                }
                playerElement = youtube;
                this.params.playerType = 'youtube';
            } else if (vimeo) {
                playerElement = vimeo;
                this.params.playerType = 'vimeo';
            } else if (wistia) {
                playerElement = wistia;
                this.params.playerType = 'wistia';
            } else if (html5) {
                playerElement = html5;
                this.params.playerType = 'html5';
            } else {
                return;
            }
            if (!playerElement.id || playerElement.id === '') {
                playerElement.id = 'annoto_player_id_' + Math.random().toString(36).substr(2, 6);
            }
            this.params.playerId = `#${playerElement.id}`;
            this.params.element = playerElement;

            return playerElement;
        },
        bootstrap: function() {
            if (this.bootsrapDone) {
                return;
            }

            const annotoPlayer = this.findPlayer.call(this);
            if (annotoPlayer) {
                this.bootsrapDone = true;
                require([this.params.bootstrapUrl], this.bootWidget.bind(this));
                log.info('AnnotoMoodle: detected ' + this.params.playerType + ':' + this.params.playerId);
            }
        },
        prepareConfig: function() {
            const config = this.config,
                params = this.params,
                nonOverlayTimelinePlayers = ['youtube', 'vimeo'];

            config.widgets[0].player.type = params.playerType;
            config.widgets[0].player.element = params.playerId;
            config.widgets[0].timeline = {
                overlay: (nonOverlayTimelinePlayers.indexOf(params.playerType) === -1),
            };
        },
        bootWidget: function() {
            const params = this.params;
            const config = {
                backend: {
                  domain: params.deploymentDomain
                },
                clientId: params.clientId,
                widgets: [{player: {}}],
                hooks: {
                    mediaDetails: function() {
                        return {
                            details: {
                                title: params.mediaTitle,
                                description: params.mediaDescription,
                            }
                        };
                    },
                    ssoAuthRequestHandle: function() {
                        window.location.replace(params.loginUrl);
                    },
                },
                group: {
                    id: params.mediaGroupId,
                    title: params.mediaGroupTitle,
                    description: params.mediaGroupDescription,
                },
                ... (params.locale) && {locale: params.locale},
            };

            this.config = config;

            this.prepareConfig.call(this);

            if (window.Annoto) {
                window.Annoto.on('ready', this.annotoReady.bind(this));
                if (this.params.playerType === 'videojs' && window.requirejs) {
                    const self = this;
                    window.require(['media_videojs/video-lazy'], function(vjs) {
                        self.config.widgets[0].player.params = {
                            videojs: vjs
                        };
                        window.Annoto.boot(self.config);
                    });
                } else {
                    window.Annoto.boot(this.config);
                }

            } else {
                log.warn('AnnotoMoodle: bootstrap didn`t load');
            }
        },

        annotoReady: function(api) {
            // Api is the API to be used after Annoot is setup
            // It can be used for SSO auth.
            this.annotoAPI = api;
            const jwt = this.params.userToken;
            log.info('AnnotoMoodle: annoto ready');
            if (api && jwt && jwt !== '') {
                api.auth(jwt).catch(function() {
                    log.error('AnnotoMoodle: SSO auth error');
                });
                this.checkWidgetVisibility();
            } else {
                log.info('AnnotoMoodle: SSO auth skipped');
            }
        },

        authKalturaPlayer: function(api) {
            // Api is the API to be used after Annoot is setup
            // It can be used for SSO auth.
            const jwt = this.params.userToken;
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
            for (let kdpMapKey in kdpMap) {
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
            kdp.player.kBind('annotoPluginReady', this.authKalturaPlayer.bind(this));
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
            const params = this.params;

            config.clientId = params.clientId;
            config.hooks = {
                getPageUrl: function() {
                    return window.location.href;
                },
                ssoAuthRequestHandle: function() {
                    window.location.replace(params.loginUrl);
                },
                mediaDetails: this.enrichMediaDetails.bind(this),
            };
            config.group = {
                id: params.mediaGroupId,
                title: params.mediaGroupTitle,
                description: params.mediaGroupDescription,
            };
            if (params.locale) {
                config.locale = params.locale;
            }
        },

        enrichMediaDetails: function(mediaParams) {
            // The details contains MediaDetails the plugin has managed to obtain
            // This hook gives a change to enrich the details, for example
            // providing group information for private discussions per course/playlist
            // https://github.com/Annoto/widget-api/blob/master/lib/media-details.d.ts#L6.
            // Annoto Kaltura plugin, already has some details about the media like title.
            //
            const retVal = (mediaParams && mediaParams.details) || {};

            retVal.title = retVal.title || this.params.mediaTitle;
            retVal.description = retVal.description || this.params.mediaDescription;

            return retVal;
        },

        checkWidgetVisibility: function() {
            const formatSelectors = {
                grid: 'body.format-grid .grid_section, body.format-grid #gridshadebox',
                topcoll: 'body.format-topcoll .ctopics.topics .toggledsection ',
                tabs: 'body.format-tabtopics .yui3-tab-panel',
                snap: 'body.format-topics.theme-snap .topics .section.main',
                modtab: '#page-mod-tab-view .TabbedPanelsContentGroup .TabbedPanelsContent'
            };
            let courseFormat = '',
                playerElement = this.params.element,
                self = this;

            if (typeof M.tabtopics !== 'undefined') {
                courseFormat = 'tabs';
            } else if (typeof M.format_grid !== 'undefined') {
                courseFormat = 'grid';
            } else if (typeof M.format_topcoll !== 'undefined') {
                    courseFormat = 'topcoll';
            } else if (typeof M.snapTheme !== 'undefined') {
                courseFormat = 'snap';
            } else if (document.body.id === 'page-mod-tab-view') {
                courseFormat = 'modtab';
            }

            const reloadAnnoto = function(mutationList) {
                let mutationTarget = null;

                if (mutationList) {
                      switch (courseFormat) {
                      case 'tabs':
                        mutationTarget = mutationList.filter(function(m) {
                          return m.target.classList.contains('yui3-tab-panel-selected');
                        })[0].target;
                        break;
                      case 'grid':
                        mutationTarget = mutationList.filter(function(m) {
                          return !m.target.classList.contains('hide_section');
                        })[0].target;
                        break;
                      case 'topcoll':
                        mutationTarget = mutationList[0].target;
                        break;
                      case 'snap':
                        mutationTarget = mutationList.filter(function(m) {
                          return m.target.classList.contains('state-visible');
                        })[0].target;
                        break;
                      case 'modtab':
                        mutationTarget = mutationList.filter(function(m) {
                          return m.target.classList.contains('TabbedPanelsContentVisible');
                        })[0].target;
                        break;
                    }
                }

                playerElement = self.findPlayer(mutationTarget);
                if (playerElement) {
                    self.params.element = playerElement;
                    self.prepareConfig();
                }

                self.annotoAPI.destroy().then(function() {
                    if (playerElement.offsetParent) {
                        self.annotoAPI.load(self.config, function(err) {
                            if (err) {
                                log.warn('AnnotoMoodle: Error while reloading Annoto configuration');
                                return;
                            }
                            log.info('AnnotoMoodle: Loaded new Configuration!');
                        });
                    }
                });
              };

            const observerNodeTargets = document.querySelectorAll(Object.values(formatSelectors).join(', '));

            if (observerNodeTargets.length > 0) {
                const observerConfig = {attributes: true, childList: true, subtree: false},
                    observer = new MutationObserver(reloadAnnoto);

                observerNodeTargets.forEach(function(target) {
                    observer.observe(target, observerConfig);
                });

                if (playerElement.offsetParent === null) {
                    reloadAnnoto();
                }
            }

        },

        setupWistiaIframeEmbed: function() {
            const wistiaplayers = document.querySelectorAll('iframe');
            const annotoIframeClient = "https://cdn.annoto.net/widget-iframe-api/latest/client.js";
            const targetHost = 'fast.wistia.net';
            const desiredParam = {
                name: 'plugin[annoto][src]',
                value: 'cdn.annoto.net'
            };

            wistiaplayers.forEach((iframe) => {
                const iframeSrc = new URL(iframe.src);
                const targetParam = iframeSrc.searchParams.get(desiredParam.name);
                if (iframeSrc.host !== targetHost) {
                    return;
                }

                if (targetParam && targetParam.match(desiredParam.value)) {
                    require([annotoIframeClient], this.setupWistiaIframeEmbedPlugin.bind(this, iframe));
                    return;
                }
            });
        },

        setupWistiaIframeEmbedPlugin: function(iframe, AnnotoIframeApi) {
            const params = this.params,
                annoto = new AnnotoIframeApi.Client(iframe);

            annoto.onSetup(function(next) {
                next({
                    clientId: params.clientId,
                    hooks: {
                        mediaDetails: function() {
                            return {
                                details: {
                                    title: params.mediaTitle,
                                    description: params.mediaDescription,
                                }
                            };
                        },
                        ssoAuthRequestHandle: function() {
                            window.location.replace(params.loginUrl);
                        },
                        getPageUrl: function() {
                            return window.location.href;
                        },
                    },
                    group: {
                        id: params.mediaGroupId,
                        title: params.mediaGroupTitle,
                        description: params.mediaGroupDescription,
                    },
                    ... (params.locale) && {locale: params.locale},
                });
            });

            annoto.onReady(function(api) {
                const token = params.userToken;
                api.auth(token, function(err) {
                    if (err) {
                        log.error('AnnotoMoodle: SSO auth error', err);
                    }
                });
            });
        }
    };
});
