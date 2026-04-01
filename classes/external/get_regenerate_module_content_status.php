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

class get_regenerate_module_content_status extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'jobid' => new external_value(PARAM_RAW, 'Job id'),
        ]);
    }

    /**
     * @param int $cmid
     * @param string $jobid
     * @return array
     */
    public static function execute(int $cmid, string $jobid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'jobid' => $jobid,
        ]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        try {
            $statusdto = service_factory::get_job_service()->get_job_status($params['jobid']);
            $status = self::normalize_status($statusdto->status, $statusdto->errorcode);

            $data = [
                'jobid' => $statusdto->jobid,
                'status' => $status,
                'progress' => $statusdto->progress,
                'content' => '',
                'errormessage' => $statusdto->errormessage ?? '',
            ];

            if ($status === 'completed') {
                $adapter = (new activity_adapter_factory($DB))->create($params['cmid']);
                $contentfield = $adapter->get_content_field();
                $resultdata = $statusdto->result['data'] ?? [];
                $data['content'] = $resultdata[$contentfield] ?? ($resultdata['content'] ?? '');
            }

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => ['message' => $e->getMessage()],
            ];
        }
    }

    /**
     * @param string $status
     * @param string|null $errorcode
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
