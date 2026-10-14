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

/**
 * The editor scripts and header button stay out when Dixeo is off.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_dixeo_editor_extend_navigation_course
 * @covers     ::local_dixeo_editor_add_button_to_context_header
 * @covers     ::local_dixeo_editor_add_button_to_activity_menu
 */
final class enabled_gate_test extends \advanced_testcase {
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
        global $PAGE;

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
        $GLOBALS['OUTPUT'] = $page->get_renderer('core');

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
