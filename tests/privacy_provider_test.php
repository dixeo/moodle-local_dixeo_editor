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
 * Tests for the local_dixeo_editor privacy provider.
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use local_dixeo_editor\privacy\provider;

/**
 * PHPUnit tests for the local_dixeo_editor privacy provider.
 *
 * @covers \local_dixeo_editor\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    public function test_get_metadata_declares_session_files_preference_and_external_link(): void {
        $this->resetAfterTest(true);

        $collection = new collection('local_dixeo_editor');
        $updated = provider::get_metadata($collection);
        $items = $updated->get_collection();

        $this->assertCount(4, $items);

        $names = array_map(static fn($item) => $item->get_name(), $items);
        $this->assertContains('local_dixeo_editor_content_panel_state', $names);
        $this->assertContains('local_dixeo_editor_session', $names);
        $this->assertContains('core_files', $names);
        $this->assertContains('dixeo_api', $names);

        $external = null;
        foreach ($items as $item) {
            if ($item->get_name() === 'dixeo_api') {
                $external = $item;
                break;
            }
        }
        $this->assertNotNull($external);
        $fields = array_keys($external->get_privacy_fields());
        $externalfields = ['courseId', 'userId', 'instructions', 'context', 'drafthtml', 'imageprompt', 'moduleType', 'namespace'];
        foreach ($externalfields as $field) {
            $this->assertContains($field, $fields, "External metadata must declare {$field}");
        }

        $sessionfields = [];
        foreach ($items as $item) {
            if ($item->get_name() === 'local_dixeo_editor_session') {
                $sessionfields = array_keys($item->get_privacy_fields());
            }
        }
        foreach (['resultjobid', 'resulthtml', 'resultplaceholderids'] as $field) {
            $this->assertContains($field, $sessionfields, "Session metadata must declare {$field}");
        }

        foreach ($items as $item) {
            $this->assertTrue(get_string_manager()->string_exists($item->get_summary(), 'local_dixeo_editor'));
            foreach ($item->get_privacy_fields() ?? [] as $stringkey) {
                $this->assertTrue(
                    get_string_manager()->string_exists($stringkey, 'local_dixeo_editor'),
                    "Missing string {$stringkey}"
                );
            }
        }
    }

    public function test_export_user_data_includes_the_applied_result(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $sessionid = (int) local\editor_session_repository::get_or_create_active((int) $page->cmid, null, (int) $user->id)->id;
        (new local\session_result_store())->store_applied_result(
            $sessionid,
            'job-export',
            new \local_dixeo\service\image\content\decode_result('<p>Applied result</p>', [], [])
        );

        writer::reset();
        provider::export_user_data(new \core_privacy\local\request\approved_contextlist(
            $user,
            'local_dixeo_editor',
            [$context->id]
        ));

        $data = writer::with_context($context)->get_data([get_string('privacy:path:sessions', 'local_dixeo_editor')]);
        $this->assertCount(1, $data->sessions);
        $this->assertSame('job-export', $data->sessions[0]->resultjobid);
        $this->assertSame('<p>Applied result</p>', $data->sessions[0]->resulthtml);
    }

    public function test_export_user_preferences_exports_panel_layout(): void {
        global $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        writer::reset();
        set_user_preference('local_dixeo_editor_content_panel_state', 'dock-left', $USER->id);

        provider::export_user_preferences((int) $USER->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());

        $prefs = (array) $writer->get_user_preferences('local_dixeo_editor');
        $this->assertArrayHasKey('local_dixeo_editor_content_panel_state', $prefs);
        $this->assertEquals('dock-left', $prefs['local_dixeo_editor_content_panel_state']->value);
    }

    public function test_export_user_preferences_skips_when_unset(): void {
        $this->resetAfterTest(true);

        writer::reset();
        $user = $this->getDataGenerator()->create_user();
        provider::export_user_preferences((int) $user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
    }
}
