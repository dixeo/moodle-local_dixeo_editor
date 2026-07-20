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
 * Cancel asynchronous module regeneration job.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\external\service_factory;
use local_dixeo_editor\event\regenerate_cancelled;
use local_dixeo_editor\local\editor_capability;
use local_dixeo_editor\local\external_error;

/**
 * External API to cancel an asynchronous module regeneration job.
 */
class cancel_regenerate_module_content extends external_api {
    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'jobid' => new external_value(PARAM_RAW, 'Job id'),
        ]);
    }

    /**
     * Cancel a module content regeneration job.
     *
     * @param int $cmid Course module ID.
     * @param string $jobid Job id.
     * @return array
     */
    public static function execute(int $cmid, string $jobid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'jobid' => $jobid,
        ]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        editor_capability::require_edit_module($context);

        try {
            // Editor regenerate jobs are initiator-scoped.
            $result = service_factory::get_job_service()->cancel_job(
                $params['jobid'],
                (int) $cm->course,
                (int) $USER->id
            );
            regenerate_cancelled::create_for_cm($cm, (int) $USER->id, $params['jobid'])->trigger();
            return [
                'success' => true,
                'data' => [
                    'jobid' => $params['jobid'],
                    'message' => $result['status'] ?? 'cancelled',
                ],
            ];
        } catch (\Throwable $e) {
            return external_error::response($e);
        }
    }

    /**
     * Define return values for the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Call success'),
            'data' => new external_single_structure([
                'jobid' => new external_value(PARAM_RAW, 'Job id'),
                'message' => new external_value(PARAM_RAW, 'Cancellation status'),
            ], 'Data payload', VALUE_OPTIONAL),
            'error' => new external_single_structure([
                'message' => new external_value(PARAM_RAW, 'Error message'),
            ], 'Error payload', VALUE_OPTIONAL),
        ]);
    }
}
