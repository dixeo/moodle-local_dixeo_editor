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
 * Get asynchronous module regeneration job status.
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
use local_dixeo_editor\activity\activity_adapter_factory;
use local_dixeo_editor\event\regenerate_completed;
use local_dixeo_editor\local\content_sanitizer;
use local_dixeo_editor\local\editor_capability;
use local_dixeo_editor\local\external_error;

/**
 * External API to poll module content regeneration job status.
 */
class get_regenerate_module_content_status extends external_api {
    /**
     * Define parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'jobid' => new external_value(PARAM_RAW, 'Job id'),
            'slideid' => new external_value(
                PARAM_INT,
                'Slide row ID (required for slideshow, 0 otherwise)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Get the status of a module content regeneration job.
     *
     * @param int $cmid Course module ID.
     * @param string $jobid Job id.
     * @param int $slideid Slide row ID (slideshow only, 0 otherwise).
     * @return array
     */
    public static function execute(int $cmid, string $jobid, int $slideid = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'jobid' => $jobid,
            'slideid' => $slideid,
        ]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        editor_capability::require_edit_module($context);

        try {
            // Editor regenerate jobs are initiator-scoped.
            $statusdto = service_factory::get_job_service()->get_job_status(
                $params['jobid'],
                (int) $cm->course,
                (int) $USER->id
            );
            $status = self::normalize_status($statusdto->status, $statusdto->errorcode);

            $data = [
                'jobid' => $statusdto->jobid,
                'status' => $status,
                'progress' => $statusdto->progress,
                'content' => '',
                'errormessage' => $statusdto->errormessage ?? '',
            ];

            if ($status === 'completed') {
                $slideidparam = (int) $params['slideid'];
                $subid = $slideidparam > 0 ? $slideidparam : null;
                $adapter = (new activity_adapter_factory($DB))->create($params['cmid'], $subid);
                $contentfield = $adapter->get_content_field();
                $resultdata = $statusdto->result['data'] ?? [];
                $rawcontent = $resultdata[$contentfield] ?? ($resultdata['content'] ?? '');
                $data['content'] = content_sanitizer::sanitize((string) $rawcontent);
                // Audit only: job id + cm — never put content in the event payload.
                regenerate_completed::create_for_cm($cm, (int) $USER->id, $statusdto->jobid)->trigger();
            }

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return external_error::response($e);
        }
    }

    /**
     * Normalize failed job status when cancellation is reported via error code.
     *
     * @param string $status Raw job status.
     * @param string|null $errorcode Optional error code from the API.
     * @return string
     */
    private static function normalize_status(string $status, ?string $errorcode): string {
        if ($status !== 'failed') {
            return $status;
        }
        if (!empty($errorcode) && stripos($errorcode, 'cancel') !== false) {
            return 'cancelled';
        }
        return $status;
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
                'status' => new external_value(PARAM_ALPHA, 'Job status'),
                'progress' => new external_value(PARAM_INT, 'Progress percentage'),
                'content' => new external_value(PARAM_RAW, 'Generated content'),
                'errormessage' => new external_value(PARAM_RAW, 'Error message when failed'),
            ], 'Data payload', VALUE_OPTIONAL),
            'error' => new external_single_structure([
                'message' => new external_value(PARAM_RAW, 'Error message'),
            ], 'Error payload', VALUE_OPTIONAL),
        ]);
    }
}
