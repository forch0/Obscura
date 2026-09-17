// resources/js/dropdown.js
// Delegated dropdown handling — works for any .dropdown anywhere,
// including elements added dynamically after page load.
// Also handles .select-dropdown (form select replacement).

document.addEventListener('click', (e) => {
    const toggle = e.target.closest('.dropdown-toggle');

    if (toggle) {
        const dropdown = toggle.closest('.dropdown');
        const wasOpen = dropdown.classList.contains('open');

        closeAllDropdowns();

        if (!wasOpen) {
            dropdown.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
        }
        return;
    }

    // Select-dropdown item picked — set hidden input + label
    const selectItem = e.target.closest('.select-menu .dropdown-item');
    if (selectItem) {
        if (selectItem.disabled) return; // disabled — keep menu open

        const dropdown = selectItem.closest('.select-dropdown');
        const input = dropdown.querySelector('input[type=hidden]');
        const label = dropdown.querySelector('.select-value');

        input.value = selectItem.dataset.value;
        label.textContent = selectItem.textContent.trim();

        dropdown.querySelectorAll('.dropdown-item').forEach(i => {
            i.classList.remove('selected');
            i.setAttribute('aria-selected', 'false');
        });
        selectItem.classList.add('selected');
        selectItem.setAttribute('aria-selected', 'true');

        dropdown.classList.remove('open');
        input.dispatchEvent(new Event('change', { bubbles: true }));
        return;
    }

    // Clicked a menu item — close its dropdown
    if (e.target.closest('.dropdown-item')) {
        e.target.closest('.dropdown')?.classList.remove('open');
        return;
    }

    // Clicked outside — close all
    closeAllDropdowns();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeAllDropdowns();
});

function closeAllDropdowns() {
    document.querySelectorAll('.dropdown.open').forEach(d => {
        d.classList.remove('open');
        d.querySelector('.dropdown-toggle')?.setAttribute('aria-expanded', 'false');
    });
}
