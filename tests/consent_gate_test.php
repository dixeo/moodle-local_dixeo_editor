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

namespace local_dixeo_editor;

use local_dixeo\api\client;
use local_dixeo\dto\job_status;
use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\job_service;
use local_dixeo\service\module_generation_service;
use local_dixeo_editor\external\cancel_regenerate_module_content;
use local_dixeo_editor\external\discard_editor_session;
use local_dixeo_editor\external\get_regenerate_module_content_status;
use local_dixeo_editor\external\regenerate_module_content;
use local_dixeo_editor\external\start_regenerate_module_content;
use local_dixeo_editor\activity\activity_adapter_factory;
use local_dixeo_editor\local\editor_session_repository;

/**
 * The editor sends course content to Dixeo only after the current Terms of Use are accepted.
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo_editor\external\start_regenerate_module_content
 * @covers     \local_dixeo_editor\external\regenerate_module_content
 * @covers     \local_dixeo_editor\external\cancel_regenerate_module_content
 * @covers     \local_dixeo_editor\external\get_regenerate_module_content_status
 * @covers     \local_dixeo_editor\external\discard_editor_session
 * @covers     \local_dixeo_editor\activity\base_activity_adapter
 */
final class consent_gate_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Editing teacher without accepted terms. */
    private \stdClass $teacher;

    /** @var \stdClass Course module of a page. */
    private \stdClass $cm;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        service_factory::reset();
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $this->course->id, 'content' => '<p>Original</p>']);
        $this->cm = get_coursemodule_from_instance('page', $page->id, $this->course->id, false, MUST_EXIST);
        $this->setUser($this->teacher);
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Both sending services refuse a capable teacher without accepted terms, before any job is submitted.
     */
    public function test_sending_services_refuse_without_accepted_terms(): void {
        $modgenservice = $this->getMockBuilder(module_generation_service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['submit_edit_job'])
            ->getMock();
        $modgenservice->expects($this->never())->method('submit_edit_job');
        service_factory::set_test_module_generation_service($modgenservice);
        $sessionid = $this->create_editor_session();
        $cmid = (int) $this->cm->id;

        $this->assert_consent_required(fn() => start_regenerate_module_content::execute($cmid, 'Make it clearer', $sessionid));
        $this->assert_consent_required(fn() => regenerate_module_content::execute($cmid, 'Make it clearer', $sessionid));
    }

    /**
     * After acceptance the start service submits the edit job.
     */
    public function test_start_reaches_the_service_after_acceptance(): void {
        $modgenservice = $this->getMockBuilder(module_generation_service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['submit_edit_job'])
            ->getMock();
        $modgenservice->expects($this->once())
            ->method('submit_edit_job')
            ->willReturn(operation_result::pending('job-after-terms', 'pending', 0));
        service_factory::set_test_module_generation_service($modgenservice);
        $this->accept_terms();

        $result = start_regenerate_module_content::execute((int) $this->cm->id, 'Make it clearer', $this->create_editor_session());

        $this->assertTrue($result['success']);
        $this->assertSame('job-after-terms', $result['data']['jobid']);
    }

    /**
     * After acceptance the synchronous service goes on to its session check.
     */
    public function test_regenerate_passes_the_gate_after_acceptance(): void {
        $this->accept_terms();

        try {
            regenerate_module_content::execute((int) $this->cm->id, 'Make it clearer', 0);
            $this->fail('A missing editor session must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }

    /**
     * Cancelling, reading status and discarding a session work without accepted terms.
     */
    public function test_stop_status_and_discard_do_not_require_terms(): void {
        $repo = new job_repository();
        $repo->register(
            'job-no-terms',
            (int) $this->course->id,
            (int) $this->teacher->id,
            'default',
            'module_edit',
            'local_dixeo_editor',
            \local_dixeo\dto\job_binding_metadata::for_module('page', (int) $this->cm->id)
        );
        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->expects($this->once())
            ->method('get_job_status')
            ->with('job-no-terms')
            ->willReturn(new job_status(
                jobid: 'job-no-terms',
                type: 'module',
                status: 'processing',
                progress: 10,
                createdat: time()
            ));
        $client = $this->createMock(client::class);
        $client->expects($this->once())
            ->method('post')
            ->with('/v1/jobs/job-no-terms/cancel', [])
            ->willReturn(['status' => 'cancelled']);
        service_factory::set_test_job_service(new job_service($client, $poller, $repo));
        $cmid = (int) $this->cm->id;
        $sessionid = $this->create_editor_session();

        $status = get_regenerate_module_content_status::execute($cmid, 'job-no-terms', $sessionid);
        $this->assertTrue($status['success']);
        $this->assertSame('processing', $status['data']['status']);
        $this->assertTrue(cancel_regenerate_module_content::execute($cmid, 'job-no-terms')['success']);
        $this->assertTrue(discard_editor_session::execute($cmid, $sessionid)['success']);
    }

    /**
     * Saving content drops image placeholders without queueing them until the terms are accepted.
     */
    public function test_save_queues_image_placeholders_only_with_accepted_terms(): void {
        global $DB;

        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
        $html = '<p>Before</p>[img-gen prompt="x"]<p>After</p>';

        $this->save_page_content($html);

        $stored = (string) $DB->get_field('page', 'content', ['id' => $this->cm->instance], MUST_EXIST);
        $this->assertStringContainsString('Before', $stored);
        $this->assertStringContainsString('After', $stored);
        $this->assertStringNotContainsString('[img-gen', $stored);
        $this->assertStringNotContainsString('data-dixeo-img-gen=', $stored);
        $this->assertSame(0, $DB->count_records('local_dixeo_image_job'));
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(\local_dixeo\task\poll_image_job::class));

        $this->accept_terms();
        $this->save_page_content($html);

        $stored = (string) $DB->get_field('page', 'content', ['id' => $this->cm->instance], MUST_EXIST);
        $this->assertStringContainsString('data-dixeo-img-gen=', $stored);
        $this->assertSame(1, $DB->count_records('local_dixeo_image_job'));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(\local_dixeo\task\poll_image_job::class));
    }

    /**
     * Save page content through the editor adapter with an active editor session, as the editor page does.
     *
     * @param string $html Submitted HTML.
     */
    private function save_page_content(string $html): void {
        global $DB;

        $adapter = (new activity_adapter_factory($DB))->create((int) $this->cm->id, null);
        $adapter->save_content($html, FORMAT_HTML, 0, [], $this->create_editor_session());
    }

    /**
     * Record acceptance of the current terms for the teacher.
     */
    private function accept_terms(): void {
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')
            ->create_terms_acceptance(['userid' => $this->teacher->id]);
    }

    /**
     * Create an active editor session for the page and the teacher.
     *
     * @return int Editor session id.
     */
    private function create_editor_session(): int {
        return (int) editor_session_repository::get_or_create_active((int) $this->cm->id, null, (int) $this->teacher->id)->id;
    }

    /**
     * Assert that a call stops with error:consentrequired from local_dixeo.
     *
     * @param callable $call Call to run.
     */
    private function assert_consent_required(callable $call): void {
        try {
            $call();
            $this->fail('Unaccepted terms must stop the request.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:consentrequired', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
    }
}
