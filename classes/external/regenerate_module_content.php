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
 * External API for regenerating module content using AI.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_module;
use local_dixeo\service\module_generation_service;

/**
 * Regenerate module content using AI via the Dixeo service.
 */
class regenerate_module_content extends external_api {

    /**
     * Describe the parameters for the execute function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'instructions' => new external_value(PARAM_TEXT, 'AI instructions for content regeneration'),
        ]);
    }

    /**
     * Execute the content regeneration.
     *
     * @param int $cmid Course module ID.
     * @param string $instructions AI instructions.
     * @return array Response with success status, data or error.
     */
    public static function execute(int $cmid, string $instructions): array {
        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'instructions' => $instructions,
        ]);

        // Retrieve module and validate context.
        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        // Call the Dixeo service to edit the module.
        $service = new module_generation_service();
        $result = $service->edit_module($params['cmid'], $params['instructions']);

        if ($result->is_success()) {
            return [
                'success' => true,
                'data' => ['content' => $result->get_content() ?? ''],
            ];
        }

        // Handle failed vs pending (timeout) states.
        // IMPORTANT: Check is_failed() BEFORE is_pending() because failed results also have completed=false.
        if ($result->is_failed()) {
            return [
                'success' => false,
                'error' => ['message' => $result->get_error_message() ?? 'An unexpected error occurred'],
            ];
        }

        // Job is still processing (timeout waiting for completion).
        return [
            'success' => false,
            'error' => ['message' => 'The AI is still processing your request. Please try again in a moment.'],
        ];
    }

    /**
     * Describe the return value of the execute function.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'data' => new external_single_structure([
                'content' => new external_value(PARAM_RAW, 'Generated content'),
            ], 'Response data', VALUE_OPTIONAL),
            'error' => new external_single_structure([
                'message' => new external_value(PARAM_TEXT, 'Error message'),
            ], 'Error details', VALUE_OPTIONAL),
        ]);
    }
}
