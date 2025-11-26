<?php
// This file is part of Moodle - https://moodle.org/
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

/**
 * Tests for local_annoto_get_user_token.
 *
 * @package    local_annoto
 * @copyright  2025 Avi Levy <avi@sysbind.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for local_annoto_get_user_token functionality.
 *
 * @covers ::local_annoto_get_user_token
 * @covers ::local_annoto_get_user_scope
 * @covers ::local_annoto_has_capability
 */
final class get_user_token_test extends \advanced_testcase {
    /** @var stdClass $course Test course. */
    private $course;

    /**
     * Standard setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Create a basic course used by tests.
        $this->course = self::getDataGenerator()->create_course();

        // Ensure $PAGE has a context to generate user pictures.
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * Helper to decode a JWT payload without verifying signature.
     *
     * @param string $jwt The encoded token.
     * @return array Decoded payload as associative array.
     */
    private static function decode_jwt_payload(string $jwt): array {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }
        $payloadb64 = $parts[1];
        $payloadb64 = strtr($payloadb64, '-_', '+/');
        $padding = strlen($payloadb64) % 4;
        if ($padding > 0) {
            $payloadb64 .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode($payloadb64);
        $data = json_decode((string)$json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Tests that an empty string is returned when the user is not logged in.
     *
     * @covers \local_annoto_get_user_token
     */
    public function test_returns_empty_string_when_user_not_logged_in(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/annoto/lib.php');

        // Ensure not logged in.
        $this->setUser(0);

        $settings = (object) [
            'clientid' => 'cid',
            'ssosecret' => 'secret',
            'moderatorroles' => '',
        ];

        $token = local_annoto_get_user_token($settings, $this->course->id);
        $this->assertSame('', $token);
    }

    /**
     * Test that the token contains the expected claims for a regular user.
     *
     * This test verifies:
     * - Token generation for a logged-in user.
     * - Decodability of the token payload.
     * - Presence and correctness of basic identity claims (jti, name, email).
     * - Correct issuer and scope of the token (regular user scope).
     * - Proper expiration time within approximately 20 minutes.
     * - Presence and type of the photo URL in the token payload.
     *
     * @covers ::local_annoto_get_user_token
     * @covers ::fullname
     * @covers ::self::decode_jwt_payload
     */
    public function test_token_contains_expected_claims_for_regular_user(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/annoto/lib.php');

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        $settings = (object) [
            'clientid' => 'client123',
            'ssosecret' => 'topsecret',
            // Empty allowed roles → user should NOT be moderator.
            'moderatorroles' => '',
        ];

        $before = time();
        $token = local_annoto_get_user_token($settings, $this->course->id);
        $after = time();

        $this->assertNotEmpty($token, 'Token should be generated for logged-in user.');

        $payload = self::decode_jwt_payload($token);
        $this->assertNotEmpty($payload, 'Payload should be decodable.');

        // Basic identity claims.
        $this->assertEquals($user->id, $payload['jti']);
        $this->assertEquals(fullname($user), $payload['name']);
        $this->assertEquals($user->email, $payload['email']);

        // Issuer and scope.
        $this->assertEquals('client123', $payload['iss']);
        $this->assertEquals('user', $payload['scope']);

        // Expiration approximately 20 minutes ahead.
        $this->assertGreaterThanOrEqual($before + 60 * 19, $payload['exp']);
        $this->assertLessThanOrEqual($after + 60 * 21, $payload['exp']);

        // Photo URL should be a string (may be empty on some setups, but usually present).
        $this->assertArrayHasKey('photoUrl', $payload);
        $this->assertIsString($payload['photoUrl']);
    }

    /**
     * Tests that the token scope is set to 'super-mod' for a user with moderator permissions.
     *
     * This test verifies the functionality of the `local_annoto_get_user_token` function
     * by checking the generated token's payload scope for a user assigned a moderator role.
     *
     * @covers ::local_annoto_get_user_token
     * @covers ::decode_jwt_payload
     */
    public function test_token_scope_is_super_mod_for_moderator(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/annoto/lib.php');

        // Create a user and a role that will be allowed to moderate.
        $user = self::getDataGenerator()->create_user();
        $roleid = self::getDataGenerator()->create_role();

        $coursecontext = \context_course::instance($this->course->id);

        // Assign the role to the user in course context.
        role_assign($roleid, $user->id, $coursecontext->id);

        // Explicitly allow the capability for this role (system context is fine for tests).
        assign_capability('local/annoto:moderatediscussion', CAP_ALLOW, $roleid, \context_system::instance());

        // Login as the user AFTER role/cap changes to ensure session reflects permissions.
        $this->setUser($user);

        // Settings: mark the role as allowed moderator role.
        $settings = (object) [
            'clientid' => 'client123',
            'ssosecret' => 'topsecret',
            'moderatorroles' => (string)$roleid,
        ];

        $token = local_annoto_get_user_token($settings, $this->course->id);
        $this->assertNotEmpty($token);

        $payload = self::decode_jwt_payload($token);
        $this->assertEquals('super-mod', $payload['scope']);
    }
}
