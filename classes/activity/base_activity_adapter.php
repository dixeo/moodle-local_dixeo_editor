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
use local_dixeo\service\image\content\editor_session_promoter;
use local_dixeo_editor\local\editor_image_context_factory;
use local_dixeo_editor\local\editor_session_repository;
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
     * Return the Moodle module name for this activity.
     *
     * @return string
     */
    public function get_modname(): string {
        return $this->get_module_name();
    }

    /**
     * Return the file area name used for this activity's content files.
     *
     * @return string
     */
    public function get_file_area_name(): string {
        return $this->get_file_area();
    }

    /**
     * Return the file item id used for this activity's content files.
     *
     * @return int
     */
    public function get_file_item_id(): int {
        return $this->get_file_itemid();
    }

    /**
     * Return the target DB record id.
     *
     * @return int
     */
    public function get_record_id(): int {
        return (int) $this->record->id;
    }

    /**
     * Return the module context.
     *
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Return the shortcode entity type for image generation.
     *
     * @return string
     */
    public function get_shortcode_entity(): string {
        if ($this->get_table_name() === 'slideshow_slide') {
            return 'slideshow_slide';
        }
        return $this->get_module_name();
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
     * @param int|null $draftitemid Passed by reference: 0/null lets core allocate
     *                              a new draft area and the generated id is
     *                              written back (same contract as
     *                              file_prepare_draft_area).
     * @param array $editoroptions Editor options.
     * @return string The draft text.
     */
    public function prepare_draft_area(?int &$draftitemid, array $editoroptions): string {
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
     * @param int|null $sessionid Editor session id for draft image promotion.
     */
    public function save_content(string $content, int $format, int $itemid, array $editoroptions, ?int $sessionid = null): void {
        global $USER;

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

        // Promote session draft images AFTER file_save_draft_area_files: that call
        // syncs the module filearea against the user draft area and would delete
        // any file promoted before it runs.
        if ($sessionid !== null && $sessionid > 0) {
            $subid = $this->get_table_name() === 'slideshow_slide' ? $this->get_record_id() : null;
            $imagecontext = editor_image_context_factory::from_cmid((int) $this->context->instanceid, $subid, $sessionid);
            $this->record->{$contentfield} = editor_session_promoter::finalize_on_save(
                $this->record->{$contentfield},
                $imagecontext,
                (int) $USER->id
            );
        }

        $this->db->update_record($this->get_table_name(), $this->record);

        if ($sessionid !== null && $sessionid > 0) {
            editor_session_repository::finalize_saved($sessionid, $this->get_modname(), $this->context);
        }

        $this->after_save();
    }
}
