define(['core/str'], function(str) {
    return {
        init: async function() {
            // Check editing mode.
            if (!document.body.classList.contains('editing')) {
                return;
            }

            const editText = await str.get_string('editcontent', 'local_dixeo_editor');

            // Loop over each action menu element.
            document.querySelectorAll('.cm_action_menu[data-region="actionmenu"]').forEach(menu => {
                const cmid = menu.getAttribute('data-cmid');
                const dropdown = menu.querySelector('.dropdown-menu[data-rel="menu-content"]');
                if (!cmid || !dropdown) {
                    return;
                }

                // Find the nearest activity container (the <li> element).
                const activityContainer = menu.closest('li.activity');
                if (!activityContainer) {
                    return;
                }

                // Only target label activities based on their CSS class.
                if (!activityContainer.classList.contains('modtype_label')) {
                    return;
                }

                // Build the URL to your plugin's content edition page.
                const editUrl = `${M.cfg.wwwroot}/local/dixeo_editor/content_edition.php?cmid=${cmid}`;

                const item = document.createElement('a');
                item.href = editUrl;
                item.className = 'dropdown-item local-dixeo-edit-action';
                item.setAttribute('role', 'menuitem');
                item.setAttribute('tabindex', '-1');
                item.innerHTML = `
                    <i class="icon fa fa-edit fa-fw" aria-hidden="true"></i>
                    <span class="menu-action-text">${editText}</span>
                `;

                // Prepend the new item so that it appears first in the list.
                dropdown.insertBefore(item, dropdown.firstChild);
            });
        }
    };
});
