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
 * Editor visibility when Dixeo is off.
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
use local_dixeo\service\module_generation_service;
use local_dixeo_editor\external\get_regenerate_module_content_status;
use local_dixeo_editor\external\regenerate_module_content;
use local_dixeo_editor\external\start_regenerate_module_content;
use local_dixeo_editor\local\editor_session_repository;
use local_dixeo_editor\output\embedded_editor;

/**
 * The editor stays out, and sends nothing to Dixeo, when Dixeo is off.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_dixeo_editor_extend_navigation_course
 * @covers     ::local_dixeo_editor_add_button_to_context_header
 * @covers     ::local_dixeo_editor_add_button_to_activity_menu
 * @covers     \local_dixeo_editor\external\start_regenerate_module_content
 * @covers     \local_dixeo_editor\external\regenerate_module_content
 * @covers     \local_dixeo_editor\external\get_regenerate_module_content_status
 * @covers     \local_dixeo_editor\output\embedded_editor
 */
final class enabled_gate_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Course editing does not load the editor menu while the Dixeo layer is off.
     */
    public function test_course_navigation_skips_editor_scripts_when_dixeo_is_off(): void {
        global $PAGE, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $page = $this->course_page($course);
        $PAGE = $page;
        $page->initialise_theme_and_output();
        $USER->editing = 1;

        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_system::instance(),
            '0'
        );

        local_dixeo_editor_extend_navigation_course(new \navigation_node('course'), $course, $context);
        $this->assertStringNotContainsString('local_dixeo_editor/action_menu_edit', $page->requires->get_end_code());

        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_system::instance(),
            '1'
        );
        $page = $this->course_page($course);
        $PAGE = $page;
        $page->initialise_theme_and_output();
        local_dixeo_editor_extend_navigation_course(new \navigation_node('course'), $course, $context);
        $this->assertStringContainsString('local_dixeo_editor/action_menu_edit', $page->requires->get_end_code());
    }

    /**
     * A page activity omits the edit button when that course is off.
     */
    public function test_header_button_is_empty_when_the_course_is_off(): void {
        global $OUTPUT, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $pagemod = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('page', $pagemod->cmid, 0, false, MUST_EXIST);
        $page = $this->course_page($course);
        $page->set_cm($cm, $course);
        $page->set_url(new \moodle_url('/mod/page/view.php', ['id' => $cm->id]));
        $PAGE = $page;
        $page->initialise_theme_and_output();
        $OUTPUT = $page->get_renderer('core');

        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($course->id),
            '0'
        );

        $this->assertSame('', local_dixeo_editor_add_button_to_context_header($page));
        $this->assertSame([], local_dixeo_editor_add_button_to_activity_menu($page));

        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($course->id),
            '1'
        );
        $button = local_dixeo_editor_add_button_to_context_header($page);
        $this->assertStringContainsString('content_edition.php', $button);
        $menu = local_dixeo_editor_add_button_to_activity_menu($page);
        $this->assertNotEmpty($menu);
        $this->assertStringContainsString('content_edition.php', (string) $menu[0]['url']);
    }

    /**
     * Both sending services stop with error:dixeo_disabled while the site is off, without a request or a job row.
     */
    public function test_sending_services_refuse_while_the_site_is_off(): void {
        [$cm, $teacher] = $this->editing_setup(true);
        $this->set_enabled(\context_system::instance(), '0');

        $this->assert_sending_services_refused($cm, $teacher);
    }

    /**
     * Both sending services stop with error:dixeo_disabled while the course is off.
     */
    public function test_sending_services_refuse_while_the_course_is_off(): void {
        [$cm, $teacher] = $this->editing_setup(true);
        $this->set_enabled(\context_course::instance($cm->course), '0');

        $this->assert_sending_services_refused($cm, $teacher);
    }

    /**
     * A teacher without accepted terms is told that Dixeo is off, not asked to accept the terms.
     */
    public function test_dixeo_off_is_reported_before_the_terms(): void {
        [$cm, $teacher] = $this->editing_setup(false);
        $this->set_enabled(\context_course::instance($cm->course), '0');

        $this->assert_sending_services_refused($cm, $teacher);
    }

    /**
     * The embedded slide editor is not rendered while Dixeo is off.
     */
    public function test_embedded_editor_refuses_while_dixeo_is_off(): void {
        global $DB;

        [$cm] = $this->editing_setup(true);
        $this->set_enabled(\context_course::instance($cm->course), '0');

        try {
            embedded_editor::render($cm, 1, \context_module::instance($cm->id));
            $this->fail('The embedded editor must not render while Dixeo is off.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:dixeo_disabled', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records(editor_session_repository::TABLE));
    }

    /**
     * A completed job read while Dixeo is off returns its content without image placeholders or image jobs.
     */
    public function test_completed_status_while_dixeo_is_off_strips_images(): void {
        global $DB;

        [$cm, $teacher] = $this->editing_setup(true);
        set_config('image_generation_enabled', 1, 'local_dixeo');
        set_config('image_generation_content_mode', 'generate', 'local_dixeo');
        $repo = new job_repository();
        $repo->register(
            'job-completed-off',
            (int) $cm->course,
            (int) $teacher->id,
            'default',
            'module_edit',
            'local_dixeo_editor',
            job_binding_metadata::for_module('page', (int) $cm->id)
        );
        $poller = $this->getMockBuilder(\local_dixeo\api\job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_job_status'])
            ->getMock();
        $poller->method('get_job_status')->willReturn(new job_status(
            jobid: 'job-completed-off',
            type: 'module',
            status: 'completed',
            progress: 100,
            createdat: time(),
            result: ['data' => ['content' => '<p>Edited</p>[img-gen prompt="A volcano"]']]
        ));
        service_factory::set_test_job_service(new job_service(null, $poller, $repo));
        $sessionid = (int) editor_session_repository::get_or_create_active((int) $cm->id, null, (int) $teacher->id)->id;
        $this->set_enabled(\context_course::instance($cm->course), '0');

        $result = get_regenerate_module_content_status::execute((int) $cm->id, 'job-completed-off', $sessionid);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['data']['status']);
        $this->assertStringContainsString('Edited', $result['data']['content']);
        $this->assertStringNotContainsString('[img-gen', $result['data']['content']);
        $this->assertStringNotContainsString('data-dixeo-img-gen', $result['data']['content']);
        $this->assertSame([], $result['data']['pendingplaceholderids']);
        $this->assertSame(0, $DB->count_records('local_dixeo_image_job'));
    }

    /**
     * Course, page and editing teacher, logged in.
     *
     * @param bool $acceptterms Whether the teacher has accepted the current terms.
     * @return array{0: \stdClass, 1: \stdClass} Course module and teacher.
     */
    private function editing_setup(bool $acceptterms): array {
        $this->resetAfterTest();
        service_factory::reset();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id, 'content' => '<p>Original</p>']);
        $cm = get_coursemodule_from_id('page', $page->cmid, 0, false, MUST_EXIST);
        if ($acceptterms) {
            $generator->get_plugin_generator('local_dixeo')->create_terms_acceptance(['userid' => $teacher->id]);
        }
        $this->setUser($teacher);
        return [$cm, $teacher];
    }

    /**
     * Assert that start and regenerate stop with error:dixeo_disabled and nothing is sent or registered.
     *
     * @param \stdClass $cm Course module.
     * @param \stdClass $teacher Acting teacher.
     */
    private function assert_sending_services_refused(\stdClass $cm, \stdClass $teacher): void {
        global $DB;

        $client = $this->createMock(client::class);
        $client->expects($this->never())->method('post');
        $client->expects($this->never())->method('post_multipart');
        service_factory::set_test_module_generation_service(
            new module_generation_service(new job_service($client, null, new job_repository()))
        );
        $sessionid = (int) editor_session_repository::get_or_create_active((int) $cm->id, null, (int) $teacher->id)->id;
        $cmid = (int) $cm->id;

        $calls = [
            fn() => start_regenerate_module_content::execute($cmid, 'Make it clearer', $sessionid),
            fn() => regenerate_module_content::execute($cmid, 'Make it clearer', $sessionid, 0, '<p>Draft</p>'),
        ];
        foreach ($calls as $call) {
            try {
                $call();
                $this->fail('A sending service must stop while Dixeo is off.');
            } catch (\moodle_exception $e) {
                $this->assertSame('error:dixeo_disabled', $e->errorcode);
                $this->assertSame('local_dixeo', $e->module);
            }
        }
        $this->assertSame(0, $DB->count_records('local_dixeo_jobs'));
    }

    /**
     * Store the Dixeo switch at a context.
     *
     * @param \context $context System or course context.
     * @param string $value '1' or '0'.
     */
    private function set_enabled(\context $context, string $value): void {
        service_factory::get_settings_config()->set('local_dixeo', 'enabled', $context, $value, false);
    }

    /**
     * Course page the editor menu can attach to.
     *
     * @param \stdClass $course
     * @return \moodle_page
     */
    private function course_page(\stdClass $course): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_course::instance($course->id));
        $page->set_course($course);
        $page->set_pagelayout('course');
        $page->set_pagetype('course-view-topics');
        $page->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        return $page;
    }
}
