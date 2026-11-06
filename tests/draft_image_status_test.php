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

use local_dixeo\service\image\placeholder_ids;
use local_dixeo_editor\external\get_editor_draft_image_status;
use local_dixeo_editor\local\editor_session_repository;

/**
 * The draft image status service polls a bounded, cleaned list of placeholder ids.
 *
 * @package    local_dixeo_editor
 * @category   test
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dixeo_editor\external\get_editor_draft_image_status
 */
final class draft_image_status_test extends \advanced_testcase {
    /** @var int Course module id of a page. */
    private int $cmid;

    /** @var int Active editor session id of the teacher. */
    private int $sessionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->cmid = (int) $page->cmid;
        $this->setUser($teacher);
        $this->sessionid = (int) editor_session_repository::get_or_create_active($this->cmid, null, (int) $teacher->id)->id;
    }

    /**
     * One id over the cap is refused before any database read.
     */
    public function test_more_than_the_cap_is_refused_without_database_reads(): void {
        global $DB;

        $ids = $this->uuids(placeholder_ids::MAX_PER_REQUEST + 1);
        $reads = $DB->perf_get_reads();
        try {
            get_editor_draft_image_status::execute($this->cmid, $this->sessionid, $ids);
            $this->fail('Too many placeholder ids must be refused.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame($reads, $DB->perf_get_reads());
        }
    }

    /**
     * The cap of valid ids is answered with one item each.
     */
    public function test_cap_of_valid_ids_is_answered(): void {
        $ids = $this->uuids(placeholder_ids::MAX_PER_REQUEST);

        $result = get_editor_draft_image_status::execute($this->cmid, $this->sessionid, $ids);

        $this->assertTrue($result['success']);
        $this->assertSame($ids, array_column($result['data']['items'], 'placeholderid'));
    }

    /**
     * Duplicate and malformed ids are dropped.
     */
    public function test_duplicate_and_invalid_ids_are_dropped(): void {
        $id = \core\uuid::generate();

        $result = get_editor_draft_image_status::execute(
            $this->cmid,
            $this->sessionid,
            [$id, ' ' . $id, 'not-a-uuid', '', '../../etc']
        );

        $this->assertTrue($result['success']);
        $this->assertSame([$id], array_column($result['data']['items'], 'placeholderid'));
    }

    /**
     * Distinct canonical UUIDs.
     *
     * @param int $count Number of ids.
     * @return string[]
     */
    private function uuids(int $count): array {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = \core\uuid::generate();
        }
        return $ids;
    }
}
