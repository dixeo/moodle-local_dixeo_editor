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
 * Tests for activity_adapter_factory slideshow ownership validation.
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor;

use local_dixeo_editor\activity\activity_adapter_factory;
use local_dixeo_editor\activity\slideshow_slide_activity_adapter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

/**
 * PHPUnit tests for slideshow slide ownership validation in activity_adapter_factory.
 *
 * @covers \local_dixeo_editor\activity\activity_adapter_factory
 * @covers \local_dixeo_editor\activity\slideshow_slide_activity_adapter::assert_belongs_to_cm
 */
final class activity_adapter_factory_test extends \advanced_testcase {
    /**
     * Create a slideshow module with one slide for testing.
     *
     * @return array{0: \stdClass, 1: int} Course module and slide id.
     */
    private function create_slideshow_with_slide(): array {
        global $DB;

        if (!$DB->record_exists('modules', ['name' => 'slideshow'])) {
            $this->markTestSkipped('The mod_slideshow plugin is required for this test.');
        }

        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();

        [, , , , $data] = prepare_new_moduleinfo_data($course, 'slideshow', 0);
        $data->name = 'Test slideshow';
        $info = add_moduleinfo($data, $course);
        $cm = get_coursemodule_from_id(false, $info->coursemodule, 0, false, MUST_EXIST);

        $slideid = $DB->insert_record('slideshow_slide', (object) [
            'slideshow' => $cm->instance,
            'name' => 'Slide 1',
            'content' => '<p>Test</p>',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'sortorder' => 1,
            'timemodified' => time(),
        ]);

        return [$cm, (int) $slideid];
    }

    public function test_create_slideshow_adapter_with_valid_slideid(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$cm, $slideid] = $this->create_slideshow_with_slide();

        $factory = new activity_adapter_factory($DB);
        $adapter = $factory->create((int) $cm->id, $slideid);

        $this->assertInstanceOf(slideshow_slide_activity_adapter::class, $adapter);
        $this->assertSame('<p>Test</p>', $adapter->get_content());
    }

    public function test_create_slideshow_adapter_rejects_foreign_slideid(): void {
        global $DB;

        $this->resetAfterTest(true);
        [$firstcm, $firstslideid] = $this->create_slideshow_with_slide();
        [, $foreignslideid] = $this->create_slideshow_with_slide();

        $this->assertNotEquals($firstslideid, $foreignslideid);

        $factory = new activity_adapter_factory($DB);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error:slidenotinslideshow', 'local_dixeo'));
        $factory->create((int) $firstcm->id, $foreignslideid);
    }
}
