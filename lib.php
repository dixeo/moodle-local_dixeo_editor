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
 * Library functions for the Dixeo Editor plugin.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_dixeo_editor\local\editor_capability;

/** @var string Path to the content edition page. */
define('LOCAL_DIXEO_EDITOR_CONTENT_EDIT_PATH', '/local/dixeo_editor/content_edition.php');

global $PAGE;
if (isset($PAGE) && strpos($PAGE->pagetype, 'course-view') === 0 && $PAGE->user_is_editing()) {
    // Enqueue our module on course view when editing mode is enabled.
    $PAGE->requires->js_call_amd('local_dixeo_editor/action_menu_edit', 'init');
}

/**
 * Extend course navigation with Dixeo editor hooks when editing a page activity.
 *
 * @param navigation_node $navigation Navigation node.
 * @param stdClass $course Course record.
 * @param context $context Course context.
 */
function local_dixeo_editor_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context) {
    global $PAGE;

    if (
        $PAGE->cm !== null && $PAGE->cm->modname === 'page'
        && editor_capability::can_edit_module($PAGE->cm->context)
    ) {
        // Call init js script.
        $url = new moodle_url(LOCAL_DIXEO_EDITOR_CONTENT_EDIT_PATH, ['cmid' => $PAGE->cm->id]);
        $PAGE->requires->js_call_amd('local_dixeo_editor/display_edit', 'init', [
            $url->out(false),
            get_string('editcontent', 'local_dixeo_editor'),
        ]);
    }
}

/**
 * Add an edit-content button to the page context header.
 *
 * @param \moodle_page $page Current page.
 * @return string HTML for the edit button, or empty string.
 */
function local_dixeo_editor_add_button_to_context_header($page) {
    global $OUTPUT;

    $editicon = '';

    if ($page->cm->modname !== 'page') {
        return $editicon;
    }

    // Check if page is the content edition page.
    $currentpath = $page->url->get_path();
    if (str_contains($currentpath, LOCAL_DIXEO_EDITOR_CONTENT_EDIT_PATH)) {
        return $editicon;
    }

    if (editor_capability::can_edit_module($page->cm->context)) {
        $editstring = get_string('editcontent', 'local_dixeo_editor');
        $editurl = new moodle_url(LOCAL_DIXEO_EDITOR_CONTENT_EDIT_PATH, ['cmid' => $page->cm->id]);
        $editicon = $OUTPUT->pix_icon('t/editstring', $editstring, 'core', ['class' => 'icon']);
        $editicon = html_writer::link(
            $editurl,
            $editicon,
            [
                'style' => 'padding: 10px 12px;',
                'class' => 'btn btn-secondary edit-button',
                'title' => $editstring,
            ]
        );
    }

    return $editicon;
}

/**
 * Add edit content button to the activity menu.
 *
 * @param \moodle_page $page Current page.
 * @return array List of action link data for the activity menu.
 */
function local_dixeo_editor_add_button_to_activity_menu($page) {
    global $OUTPUT;

    $actions = [];

    if ($page->cm->modname !== 'page') {
        return $actions;
    }

    // Check if page is the content edition page.
    $currentpath = $page->url->get_path();
    if (str_contains($currentpath, LOCAL_DIXEO_EDITOR_CONTENT_EDIT_PATH)) {
        return $actions;
    }

    if (!editor_capability::can_edit_module($page->cm->context)) {
        return $actions;
    }

    $text = get_string('editcontent', 'local_dixeo_editor');

    $actions[] = [
        'url' => new moodle_url(LOCAL_DIXEO_EDITOR_CONTENT_EDIT_PATH, ['cmid' => $page->cm->id]),
        'icon' => $OUTPUT->pix_icon('t/editstring', $text, 'core', ['class' => 'icon']),
        'params' => ['class' => 'btn btn-secondary edit-button', 'title' => $text, 'style' => 'padding: 13px 15px;'],
    ];

    return $actions;
}

/**
 * User preferences for AJAX / core_user repository (content editor layout).
 *
 * @return array
 */
function local_dixeo_editor_user_preferences(): array {
    return [
        'local_dixeo_editor_content_panel_state' => [
            'type' => PARAM_TEXT,
            'null' => NULL_ALLOWED,
            'default' => '',
            'permissioncallback' => [\core\user::class, 'is_current_user'],
        ],
    ];
}
