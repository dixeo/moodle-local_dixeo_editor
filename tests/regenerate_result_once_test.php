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

namespace local_dixeo_editor;

use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\image\content\decode_result;
use local_dixeo\service\image\content\editor_draft_fileareas;
use local_dixeo\service\job_service;
use local_dixeo_editor\event\regenerate_completed;
use local_dixeo_editor\external\get_regenerate_module_content_status;
use local_dixeo_editor\local\editor_session_repository;
use local_dixeo_editor\local\session_result_store;

/**
 * A completed regenerate job is applied once per editor session.
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo_editor\external\get_regenerate_module_content_status
 * @covers     \local_dixeo_editor\local\session_result_store
 * @covers     \local_dixeo_editor\local\editor_session_repository
 */
final class regenerate_result_once_test extends \advanced_testcase {
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
     * Polling a completed job twice queues its images once and logs one completion.
     */
    public function test_second_completed_poll_repeats_no_side_effect(): void {
        global $DB;

        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cmid = (int) $page->cmid;

        $repo = new job_repository();
        $repo->register(
            'job-twice',
            (int) $course->id,
            (int) $user->id,
            'default',
            'module_edit',
            'local_dixeo_editor',
            job_binding_metadata::for_module('page', $cmid)
        );
        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->exactly(2))->method('get_job_status')->willReturn(new job_status(
            jobid: 'job-twice',
            type: 'module',
            status: 'completed',
            progress: 100,
            createdat: time(),
            result: ['data' => ['content' => '<p>Body</p>[img-gen prompt="A volcano"][img-gen prompt="A glacier"]']]
        ));
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        $this->setUser($user);
        $sessionid = (int) editor_session_repository::get_or_create_active($cmid, null, (int) $user->id)->id;
        $sink = $this->redirectEvents();

        $first = get_regenerate_module_content_status::execute($cmid, 'job-twice', $sessionid);
        $second = get_regenerate_module_content_status::execute($cmid, 'job-twice', $sessionid);

        $this->assertSame('completed', $first['data']['status']);
        $this->assertSame('completed', $second['data']['status']);
        $this->assertCount(2, $first['data']['pendingplaceholderids']);
        $this->assertSame($first['data']['pendingplaceholderids'], $second['data']['pendingplaceholderids']);
        $this->assertSame($first['data']['content'], $second['data']['content']);
        $this->assertSame(2, $DB->count_records('local_dixeo_image_job'));
        $this->assertSame(2, $DB->count_records('local_dixeo_image_job', ['status' => 'pending']));
        $this->assertSame(2, $this->count_session_files(\context_module::instance($cmid)->id, $sessionid));
        $completed = array_filter($sink->get_events(), static fn($event) => $event instanceof regenerate_completed);
        $this->assertCount(1, $completed);
    }

    /**
     * The session store returns what it stored for the same job only.
     */
    public function test_session_result_store_round_trip(): void {
        $sessionid = (int) editor_session_repository::get_or_create_active(5, null, 2)->id;
        $store = new session_result_store();
        $ids = [\core\uuid::generate(), \core\uuid::generate()];

        $this->assertNull($store->get_applied_result($sessionid, 'job-a'));
        $store->store_applied_result($sessionid, 'job-a', new decode_result('<p>Stored</p>', $ids, ['ignored']));

        $stored = $store->get_applied_result($sessionid, 'job-a');
        $this->assertSame('<p>Stored</p>', $stored->html);
        $this->assertSame($ids, $stored->newplaceholderids);
        $this->assertSame([], $stored->restoredplaceholderids);
        $this->assertNull($store->get_applied_result($sessionid, 'job-b'));
    }

    /**
     * Invalid stored placeholder data reads as an empty list.
     */
    public function test_invalid_stored_placeholder_list_reads_as_empty(): void {
        global $DB;

        $sessionid = (int) editor_session_repository::get_or_create_active(5, null, 2)->id;
        $DB->update_record(editor_session_repository::TABLE, (object) [
            'id' => $sessionid,
            'resultjobid' => 'job-a',
            'resulthtml' => '<p>Stored</p>',
            'resultplaceholderids' => '{not json',
        ]);

        $stored = (new session_result_store())->get_applied_result($sessionid, 'job-a');

        $this->assertSame([], $stored->newplaceholderids);
    }

    /**
     * Saving or discarding a session clears its stored result.
     */
    public function test_saved_and_discarded_sessions_clear_the_result(): void {
        global $DB;

        $store = new session_result_store();
        foreach ([editor_session_repository::STATUS_SAVED, editor_session_repository::STATUS_DISCARDED] as $status) {
            $sessionid = (int) editor_session_repository::get_or_create_active(5, null, 2)->id;
            $store->store_applied_result($sessionid, 'job-a', new decode_result('<p>Stored</p>', ['x'], []));

            editor_session_repository::set_status($sessionid, $status);

            $record = $DB->get_record(editor_session_repository::TABLE, ['id' => $sessionid], '*', MUST_EXIST);
            $this->assertSame($status, $record->status);
            $this->assertNull($record->resultjobid);
            $this->assertNull($record->resulthtml);
            $this->assertNull($record->resultplaceholderids);
        }
    }

    /**
     * Number of files in the editor draft area of a session.
     *
     * @param int $contextid Module context id.
     * @param int $sessionid Editor session id.
     * @return int
     */
    private function count_session_files(int $contextid, int $sessionid): int {
        return count(get_file_storage()->get_area_files(
            $contextid,
            editor_draft_fileareas::COMPONENT,
            editor_draft_fileareas::for_modname('page'),
            $sessionid,
            'id',
            false
        ));
    }
}
