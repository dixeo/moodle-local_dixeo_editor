# Dixeo AI Editor

This local plugin provides AI-assisted editing for Moodle's _Page_ and _Text & Media_ activities, as well as Dixeo's own _Slideshow_ activity. It adds a "Dixeo Editor" item to the activity menu that opens a TinyMCE editor extended with an AI prompt area.

## Features

- **AI-Assisted Text Generation and Editing**: Dixeo generates or modifies module content based on your natural lanuage instructions.
- **Integration With TinyMCE**: The plugin integrates with the standard TinyMCE editor, extending it with an AI prompt area.
- **Quick Prompts**: Supports pre-programmed prompts to translate, enrich, or prettify content with a single click.
- **Success Notification**: Displays a custom success box upon completion of content update.
- **Undo/Redo Functionality**: Changes made by the AI can be undone/redone, exactly like with manual chnages.

## Requirements

- **Moodle**: 4.3 (or above; should be compatible with 4.1+).
- **TinyMCE**: Must be configured as the default Moodle text editor.
- **Dixeo AI (local_dixeo)**: The Dixeo AI core plugin and a valid Dixeo API key.

## Installation

1. Copy `dixeo_editor` to `/local/dixeo_editor/`
2. Visit Site Administration > Notifications
3. Complete the Moodle upgrade.

## Configuration

- The plugin does not require additional configuration.
- Users must have the capabilities **local/dixeo:edit** and **moodle/course:manageactivities** in the course (or module) context. 

## Usage

1. Navigate to a **Page**, **Text & Media** or **Slideshow** activity in a course.
2. In the activity menu (or contextual menu on the course page), click on **Dixeo Editor** to open the AI editing interface.
3. Enter your prompt or select a quick-prompt from the AI editing area situated below the TinyMCE window.
4. Press the Dixeo logo to update your content with AI.
5. Undo/redo changes from TinyMCE.
6. Save the changes and exit the editor by clicking **OK** (or press **X** to discard changes).

## License

GNU GPL v3 or later
Copyright (C) 2026 Edunao

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License.
