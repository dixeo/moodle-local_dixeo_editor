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
 * External services definitions for local_dixeo_editor.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_dixeo_editor_regenerate_module_content' => [
        'classname'   => 'local_dixeo_editor\external\regenerate_module_content',
        'description' => 'Regenerate module content using AI',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/dixeo:edit',
    ],
    'local_dixeo_editor_start_regenerate_module_content' => [
        'classname'   => 'local_dixeo_editor\external\start_regenerate_module_content',
        'description' => 'Start asynchronous module content regeneration job',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/dixeo:edit',
    ],
    'local_dixeo_editor_get_regenerate_module_content_status' => [
        'classname'   => 'local_dixeo_editor\external\get_regenerate_module_content_status',
        'description' => 'Get asynchronous module content regeneration job status',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'local/dixeo:edit',
    ],
    'local_dixeo_editor_cancel_regenerate_module_content' => [
        'classname'   => 'local_dixeo_editor\external\cancel_regenerate_module_content',
        'description' => 'Cancel asynchronous module content regeneration job',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'local/dixeo:edit',
    ],
];
