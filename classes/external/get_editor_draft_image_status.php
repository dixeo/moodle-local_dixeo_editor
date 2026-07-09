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
 * Poll pending draft image placeholders for the editor.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\repository\image\job_repository;
use local_dixeo\service\image\content\file_service;
use local_dixeo\service\image\content\url_helper;
use local_dixeo_editor\local\editor_capability;
use local_dixeo_editor\local\editor_image_context_factory;
use local_dixeo_editor\local\editor_session_repository;

/**
 * External API to poll draft image placeholder status for the editor.
 */
class get_editor_draft_image_status extends external_api {
    /**
     * Describe parameters for the web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'sessionid' => new external_value(PARAM_INT, 'Editor session ID'),
            'placeholderids' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'Placeholder UUID'),
                'Placeholder ids to poll'
            ),
            'slideid' => new external_value(PARAM_INT, 'Slide row ID', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Return status details for the given draft image placeholders.
     *
     * @param int $cmid Course module ID.
     * @param int $sessionid Editor session ID.
     * @param array $placeholderids Placeholder UUIDs to poll.
     * @param int $slideid Slide row ID (slideshow only, 0 otherwise).
     * @return array
     */
    public static function execute(int $cmid, int $sessionid, array $placeholderids, int $slideid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'sessionid' => $sessionid,
            'placeholderids' => $placeholderids,
            'slideid' => $slideid,
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

        $subid = (int) $params['slideid'] > 0 ? (int) $params['slideid'] : null;
        $imagecontext = editor_image_context_factory::from_cmid($params['cmid'], $subid, $params['sessionid']);

        $items = [];
        foreach ($params['placeholderids'] as $placeholderid) {
            $placeholderid = trim((string) $placeholderid);
            if ($placeholderid === '') {
                continue;
            }

            $filename = file_service::stub_filename_for_placeholder($placeholderid);
            $draftloc = $imagecontext->draft_location($filename);
            $statuspayload = job_repository::get_location_status($draftloc);

            $imgclass = 'img-fluid';
            if (
                $statuspayload['status'] === job_repository::STATUS_PENDING
                || $statuspayload['status'] === job_repository::STATUS_PROCESSING
            ) {
                $imgclass .= ' dixeo-img-gen-pending';
            } else if ($statuspayload['status'] === job_repository::STATUS_FAILED) {
                $imgclass .= ' dixeo-img-gen-failed';
            }

            $items[] = [
                'placeholderid' => $placeholderid,
                'status' => (string) $statuspayload['status'],
                'imageurl' => (string) ($statuspayload['imageurl'] ?? url_helper::get_current_image_url($draftloc)),
                'imgclass' => $imgclass,
                'errormessage' => (string) ($statuspayload['errormessage'] ?? ''),
            ];
        }

        return [
            'success' => true,
            'data' => ['items' => $items],
        ];
    }

    /**
     * Describe return values for the web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success'),
            'data' => new external_single_structure([
                'items' => new external_multiple_structure(new external_single_structure([
                    'placeholderid' => new external_value(PARAM_RAW, 'Placeholder id'),
                    'status' => new external_value(PARAM_ALPHA, 'Job status'),
                    'imageurl' => new external_value(PARAM_URL, 'Image URL', VALUE_OPTIONAL),
                    'imgclass' => new external_value(PARAM_RAW, 'CSS classes'),
                    'errormessage' => new external_value(PARAM_RAW, 'Error message', VALUE_OPTIONAL),
                ])),
            ], 'Data', VALUE_OPTIONAL),
            'error' => new external_single_structure([
                'message' => new external_value(PARAM_RAW, 'Error message'),
            ], 'Error', VALUE_OPTIONAL),
        ]);
    }
}
