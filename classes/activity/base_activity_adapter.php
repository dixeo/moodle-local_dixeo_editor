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
 * Abstract base class for activity content adapters.
 *
 * @package    local_dixeo_editor
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\activity;

use context_module;
use moodle_database;
use stdClass;
use local_dixeo_editor\local\content_sanitizer;

/**
 * Template Method pattern: common logic lives here, variations are delegated
 * to a small set of hooks that subclasses override.
 *
 * Subclasses MUST implement:
 *   - get_module_name(), get_content_field(), get_format_field(),
 *     get_file_area(), get_table_name()
 *   - resolve_record_id() — static, returns the DB row id from (cm, subid)
 *
 * Subclasses MAY override:
 *   - get_file_itemid() — defaults to 0; return $this->record->id for per-row
 *     file storage (composite modules like slideshow)
 *   - after_save() — defaults to noop; use for side-effects like cache
 *     revision bumps on the parent record
 */
abstract class base_activity_adapter implements activity_adapter_interface {
    /** @var stdClass The DB record for this activity instance (or sub-row). */
    protected stdClass $record;

    /** @var context_module Module context. */
    protected context_module $context;

    /** @var moodle_database Database handle. */
    protected moodle_database $db;

    /**
     * Constructor.
     *
     * @param int $recordid Target DB row id.
     * @param context_module $context Module context.
     * @param moodle_database $db Database handle.
     */
    public function __construct(int $recordid, context_module $context, moodle_database $db) {
        $this->db = $db;
        $this->context = $context;
        $this->record = $this->db->get_record($this->get_table_name(), ['id' => $recordid], '*', MUST_EXIST);
    }

    /**
     * Return the DB row id this adapter targets, computed from the course
     * module record and an optional composite sub-id (e.g. slide id).
     *
     * Simple modules (page, label) return $cm->instance and ignore $subid.
     * Composite modules (slideshow) validate $subid and return it.
     *
     * @param stdClass $cm Course module record.
     * @param int|null $subid Optional child record ID for composite modules.
     * @return int
     */
    abstract public static function resolve_record_id(stdClass $cm, ?int $subid): int;

    /**
     * Return the module name (e.g., 'page', 'label', 'slideshow').
     *
     * @return string
     */
    abstract protected function get_module_name(): string;

    /**
     * Return the DB column name for the content field.
     *
     * @return string
     */
    abstract public function get_content_field(): string;

    /**
     * Return the DB column name for the format field.
     *
     * @return string
     */
    abstract protected function get_format_field(): string;

    /**
     * Return the file area name used for file storage.
     *
     * @return string
     */
    abstract protected function get_file_area(): string;

    /**
     * Return the DB table name for this activity type (or sub-table).
     *
     * @return string
     */
    abstract protected function get_table_name(): string;

    /**
     * Return the itemid used when reading/writing files for this record.
     *
     * Default 0 (page/label). Composite modules override to return the
     * sub-row id (e.g. slideshow stores files with itemid = slide id).
     *
     * @return int
     */
    protected function get_file_itemid(): int {
        return 0;
    }

    /**
     * Hook called at the end of save_content(). Default noop.
     *
     * Override to perform side-effects like bumping a parent record's
     * revision counter for cache busting.
     */
    protected function after_save(): void {
    }

    /**
     * Return the main content to be edited.
     *
     * @return string
     */
    public function get_content(): string {
        $field = $this->get_content_field();
        return (string) $this->record->{$field};
    }

    /**
     * Return the content format (HTML, etc.).
     *
     * @return int
     */
    public function get_content_format(): int {
        $field = $this->get_format_field();
        return (int) $this->record->{$field};
    }

    /**
     * Prepare the content for editing in draft mode.
     *
     * @param int $draftitemid Draft file area item id.
     * @param array $editoroptions Editor options.
     * @return string The draft text.
     */
    public function prepare_draft_area(int $draftitemid, array $editoroptions): string {
        $contentfield = $this->get_content_field();

        return file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_' . $this->get_module_name(),
            $this->get_file_area(),
            $this->get_file_itemid(),
            $editoroptions,
            $this->record->{$contentfield}
        );
    }

    /**
     * Save the edited content.
     *
     * @param string $content Content text.
     * @param int $format Content format.
     * @param int $itemid Draft item id.
     * @param array $editoroptions Editor options.
     */
    public function save_content(string $content, int $format, int $itemid, array $editoroptions): void {
        $contentfield = $this->get_content_field();
        $formatfield = $this->get_format_field();

        // Purify untrusted HTML (including AI-generated content) before persist.
        $content = content_sanitizer::sanitize($content, $format);

        $this->record->{$contentfield} = $content;
        $this->record->{$formatfield} = $format;

        if (property_exists($this->record, 'timemodified')) {
            $this->record->timemodified = time();
        }

        if (!empty($itemid)) {
            $this->record->{$contentfield} = file_save_draft_area_files(
                $itemid,
                $this->context->id,
                'mod_' . $this->get_module_name(),
                $this->get_file_area(),
                $this->get_file_itemid(),
                $editoroptions,
                $this->record->{$contentfield}
            );
        }

        $this->db->update_record($this->get_table_name(), $this->record);

        $this->after_save();
    }
}
