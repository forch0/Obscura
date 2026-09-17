// resources/js/dropdown.js
// Delegated dropdown handling — works for any .dropdown anywhere,
// including elements added dynamically after page load.

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
