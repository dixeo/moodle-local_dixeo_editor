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
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;
use local_dixeo_editor\external\cancel_regenerate_module_content;
use local_dixeo_editor\external\discard_editor_session;
use local_dixeo_editor\external\get_editor_draft_image_status;
use local_dixeo_editor\external\get_regenerate_module_content_status;
use local_dixeo_editor\external\start_regenerate_module_content;
use local_dixeo_editor\local\editor_session_repository;
use local_dixeo_editor\local\external_error;

/**
 * Editor job and session access: course, course module, initiating user, component and active session.
 *
 * @covers \local_dixeo_editor\external\get_regenerate_module_content_status
 * @covers \local_dixeo_editor\external\cancel_regenerate_module_content
 * @covers \local_dixeo_editor\external\start_regenerate_module_content
 * @covers \local_dixeo_editor\external\discard_editor_session
 * @covers \local_dixeo_editor\external\get_editor_draft_image_status
 * @covers \local_dixeo_editor\local\editor_job_access
 * @covers \local_dixeo_editor\local\editor_session_repository
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

    /**
     * Register an editor regenerate job bound to a course module.
     *
     * @param job_repository $repo Job repository.
     * @param string $jobid Job id.
     * @param \stdClass $course Course record.
     * @param int $userid Initiating user id.
     * @param int $cmid Course module id.
     * @param string $component Originating component.
     */
    private function register_editor_job(
        job_repository $repo,
        string $jobid,
        \stdClass $course,
        int $userid,
        int $cmid,
        string $component = 'local_dixeo_editor'
    ): void {
        $repo->register(
            $jobid,
            (int) $course->id,
            $userid,
            'default',
            'module_edit',
            $component,
            job_binding_metadata::for_module('page', $cmid)
        );
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
        $this->register_editor_job($repo, 'job-peer', $course, (int) $owner->id, (int) $cm->id);

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
        $this->register_editor_job($repo, 'job-mine', $course, (int) $owner->id, (int) $cm->id);

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
        $this->register_editor_job($repo, 'job-cancel-peer', $course, (int) $owner->id, (int) $cm->id);

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
        $this->register_editor_job($repo, 'job-cancel-mine', $course, (int) $owner->id, (int) $cm->id);

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

    /**
     * A job started on one page cannot be read or cancelled through another page of the same course.
     */
    public function test_status_and_cancel_reject_job_of_another_module(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $this->create_editor_user($course);
        $pagea = $generator->create_module('page', ['course' => $course->id]);
        $pageb = $generator->create_module('page', ['course' => $course->id]);

        $repo = new job_repository();
        $this->register_editor_job($repo, 'job-on-a', $course, (int) $owner->id, (int) $pagea->cmid);
        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->never())->method('get_job_status');
        service_factory::set_test_job_service(new job_service($client, $poller, $repo));

        $this->setUser($owner);
        $sessionb = $this->create_editor_session((int) $pageb->cmid, (int) $owner->id);
        $status = get_regenerate_module_content_status::execute((int) $pageb->cmid, 'job-on-a', $sessionb);
        $this->assertDebuggingCalled();
        $cancel = cancel_regenerate_module_content::execute((int) $pageb->cmid, 'job-on-a');
        $this->assertDebuggingCalled();

        foreach ([$status, $cancel] as $result) {
            $this->assertFalse($result['success']);
            $this->assertSame(external_error::generic_message(), $result['error']['message']);
            $this->assertArrayNotHasKey('data', $result);
        }
    }

    /**
     * A job of the same user, course and module started by another Dixeo plugin is rejected.
     */
    public function test_status_rejects_job_of_another_component(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $this->create_editor_user($course);
        $page = $generator->create_module('page', ['course' => $course->id]);

        $repo = new job_repository();
        $this->register_editor_job($repo, 'job-modulegen', $course, (int) $owner->id, (int) $page->cmid, 'block_dixeo_modulegen');
        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->never())->method('get_job_status');
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        $this->setUser($owner);
        $sessionid = $this->create_editor_session((int) $page->cmid, (int) $owner->id);
        $result = get_regenerate_module_content_status::execute((int) $page->cmid, 'job-modulegen', $sessionid);
        $this->assertDebuggingCalled();

        $this->assertFalse($result['success']);
        $this->assertSame(external_error::generic_message(), $result['error']['message']);
    }

    /**
     * A session of one page is refused by every session-bound service called for another page.
     */
    public function test_session_of_another_module_is_rejected(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $this->create_editor_user($course);
        $pagea = $generator->create_module('page', ['course' => $course->id]);
        $pageb = $generator->create_module('page', ['course' => $course->id]);
        $generator->get_plugin_generator('local_dixeo')->create_terms_acceptance(['userid' => $owner->id]);
        $repo = new job_repository();
        $this->register_editor_job($repo, 'job-on-b', $course, (int) $owner->id, (int) $pageb->cmid);
        service_factory::set_test_job_service(new job_service($this->createMock(client::class), null, $repo));

        $this->setUser($owner);
        $sessiona = $this->create_editor_session((int) $pagea->cmid, (int) $owner->id);
        $cmidb = (int) $pageb->cmid;

        $this->assert_session_refused('nopermissions', fn() => get_regenerate_module_content_status::execute(
            $cmidb,
            'job-on-b',
            $sessiona
        ));
        $this->assert_session_refused('nopermissions', fn() => start_regenerate_module_content::execute(
            $cmidb,
            'Make it clearer',
            $sessiona
        ));
        $this->assert_session_refused('nopermissions', fn() => discard_editor_session::execute($cmidb, $sessiona));
        $this->assert_session_refused('nopermissions', fn() => get_editor_draft_image_status::execute(
            $cmidb,
            $sessiona,
            [\core\uuid::generate()]
        ));
        $this->assertSame(
            editor_session_repository::STATUS_ACTIVE,
            $DB->get_field(editor_session_repository::TABLE, 'status', ['id' => $sessiona])
        );
    }

    /**
     * Discarded and saved sessions are refused as expired.
     */
    public function test_inactive_sessions_are_rejected(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $owner = $this->create_editor_user($course);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->setUser($owner);
        $cmid = (int) $page->cmid;

        $discarded = $this->create_editor_session($cmid, (int) $owner->id);
        editor_session_repository::set_status($discarded, editor_session_repository::STATUS_DISCARDED);
        $this->assert_session_refused('editorsessionexpired', fn() => discard_editor_session::execute($cmid, $discarded));

        $saved = $this->create_editor_session($cmid, (int) $owner->id);
        editor_session_repository::set_status($saved, editor_session_repository::STATUS_SAVED);
        $this->assert_session_refused('editorsessionexpired', fn() => get_editor_draft_image_status::execute(
            $cmid,
            $saved,
            [\core\uuid::generate()]
        ));
    }

    /**
     * A session of one slide is refused for another slide or for the activity without a slide.
     */
    public function test_session_of_another_slide_is_rejected(): void {
        $userid = 7;
        $session = editor_session_repository::get_or_create_active(50, 3, $userid);

        $this->assertSame(
            (int) $session->id,
            (int) editor_session_repository::require_active_owned((int) $session->id, $userid, 50, 3)->id
        );
        foreach ([[50, 4], [50, null], [51, 3]] as [$cmid, $slideid]) {
            try {
                editor_session_repository::require_active_owned((int) $session->id, $userid, $cmid, $slideid);
                $this->fail('A session of another slide or module must be refused.');
            } catch (\moodle_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
        $this->expectException(\moodle_exception::class);
        editor_session_repository::require_active_owned((int) $session->id, $userid + 1, 50, 3);
    }

    /**
     * Assert that a call stops with the given session error.
     *
     * @param string $errorcode Expected error code.
     * @param callable $call Call to run.
     */
    private function assert_session_refused(string $errorcode, callable $call): void {
        try {
            $call();
            $this->fail('The session must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }
}
