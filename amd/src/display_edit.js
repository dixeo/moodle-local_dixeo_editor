/**
 * Adds an AI content edit tab to activity secondary navigation.
 *
 * @module     local_dixeo_editor/display_edit
 * @copyright  2025 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    return {
        init: function(url, itemname) {
            // Create the new list item for the edit button.
            const editListItem = document.createElement('li');
            editListItem.setAttribute('data-key', 'aiedit');
            editListItem.classList.add('nav-item');
            editListItem.setAttribute('role', 'none');
            editListItem.setAttribute('data-forceintomoremenu', 'false');

            // Create the anchor tag for the edit button.
            const editLink = document.createElement('a');
            editLink.setAttribute('role', 'menuitem');
            editLink.classList.add('nav-link');
            editLink.setAttribute('href', url);
            editLink.textContent = itemname;

            // Append the anchor to the list item.
            editListItem.appendChild(editLink);

            // Find the modedit item and insert the new edit button before it.
            const modeditItem = document.querySelector('ul.nav-tabs > li[data-key="modedit"]');
            if (modeditItem) {
                modeditItem.parentNode.insertBefore(editListItem, modeditItem);
            }
        }
    };
});
