# Dixeo AI Editor — Moodle Plugin

**Plugin component:** `local_dixeo_editor`  
**Description:** Local plugin that adds AI-assisted content editing for supported Moodle activities (page, label, slideshow). It provides an editing UI with TinyMCE integration and calls Dixeo web services to regenerate or refine module content.

---

## Features

- **AI-powered content editing** for page, label, and slideshow activities.
- **Quick-prompt toolbar** for common transformations (translate, enrich, prettify, tone, structure, and more).
- **TinyMCE integration** with optional Tiny autosave draft support during async regeneration.
- **Sync and async flows** via the plugin's AJAX web services.

---

## Requirements

- **Moodle:** 4.1+ (see `version.php` for the exact `$plugin->requires` value).
- **TinyMCE:** Required for the in-browser editing experience.
- **local_dixeo:** Required dependency (`$plugin->dependencies`); provides API access, job handling, and the `local/dixeo:edit` capability.

---

## Capabilities

Editor access is enforced at runtime by `editor_capability`, which requires **both**:

- `moodle/course:manageactivities`
- `local/dixeo:edit` (defined in `local_dixeo`)

The editor plugin does not define its own `db/access.php`; capability management stays in `local_dixeo`.

---

## Web services

Registered in `db/services.php` (all require the capabilities above):

| Function | Purpose |
|---|---|
| `local_dixeo_editor_regenerate_module_content` | Synchronous content regeneration |
| `local_dixeo_editor_start_regenerate_module_content` | Start async regeneration job |
| `local_dixeo_editor_get_regenerate_module_content_status` | Poll async job status |
| `local_dixeo_editor_cancel_regenerate_module_content` | Cancel async job |

---

## Usage

1. Open a supported activity (page, label, or slideshow slide) in a course where you have editor capabilities.
2. Use the Dixeo editor entry point (activity menu / edit tab, depending on module).
3. Enter instructions or choose a quick prompt, then generate content.
4. Review the result in TinyMCE and save through the normal Moodle activity workflow.

---

## Integration with Dixeo

Content editing is delegated to `local_dixeo` services. The editor externals build activity context (including slideshow slide ownership checks) and submit jobs to the Dixeo API (`/v1/modules/edit`). The plugin does not persist AI payloads in its own database tables.

---

## Customization

- **UI / prompts:** Templates and AMD modules under `templates/` and `amd/src/`.
- **New activity types:** Register adapters via `activity_adapter_factory::register_adapter()`.
