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
 * Interface for activity-specific content adapters.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\activity;

use moodle_url;

interface activity_adapter_interface {

    /**
     * Return the main content to be edited.
     */
    public function get_content(): string;

    /**
     * Return the content format (HTML, etc.).
     */
    public function get_content_format(): int;

    /**
     * Prepare the content for editing in draft mode.
     *
     * @param int   $draftitemid
     * @param array $editoroptions
     * @return string The draft text
     */
    public function prepare_draft_area(int $draftitemid, array $editoroptions): string;

    /**
     * Save the edited content.
     *
     * @param string $content
     * @param int    $format
     * @param int    $itemid
     * @param array  $editoroptions
     */
    public function save_content(string $content, int $format, int $itemid, array $editoroptions): void;

    /**
     * Return the URL where the user should be redirected after saving/cancelling.
     *
     * @param int $courseid
     * @param int $cmid
     * @return moodle_url
     */
    public function get_redirect_url(int $courseid, int $cmid): moodle_url;
}

