const dialog = document.querySelector('[data-admin-reauth-dialog]');

if (dialog) {
    const reauthForm = dialog.querySelector('[data-admin-reauth-form]');
    const passwordInput = reauthForm.querySelector('[name="password"]');
    const otpInput = reauthForm.querySelector('[name="otp"]');
    const error = dialog.querySelector('[data-admin-reauth-error]');
    const actionLabel = dialog.querySelector('[data-admin-action-label]');
    const submitButton = reauthForm.querySelector('button[type="submit"]');
    const cancelButton = dialog.querySelector('[data-admin-reauth-cancel]');
    let pendingForm = null;
    let reauthenticatedUntil = Date.now() + (Number(dialog.dataset.reauthenticatedFor || 0) * 1000);

    const hasRecentReauthentication = () => Date.now() < reauthenticatedUntil;

    const resetDialog = () => {
        reauthForm.reset();
        error.textContent = '';
        submitButton.disabled = false;
        submitButton.textContent = 'Confirmar y continuar';
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest?.('form[data-admin-reauth]');

        if (!form || hasRecentReauthentication() || typeof dialog.showModal !== 'function') {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        pendingForm = form;
        actionLabel.textContent = form.dataset.actionLabel || 'continuar con esta acción';
        error.textContent = '';
        dialog.showModal();
        passwordInput.focus();
    }, true);

    cancelButton.addEventListener('click', () => dialog.close());
    otpInput.addEventListener('input', () => {
        otpInput.value = otpInput.value.replace(/\D/g, '');
    });
    dialog.addEventListener('close', () => {
        pendingForm = null;
        resetDialog();
    });

    reauthForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        otpInput.value = otpInput.value.replace(/\D/g, '');
        error.textContent = '';
        submitButton.disabled = true;
        submitButton.textContent = submitButton.dataset.submitLabel || 'Verificando...';

        try {
            const response = await fetch(dialog.dataset.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: new FormData(reauthForm),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || 'No fue posible confirmar tu identidad. Revisa los datos e inténtalo de nuevo.');
            }

            reauthenticatedUntil = Date.now() + (Number(payload.reauthenticated_for || 0) * 1000);
            const actionForm = pendingForm;
            dialog.close();
            actionForm?.requestSubmit();
        } catch (requestError) {
            error.textContent = requestError.message || 'Ocurrió un error de conexión. Inténtalo de nuevo.';
            submitButton.disabled = false;
            submitButton.textContent = 'Confirmar y continuar';
            passwordInput.focus();
        }
    });
}
