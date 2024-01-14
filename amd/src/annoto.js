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
 * @package
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
  'jquery',
  'core/log',
  'core/notification',
  'core/ajax',
  'https://player.vimeo.com/api/player.js',
  'media_videojs/video-lazy',
], function($, log, notification, Ajax, VimeoPlayer, videojs) {

    const moodleAnnotoExports = {
        $,
        log,
        notification,
        Ajax,
        VimeoPlayer,
        videojs,
        require,
    };
    window.moodleAnnoto = window.moodleAnnoto ? Object.assign(window.moodleAnnoto, moodleAnnotoExports) : moodleAnnotoExports;

    try {
        if (window.sessionStorage.getItem('moodleAnnotoDebug')) {
            log = console;
        }
    } catch (err) {}

    return {
        init: function(courseid, modid) {

            log.info('AnnotoMoodle: starting plugin init');
            Ajax.call([{
                methodname: 'get_jsparams',
                args: {
                    courseid: courseid,
                    modid: modid
                },
                done: function(response) {
                    if (!response.result) {
                        log.warn('AnnotoMoodle: action not permitted for user');
                        return;
                    }
                    const params = JSON.parse(response.params);
                    window.moodleAnnoto.params = params;

                    const annotoMoodleCdnUrl = params.annotoMoodleCdnUrl ||
                        'https://cdn.annoto.net/moodle-local-js/latest/annoto.js';
                    require([annotoMoodleCdnUrl], function(AnnotoMooodle) {
                        window.AnnotoMooodle = window.AnnotoMooodle || AnnotoMooodle;
                        AnnotoMooodle.setup();
                    });
                }.bind(this),
                fail: notification.exception,
            }]);
        },
    };
});
