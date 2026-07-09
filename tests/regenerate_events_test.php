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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for editor regenerate Moodle events (kit A09).
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor;

use local_dixeo\api\client;
use local_dixeo\dto\job_status;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;
use local_dixeo\service\module_generation_service;
use local_dixeo_editor\event\regenerate_cancelled;
use local_dixeo_editor\event\regenerate_completed;
use local_dixeo_editor\event\regenerate_started;
use local_dixeo_editor\external\cancel_regenerate_module_content;
use local_dixeo_editor\external\get_regenerate_module_content_status;
use local_dixeo_editor\external\start_regenerate_module_content;
use local_dixeo_editor\local\editor_session_repository;

/**
 * Regenerate lifecycle events must fire without content/instructions in other.
 *
 * @covers \local_dixeo_editor\event\regenerate_started
 * @covers \local_dixeo_editor\event\regenerate_completed
 * @covers \local_dixeo_editor\event\regenerate_cancelled
 * @covers \local_dixeo_editor\external\start_regenerate_module_content
 * @covers \local_dixeo_editor\external\get_regenerate_module_content_status
 * @covers \local_dixeo_editor\external\cancel_regenerate_module_content
 */
final class regenerate_events_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        if (!class_exists(service_factory::class)) {
            $this->markTestSkipped('local_dixeo hub is required');
        }
        service_factory::reset();
    }

    protected function tearDown(): void {
        if (class_exists(service_factory::class)) {
            service_factory::reset();
        }
        parent::tearDown();
    }

    /**
     * Assert event other has jobid only (no AI payload keys).
     *
     * @param \core\event\base $event
     * @param string $jobid
     */
    private function assert_minimal_job_other(\core\event\base $event, string $jobid): void {
        $this->assertSame($jobid, $event->other['jobid']);
        $this->assertArrayNotHasKey('instructions', $event->other);
        $this->assertArrayNotHasKey('content', $event->other);
        $this->assertArrayNotHasKey('message', $event->other);
        $this->assertArrayNotHasKey('prompt', $event->other);
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

    public function test_start_emits_regenerate_started(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Original</p>',
        ]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $modgenservice = $this->getMockBuilder(module_generation_service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['submit_edit_job'])
            ->getMock();
        $modgenservice->expects($this->once())
            ->method('submit_edit_job')
            ->with($this->callback(static function (array $payload): bool {
                return isset($payload['courseId'], $payload['moduleType'], $payload['instructions'], $payload['context'])
                    && $payload['moduleType'] === 'page'
                    && str_contains((string) $payload['instructions'], 'Make it clearer');
            }))
            ->willReturn(operation_result::pending('job-start-event', 'pending', 0));
        service_factory::set_test_module_generation_service($modgenservice);

        $this->setUser($user);
        $sessionid = $this->create_editor_session((int) $cm->id, (int) $user->id);
        $sink = $this->redirectEvents();
        $result = start_regenerate_module_content::execute((int) $cm->id, 'Make it clearer', $sessionid);

        $this->assertTrue($result['success']);
        $this->assertSame('job-start-event', $result['data']['jobid']);

        $started = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof regenerate_started
        ));
        $this->assertCount(1, $started);
        $this->assertEquals((int) $cm->id, (int) $started[0]->objectid);
        $this->assertEquals((int) $course->id, (int) $started[0]->courseid);
        $this->assertEquals((int) $user->id, (int) $started[0]->userid);
        $this->assert_minimal_job_other($started[0], 'job-start-event');
    }

    public function test_status_completed_emits_regenerate_completed_without_content(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Original</p>',
        ]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $repo = new job_repository();
        $repo->register('job-complete-event', (int) $course->id, (int) $user->id, 'default', 'module_edit');

        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with('job-complete-event')
            ->willReturn(new job_status(
                jobid: 'job-complete-event',
                type: 'module',
                status: 'completed',
                progress: 100,
                createdat: time(),
                result: [
                    'data' => [
                        'content' => '<p>Secret regenerated body</p>',
                    ],
                ]
            ));
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        $this->setUser($user);
        $sessionid = $this->create_editor_session((int) $cm->id, (int) $user->id);
        $sink = $this->redirectEvents();
        $result = get_regenerate_module_content_status::execute((int) $cm->id, 'job-complete-event', $sessionid);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['data']['status']);
        $this->assertStringContainsString('Secret regenerated body', $result['data']['content']);

        $completed = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof regenerate_completed
        ));
        $this->assertCount(1, $completed);
        $this->assertEquals((int) $cm->id, (int) $completed[0]->objectid);
        $this->assert_minimal_job_other($completed[0], 'job-complete-event');
        $this->assertStringNotContainsString('Secret', $completed[0]->get_description());
    }

    public function test_cancel_emits_regenerate_cancelled(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', [
            'course' => $course->id,
            'content' => '<p>Original</p>',
        ]);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);

        $repo = new job_repository();
        $repo->register('job-cancel-event', (int) $course->id, (int) $user->id, 'default', 'module_edit');

        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('/v1/jobs/job-cancel-event/cancel', [])
            ->willReturn(['status' => 'cancelled']);
        service_factory::set_test_job_service(new job_service($client, null, $repo));

        $this->setUser($user);
        $sink = $this->redirectEvents();
        $result = cancel_regenerate_module_content::execute((int) $cm->id, 'job-cancel-event');

        $this->assertTrue($result['success']);

        $cancelled = array_values(array_filter(
            $sink->get_events(),
            static fn($event) => $event instanceof regenerate_cancelled
        ));
        $this->assertCount(1, $cancelled);
        $this->assertEquals((int) $cm->id, (int) $cancelled[0]->objectid);
        $this->assertEquals((int) $user->id, (int) $cancelled[0]->userid);
        $this->assert_minimal_job_other($cancelled[0], 'job-cancel-event');
    }
}
