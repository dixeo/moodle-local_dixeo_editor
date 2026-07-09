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

namespace local_dixeo_editor\local;

use local_dixeo\external\service_factory;

/**
 * Initiator-scoped access checks for editor regenerate jobs.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class editor_job_access {
    /**
     * Ensure a regenerate job belongs to the course and initiating user.
     *
     * Uses the hub job registry only; does not call the remote API.
     *
     * @param string $jobid Remote job UUID.
     * @param int $courseid Expected course ID.
     * @param int $userid Expected initiating user ID.
     * @throws \moodle_exception When the binding is missing or mismatched.
     */
    public static function require_initiator_job(string $jobid, int $courseid, int $userid): void {
        $record = service_factory::get_job_service()->get_job_repository()->get_by_jobid($jobid);
        if (
            $record === null
            || (int) $record->courseid !== $courseid
            || (int) $record->userid !== $userid
        ) {
            throw new \moodle_exception('error:job_not_found', 'local_dixeo');
        }
    }
}
