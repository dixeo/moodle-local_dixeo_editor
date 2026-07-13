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

    public function test_get_metadata_declares_preference_and_external_link(): void {
        $this->resetAfterTest(true);

        $collection = new collection('local_dixeo_editor');
        $updated = provider::get_metadata($collection);
        $items = $updated->get_collection();

        $this->assertCount(2, $items);

        $names = array_map(static fn($item) => $item->get_name(), $items);
        $this->assertContains('local_dixeo_editor_content_panel_state', $names);
        $this->assertContains('dixeo_api', $names);
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
