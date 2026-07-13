# Dixeo AI Editor

This local plugin provides AI-assisted editing for Moodle's _Page_ and _Text & Media_ activities, as well as Dixeo's own _Slideshow_ activity. It adds a "Dixeo Editor" item to the activity menu that opens a TinyMCE editor extended with an AI prompt area.

## Features

- **AI-Assisted Content Generation and Updating**: Dixeo generates or modifies module content based on your natural lanuage instructions.
- **Integration With TinyMCE**: The plugin integrates with the standard TinyMCE editor, extending it with an AI prompt area.
- **Quick Prompts**: Supports pre-programmed prompts to translate, enrich, or prettify content with a single click.
- **Success Notification**: Displays a custom success box upon completion of content update.
- **Undo/Redo Functionality**: Changes made by the AI can be undone/redone, exactly like with manual chnages.

## Requirements

- **Moodle**: 4.3 (or above; should be compatible with 4.1+).
- **TinyMCE**: Must be configured as the default Moodle text editor.
- **local_dixeo**: The Dixeo AI core plugin and a valid Dixeo API key.

## Installation

1. Copy `dixeo_editor` to `/local/dixeo_editor/`
2. Visit Site Administration > Notifications

## Configuration

- The plugin does not require additional configuration.
- Usage requires the user to have **local/dixeo:edit** and **moodle/course:manageactivities** in the module context. 

## Usage

1. Navigate to a **Page**, **Text & Media** or **Slideshow** activity in a course.
2. In the activity/contextual menu, click on **Dixeo Editor** to open the AI editing interface.
3. Enter your prompt or select a quick-prompt from the AI editing area below the TinyMCE window.
4. Press the Dixeo logo to update your content with AI.
5. Save the changes and exit the editor by clicking **OK** (or **X** to discard changes).

## Integration with Dixeo

This plugin uses the `local_dixeo_regenerate_module_content` web service to edit content. The service:
- Analyzes current module content
- Applies user instructions via AI
- Uses course context for consistency
- Leverages vector stores if course has synchronized files
- Returns validated, formatted content ready for display

The integration is implemented through Moodle's standard Ajax API, ensuring secure and efficient communication between the editor interface and the Dixeo AI backend.

## License

GNU GPL v3 or later
Copyright (C) 2026 Edunao

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License.
