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
