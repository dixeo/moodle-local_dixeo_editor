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

use local_dixeo\external\service_factory;
use local_dixeo\service\module_generation_service;
use local_dixeo\util\input_limits;
use local_dixeo_editor\external\regenerate_module_content;
use local_dixeo_editor\external\start_regenerate_module_content;
use local_dixeo_editor\local\editor_session_repository;

/**
 * Draft and autosave HTML larger than the edit limit is refused before anything is sent.
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo_editor\external\start_regenerate_module_content
 * @covers     \local_dixeo_editor\external\regenerate_module_content
 */
final class content_size_limit_test extends \advanced_testcase {
    /** @var \stdClass Course module of a page. */
    private \stdClass $cm;

    /** @var \stdClass Editing teacher with accepted terms. */
    private \stdClass $teacher;

    /** @var int Active editor session id. */
    private int $sessionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        service_factory::reset();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->teacher = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->cm = get_coursemodule_from_id('page', $page->cmid, 0, false, MUST_EXIST);
        $generator->get_plugin_generator('local_dixeo')->create_terms_acceptance(['userid' => $this->teacher->id]);
        $this->setUser($this->teacher);
        $this->sessionid = (int) editor_session_repository::get_or_create_active(
            (int) $this->cm->id,
            null,
            (int) $this->teacher->id
        )->id;

        $modgenservice = $this->getMockBuilder(module_generation_service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['submit_edit_job'])
            ->getMock();
        $modgenservice->expects($this->never())->method('submit_edit_job');
        service_factory::set_test_module_generation_service($modgenservice);
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Oversized posted draft HTML stops the synchronous service.
     */
    public function test_oversized_draft_html_is_refused(): void {
        $this->assert_too_long(fn() => regenerate_module_content::execute(
            (int) $this->cm->id,
            'Make it clearer',
            $this->sessionid,
            0,
            $this->oversized_html()
        ));
    }

    /**
     * Oversized Tiny autosave text stops the asynchronous start.
     */
    public function test_oversized_autosave_text_is_refused(): void {
        global $DB;

        $context = \context_module::instance($this->cm->id);
        $pagehash = sha1('limit' . $context->id);
        $DB->insert_record('tiny_autosave', [
            'elementid' => 'id_modulecontent',
            'contextid' => $context->id,
            'userid' => $this->teacher->id,
            'pagehash' => $pagehash,
            'drafttext' => $this->oversized_html(),
            'pageinstance' => 'testinstance',
            'timemodified' => time(),
        ]);

        $this->assert_too_long(fn() => start_regenerate_module_content::execute(
            (int) $this->cm->id,
            'Make it clearer',
            $this->sessionid,
            0,
            (int) $context->id,
            $pagehash,
            'id_modulecontent'
        ));
    }

    /**
     * A malformed autosave reference reaches the client as a parameter error.
     */
    public function test_invalid_autosave_reference_is_a_parameter_error(): void {
        $this->expectException(\invalid_parameter_exception::class);
        start_regenerate_module_content::execute(
            (int) $this->cm->id,
            'Make it clearer',
            $this->sessionid,
            0,
            (int) \context_module::instance($this->cm->id)->id,
            'not-hex',
            'id_modulecontent'
        );
    }

    /**
     * HTML one byte over the edit limit.
     *
     * @return string
     */
    private function oversized_html(): string {
        return '<p>' . str_repeat('x', input_limits::MAX_EDIT_CONTENT_BYTES - 6) . '</p>';
    }

    /**
     * Assert that a call stops with error:content_too_long and registers no job.
     *
     * @param callable $call Call to run.
     */
    private function assert_too_long(callable $call): void {
        global $DB;

        try {
            $call();
            $this->fail('Oversized content must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:content_too_long', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
        $this->assertSame(0, $DB->count_records('local_dixeo_jobs'));
    }
}
