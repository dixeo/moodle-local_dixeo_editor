<?php
/**
 * Activity adapter for a single slide of a Slideshow module.
 *
 * Unlike page/label adapters which target the module instance record, this
 * adapter targets a row in slideshow_slide identified by $slideid. File
 * storage uses itemid = slideid (mod_slideshow stores per-slide files).
 * After save, slideshow.revision is bumped for cache busting, mirroring
 * the behaviour of mod_slideshow's own edit flow.
 *
 * @package    local_dixeo_editor
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @author     Pierre FACQ <pierre.facq@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dixeo_editor\activity;

use context_module;
use moodle_database;
use moodle_url;

class slideshow_slide_activity_adapter extends base_activity_adapter {

    /**
     * Constructor.
     *
     * Loads the slideshow_slide record identified by $slideid. The
     * parent's $instanceid parameter carries the slide ID (not the
     * slideshow instance) because get_table_name() returns slideshow_slide.
     *
     * @param int $slideid The slideshow_slide row ID.
     * @param context_module $context The slideshow activity context.
     * @param moodle_database $db The database instance.
     */
    public function __construct(int $slideid, context_module $context, moodle_database $db) {
        parent::__construct($slideid, $context, $db);
    }

    protected function get_module_name(): string {
        return 'slideshow';
    }

    public function get_content_field(): string {
        return 'content';
    }

    protected function get_format_field(): string {
        return 'contentformat';
    }

    protected function get_file_area(): string {
        return 'content';
    }

    protected function get_table_name(): string {
        return 'slideshow_slide';
    }

    /**
     * Prepare draft area using the slide ID as itemid (per-slide file storage).
     *
     * @param int $draftitemid
     * @param array $editoroptions
     * @return string
     */
    public function prepare_draft_area(int $draftitemid, array $editoroptions): string {
        $contentfield = $this->get_content_field();

        return file_prepare_draft_area(
            $draftitemid,
            $this->context->id,
            'mod_' . $this->get_module_name(),
            $this->get_file_area(),
            (int) $this->record->id,
            $editoroptions,
            $this->record->{$contentfield}
        );
    }

    /**
     * Save the edited slide content using the slide ID as itemid.
     *
     * Also bumps the parent slideshow.revision and the slide's timemodified
     * for cache busting, matching mod_slideshow's own edit behaviour.
     *
     * @param string $content
     * @param int $format
     * @param int $itemid
     * @param array $editoroptions
     */
    public function save_content(string $content, int $format, int $itemid, array $editoroptions): void {
        $contentfield = $this->get_content_field();
        $formatfield = $this->get_format_field();

        $this->record->{$contentfield} = $content;
        $this->record->{$formatfield} = $format;
        $this->record->timemodified = time();

        if (!empty($itemid)) {
            $this->record->{$contentfield} = file_save_draft_area_files(
                $itemid,
                $this->context->id,
                'mod_' . $this->get_module_name(),
                $this->get_file_area(),
                (int) $this->record->id,
                $editoroptions,
                $this->record->{$contentfield}
            );
        }

        $this->db->update_record($this->get_table_name(), $this->record);

        // Bump parent slideshow.revision for cache busting.
        $this->db->execute(
            'UPDATE {slideshow} SET revision = revision + 1, timemodified = ? WHERE id = ?',
            [time(), (int) $this->record->slideshow]
        );
    }

    public function get_redirect_url(int $courseid, int $cmid): moodle_url {
        return new moodle_url('/mod/slideshow/slides.php', ['id' => $cmid]);
    }
}