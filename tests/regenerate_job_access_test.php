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

/**
 * Tests for editor regenerate job course + initiator binding (MF-SEC-004).
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor;

use local_dixeo\api\client;
use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;
use local_dixeo_editor\external\cancel_regenerate_module_content;
use local_dixeo_editor\external\get_regenerate_module_content_status;
use local_dixeo_editor\local\editor_session_repository;
use local_dixeo_editor\local\external_error;

/**
 * Editor job access: course + initiating userid.
 *
 * @covers \local_dixeo_editor\external\get_regenerate_module_content_status
 * @covers \local_dixeo_editor\external\cancel_regenerate_module_content
 */
final class regenerate_job_access_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        service_factory::reset();
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Enrol an editing teacher (includes local/dixeo:edit via archetype).
     *
     * @param \stdClass $course Course record.
     * @return \stdClass User record.
     */
    private function create_editor_user(\stdClass $course): \stdClass {
        return $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
    }

    /**
     * Create an active editor session for a course module and user.
     *
     * @param int $cmid Course-module id.
     * @param int $userid User id.
     * @return int Editor session id.
     */
    private function create_editor_session(int $cmid, int $userid): int {
        return (int) editor_session_repository::get_or_create_active($cmid, null, $userid)->id;
    }

    public function test_status_rejects_foreign_course_job_without_content(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();
        $user = $this->create_editor_user($course);
        $page = $generator->create_module('page', ['course' => $course->id, 'content' => '<p>A</p>']);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $repo = new job_repository();
        $repo->register('job-foreign', (int) $othercourse->id, (int) $user->id, 'default', 'module_edit');

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('get');
        service_factory::set_test_job_service(new job_service($client, null, $repo));

        $this->setUser($user);
        $sessionid = $this->create_editor_session((int) $cm->id, (int) $user->id);
        $result = get_regenerate_module_content_status::execute((int) $cm->id, 'job-foreign', $sessionid);
        $this->assertDebuggingCalled();

        $this->assertFalse($result['success']);
        $this->assertSame(external_error::generic_message(), $result['error']['message']);
        $this->assertArrayNotHasKey('data', $result);
    }

    public function test_status_rejects_same_course_peer_job_without_content(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $this->create_editor_user($course);
        $peer = $this->create_editor_user($course);
        $page = $generator->create_module('page', ['course' => $course->id, 'content' => '<p>Secret</p>']);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $repo = new job_repository();
        $repo->register('job-peer', (int) $course->id, (int) $owner->id, 'default', 'module_edit');

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->never())->method('get_job_status');
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        $this->setUser($peer);
        $sessionid = $this->create_editor_session((int) $cm->id, (int) $peer->id);
        $result = get_regenerate_module_content_status::execute((int) $cm->id, 'job-peer', $sessionid);
        $this->assertDebuggingCalled();

        $this->assertFalse($result['success']);
        $this->assertSame(external_error::generic_message(), $result['error']['message']);
        $encoded = json_encode($result);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('Secret', $encoded);
    }

    public function test_status_allows_owner(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $this->create_editor_user($course);
        $page = $generator->create_module('page', ['course' => $course->id, 'content' => '<p>A</p>']);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $repo = new job_repository();
        $repo->register('job-mine', (int) $course->id, (int) $owner->id, 'default', 'module_edit');

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with('job-mine')
            ->willReturn(new job_status(
                jobid: 'job-mine',
                type: 'module',
                status: 'processing',
                progress: 25,
                createdat: time()
            ));
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        $this->setUser($owner);
        $sessionid = $this->create_editor_session((int) $cm->id, (int) $owner->id);
        $result = get_regenerate_module_content_status::execute((int) $cm->id, 'job-mine', $sessionid);

        $this->assertTrue($result['success']);
        $this->assertSame('job-mine', $result['data']['jobid']);
        $this->assertSame('processing', $result['data']['status']);
        $this->assertSame(25, $result['data']['progress']);
    }

    public function test_cancel_rejects_same_course_peer(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $this->create_editor_user($course);
        $peer = $this->create_editor_user($course);
        $page = $generator->create_module('page', ['course' => $course->id, 'content' => '<p>A</p>']);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $repo = new job_repository();
        $repo->register('job-cancel-peer', (int) $course->id, (int) $owner->id, 'default', 'module_edit');

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        service_factory::set_test_job_service(new job_service($client, null, $repo));

        $this->setUser($peer);
        $result = cancel_regenerate_module_content::execute((int) $cm->id, 'job-cancel-peer');
        $this->assertDebuggingCalled();

        $this->assertFalse($result['success']);
        $this->assertSame(external_error::generic_message(), $result['error']['message']);
    }

    public function test_cancel_allows_owner(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $this->create_editor_user($course);
        $page = $generator->create_module('page', ['course' => $course->id, 'content' => '<p>A</p>']);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $repo = new job_repository();
        $repo->register('job-cancel-mine', (int) $course->id, (int) $owner->id, 'default', 'module_edit');

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('/v1/jobs/job-cancel-mine/cancel', [])
            ->willReturn(['status' => 'cancelled']);
        service_factory::set_test_job_service(new job_service($client, null, $repo));

        $this->setUser($owner);
        $result = cancel_regenerate_module_content::execute((int) $cm->id, 'job-cancel-mine');

        $this->assertTrue($result['success']);
        $this->assertSame('job-cancel-mine', $result['data']['jobid']);
    }
}
