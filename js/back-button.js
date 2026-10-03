const backButtonScript = document.currentScript;
const backButtonFallback = backButtonScript?.dataset.fallback || 'view/dashboard.php';
const backButtonStylesheet = document.createElement('link');
backButtonStylesheet.rel = 'stylesheet';
backButtonStylesheet.href = new URL('../css/back-button.css', backButtonScript.src).href;
document.head.appendChild(backButtonStylesheet);

let backButton = document.querySelector('[data-back-button]');

if (!backButton) {
    backButton = document.createElement('button');
    backButton.type = 'button';
    backButton.className = 'back-button';
    backButton.dataset.backButton = '';
    backButton.dataset.fallback = backButtonFallback;
    backButton.textContent = '← Kembali';
    const mainContent = document.querySelector('main');
    (mainContent || document.body).appendChild(backButton);
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-back-button]');

    if (!button) {
        return;
    }

    const fallback = button.dataset.fallback || backButtonFallback;
    const referrer = document.referrer;

    if (referrer) {
        try {
            if (new URL(referrer).origin === window.location.origin && window.history.length > 1) {
                window.history.back();
                return;
            }
        } catch {
            // Gunakan alamat fallback jika referrer tidak valid.
        }
    }

    window.location.href = fallback;
});