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
use local_dixeo\service\job_service;
use local_dixeo_editor\external\get_regenerate_module_content_status;
use local_dixeo_editor\local\editor_session_repository;

/**
 * A failed regenerate job reports a mapped message, never the provider's error text.
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo_editor\external\get_regenerate_module_content_status
 */
final class regenerate_status_error_test extends \advanced_testcase {
    /** @var string Provider error text that must not reach the browser. */
    private const SECRET = 'SECRET upstream text';

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
     * A known error type maps to its local_dixeo message.
     */
    public function test_known_error_type_maps_to_local_dixeo_message(): void {
        $result = $this->status_for_failed_job('upstream_ai');

        $this->assertSame('failed', $result['data']['status']);
        $this->assertSame(get_string('error:unavailable', 'local_dixeo'), $result['data']['errormessage']);
        $this->assertStringNotContainsString(self::SECRET, json_encode($result));
    }

    /**
     * An unknown error type maps to the editor's generic message.
     */
    public function test_unknown_error_type_maps_to_editor_generic_message(): void {
        $result = $this->status_for_failed_job('something_internal');

        $this->assertSame(get_string('error:generic', 'local_dixeo_editor'), $result['data']['errormessage']);
        $this->assertStringNotContainsString(self::SECRET, json_encode($result));
    }

    /**
     * A cancellation is still reported from the raw error code.
     */
    public function test_cancellation_is_read_from_the_raw_error_code(): void {
        $result = $this->status_for_failed_job('job_cancelled');

        $this->assertSame('cancelled', $result['data']['status']);
        $this->assertStringNotContainsString(self::SECRET, json_encode($result));
    }

    /**
     * Read the status of a failed regenerate job through the web service.
     *
     * @param string $errorcode Provider error type.
     * @return array Web service response.
     */
    private function status_for_failed_job(string $errorcode): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cmid = (int) $page->cmid;

        $repo = new job_repository();
        $repo->register(
            'job-failed',
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
        $poller->method('get_job_status')->willReturn(new job_status(
            jobid: 'job-failed',
            type: 'module',
            status: 'failed',
            progress: 0,
            createdat: time(),
            errorcode: $errorcode,
            errormessage: self::SECRET
        ));
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));

        $this->setUser($user);
        $sessionid = (int) editor_session_repository::get_or_create_active($cmid, null, (int) $user->id)->id;
        $result = get_regenerate_module_content_status::execute($cmid, 'job-failed', $sessionid);
        $this->assertDebuggingCalled();

        $this->assertTrue($result['success']);
        return $result;
    }
}
