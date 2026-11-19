<?php
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

namespace local_annoto;

use local_annoto\embed\helper;

/**
 * Tests for the Embed Annoto Media helper.
 *
 * @package    local_annoto
 * @copyright  Annoto Ltd.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_annoto\embed\helper
 */
final class embed_helper_test extends \advanced_testcase {
    /**
     * Creates a configured LTI 1.3 tool type on the Annoto EU host.
     *
     * @param string $name
     * @return \stdClass lti_types record
     */
    protected function create_embed_type(string $name = 'Annoto Media Embed'): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        $type = new \stdClass();
        $type->name = $name;
        $type->baseurl = 'https://eu.annoto.net/hosts/h1/lti/embed/select';
        $type->tooldomain = 'eu.annoto.net';
        $type->state = LTI_TOOL_STATE_CONFIGURED;
        $type->course = SITEID;
        $type->coursevisible = LTI_COURSEVISIBLE_NO;
        $type->ltiversion = LTI_VERSION_1P3;
        $type->clientid = 'clientid123';
        $type->createdby = 2;
        $type->timecreated = time();
        $type->timemodified = time();
        $type->id = $DB->insert_record('lti_types', $type);
        $DB->insert_record('lti_types_config', ['typeid' => $type->id, 'name' => 'toolurl', 'value' => $type->baseurl]);
        $DB->insert_record('lti_types_config', ['typeid' => $type->id, 'name' => 'initiatelogin',
            'value' => 'https://eu.annoto.net/hosts/h1/lti/login/i1']);
        return $DB->get_record('lti_types', ['id' => $type->id]);
    }

    public function test_host_matches_domain(): void {
        $this->assertTrue(helper::host_matches_domain('eu.annoto.net', 'eu.annoto.net'));
        $this->assertTrue(helper::host_matches_domain('app.eu.annoto.net', 'eu.annoto.net'));
        $this->assertFalse(helper::host_matches_domain('evileu.annoto.net', 'eu.annoto.net'));
        $this->assertFalse(helper::host_matches_domain('eu.annoto.net', ''));
    }

    public function test_clean_custom(): void {
        $this->assertSame(
            ['nn_activity_id' => 'A1'],
            helper::clean_custom(['nn_activity_id' => 'A1', 'bad key' => 'x', 'arr' => [1], 'nl' => "a\nb"])
        );
        $this->assertSame([], helper::clean_custom('nope'));
    }

    public function test_get_tool_type_auto_and_setting(): void {
        $this->resetAfterTest();
        set_config('deploymentdomain', 'eu.annoto.net', 'local_annoto');
        $this->assertNull(helper::get_tool_type());
        $dashboard = $this->create_embed_type('Annoto Dashboard');
        $dashboard->baseurl = 'https://eu.annoto.net/hosts/h1/lti/dashboard/launch';
        $GLOBALS['DB']->update_record('lti_types', $dashboard);
        $this->assertNull(helper::get_tool_type());
        $embed = $this->create_embed_type();
        $this->assertEquals($embed->id, helper::get_tool_type()->id);
        set_config('embedltitype', $dashboard->id, 'local_annoto');
        $this->assertEquals($dashboard->id, helper::get_tool_type()->id);
    }

    public function test_launch_url_validation_and_placeholder(): void {
        $this->resetAfterTest();
        $type = $this->create_embed_type();
        $this->assertTrue(helper::is_valid_launch_url('https://eu.annoto.net/hosts/h1/lti/embed/launch', $type));
        $this->assertFalse(helper::is_valid_launch_url('https://evil.example.com/hosts/h1/lti/embed/launch', $type));
        $this->assertFalse(helper::is_valid_launch_url('http://eu.annoto.net/hosts/h1/lti/embed/launch', $type));

        $items = json_encode(['@graph' => [[
            '@type' => 'LtiLinkItem',
            'url' => 'https://eu.annoto.net/hosts/h1/lti/embed/launch',
            'title' => 'Lecture <b>1</b>',
            'custom' => ['nn_activity_id' => 'A1'],
            'placementAdvice' => ['displayWidth' => 800, 'displayHeight' => 450],
        ]]]);
        $html = helper::placeholder_from_content_items($items, $type);
        $this->assertStringContainsString('class="local-annoto-embed"', $html);
        $this->assertStringContainsString('/local/annoto/embed/launch.php?', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertNull(helper::placeholder_from_content_items(json_encode(['@graph' => []]), $type));

        $this->expectException(\moodle_exception::class);
        helper::placeholder_from_content_items(json_encode(['@graph' => [['url' => 'https://evil.example.com/x']]]), $type);
    }

    public function test_session_launch_is_single_use(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        $type = $this->create_embed_type();
        $html = helper::initiate_login($type, SITEID, helper::KIND_DEEPLINK, $type->baseurl);
        $this->assertMatchesRegularExpression('/name="lti_message_hint" value="([^"]+)"/', $html);
        preg_match('/name="lti_message_hint" value="([^"]+)"/', $html, $m);
        $hint = html_entity_decode($m[1]);
        $this->assertTrue(helper::is_own_hint($hint));
        $data = helper::consume_launch($hint);
        $this->assertSame(helper::KIND_DEEPLINK, $data['kind']);
        $this->assertSame([], helper::consume_launch($hint));
        $this->assertNull(helper::consume_launch(json_encode(['launchid' => 'ltilaunch_123'])));
    }
}
