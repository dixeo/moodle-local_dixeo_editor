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

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\service\image\content\editor_regenerate_service;
use local_dixeo\service\module_generation_service;
use local_dixeo_editor\activity\activity_adapter_factory;
use local_dixeo_editor\local\content_sanitizer;
use local_dixeo_editor\local\editor_capability;
use local_dixeo_editor\local\editor_image_context_factory;
use local_dixeo_editor\local\editor_session_repository;
use local_dixeo_editor\local\external_error;

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
            'sessionid' => new external_value(PARAM_INT, 'Editor session ID'),
            'slideid' => new external_value(
                PARAM_INT,
                'Slide row ID (required for slideshow, 0 otherwise)',
                VALUE_DEFAULT,
                0
            ),
            'drafthtml' => new external_value(
                PARAM_RAW,
                'Current editor draft HTML (empty = use stored content)',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Execute the content regeneration.
     *
     * @param int $cmid Course module ID.
     * @param string $instructions AI instructions.
     * @param int $sessionid Editor session id.
     * @param int $slideid Slide row ID (slideshow only).
     * @param string $drafthtml Current editor draft HTML.
     * @return array Response with success status, data or error.
     */
    public static function execute(
        int $cmid,
        string $instructions,
        int $sessionid,
        int $slideid = 0,
        string $drafthtml = ''
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'instructions' => $instructions,
            'sessionid' => $sessionid,
            'slideid' => $slideid,
            'drafthtml' => $drafthtml,
        ]);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        editor_capability::require_edit_module($context);

        $session = editor_session_repository::get($params['sessionid']);
        if (!$session || (int) $session->userid !== (int) $USER->id) {
            return [
                'success' => false,
                'error' => ['message' => 'Invalid editor session'],
            ];
        }

        $supported = activity_adapter_factory::get_supported_types();
        if (!in_array($cm->modname, $supported, true)) {
            return [
                'success' => false,
                'error' => ['message' => 'Content regeneration is not supported for this activity type.'],
            ];
        }

        $slideidparam = (int) $params['slideid'];
        $subid = $slideidparam > 0 ? $slideidparam : null;

        $drafthtml = trim((string) $params['drafthtml']) !== '' && $subid === null
            ? (string) $params['drafthtml']
            : null;

        $imagecontext = editor_image_context_factory::from_cmid($params['cmid'], $subid, $params['sessionid']);

        $built = editor_regenerate_service::build_edit_payload(
            $params['cmid'],
            $subid,
            $params['instructions'],
            $imagecontext,
            $drafthtml,
            (int) $USER->id
        );

        $service = new module_generation_service();
        $result = $service->edit_module_content_with_payload($built['payload']);

        if ($result->is_success()) {
            $adapter = (new activity_adapter_factory($DB))->create($params['cmid'], $subid);
            $contentfield = $adapter->get_content_field();
            $rawcontent = $result->result !== null
                ? ($result->result['data'][$contentfield] ?? '')
                : '';

            $decoded = editor_regenerate_service::decode_api_content(
                (string) $rawcontent,
                $imagecontext,
                (int) $USER->id
            );

            return [
                'success' => true,
                'data' => [
                    'content' => content_sanitizer::sanitize($decoded->html),
                    'pendingplaceholderids' => $decoded->newplaceholderids,
                ],
            ];
        }

        if ($result->is_failed()) {
            debugging(
                'local_dixeo_editor external error: ' . ($result->get_error_message() ?? 'unknown'),
                DEBUG_DEVELOPER
            );
            return [
                'success' => false,
                'error' => ['message' => external_error::generic_message()],
            ];
        }

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
                'pendingplaceholderids' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Placeholder id'),
                    'New pending placeholder ids',
                    VALUE_OPTIONAL
                ),
            ], 'Response data', VALUE_OPTIONAL),
            'error' => new external_single_structure([
                'message' => new external_value(PARAM_TEXT, 'Error message'),
            ], 'Error details', VALUE_OPTIONAL),
        ]);
    }
}
