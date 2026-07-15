const controlCharacters = /[\u0000-\u001f\u007f]/g;

const canonicalize = (input) => {
    const mode = input.dataset.sanitize;
    const withoutControls = input.value.replace(controlCharacters, '');

    switch (mode) {
        case 'username':
            input.value = withoutControls.trim().toLowerCase().replace(/[^a-z0-9_]/g, '');
            break;
        case 'login-identifier':
        case 'email':
            input.value = withoutControls.trim().toLowerCase();
            break;
        case 'otp':
            input.value = withoutControls.replace(/\D/g, '');
            break;
        case 'recovery-code':
            input.value = withoutControls.toUpperCase().replace(/[^A-Z0-9-]/g, '');
            break;
        default:
            break;
    }
};

document.querySelectorAll('[data-sanitize]').forEach((input) => {
    input.addEventListener('blur', () => canonicalize(input));
});

document.querySelectorAll('form[data-safe-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-sanitize]').forEach(canonicalize);

        const button = form.querySelector('button[type="submit"]');

        if (!button || button.disabled) {
            return;
        }

        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = button.dataset.submitLabel || 'Procesando...';
    });
});

document.querySelectorAll('[data-password-rules]').forEach((list) => {
    const input = document.getElementById(list.dataset.passwordTarget);

    if (!input) {
        return;
    }

    const summary = list.closest?.('.password-guidance')?.querySelector('.password-summary');

    if (summary?.id) {
        const describedBy = new Set((input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
        describedBy.add(summary.id);
        input.setAttribute('aria-describedby', [...describedBy].join(' '));
    }

    const checks = {
        length: (value) => value.length >= 12,
        upper: (value) => /[A-Z]/.test(value),
        lower: (value) => /[a-z]/.test(value),
        number: (value) => /[0-9]/.test(value),
        symbol: (value) => /[^A-Za-z0-9]/.test(value),
    };

    const render = () => {
        Object.entries(checks).forEach(([rule, check]) => {
            const item = list.querySelector(`[data-rule="${rule}"]`);
            const isMet = check(input.value);

            item?.classList.toggle('met', isMet);
            item?.classList.toggle('unmet', !isMet);
            item?.setAttribute('aria-label', `${isMet ? 'Cumplido' : 'Pendiente'}: ${item.textContent.trim()}`);
        });
    };

    input.addEventListener('input', render);
    render();
});

document.querySelectorAll('input[type="password"]').forEach((input) => {
    if (!input.id || document.querySelector(`[data-password-toggle="${input.id}"]`)) {
        return;
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.dataset.passwordToggle = input.id;
    button.setAttribute('aria-controls', input.id);

    const render = () => {
        const visible = input.type === 'text';
        button.textContent = visible ? 'Ocultar contraseña' : 'Mostrar contraseña';
        button.setAttribute('aria-pressed', String(visible));
    };

    button.addEventListener('click', () => {
        const selectionStart = input.selectionStart;
        const selectionEnd = input.selectionEnd;
        input.type = input.type === 'password' ? 'text' : 'password';
        input.focus({ preventScroll: true });

        if (selectionStart !== null && selectionEnd !== null) {
            input.setSelectionRange(selectionStart, selectionEnd);
        }

        render();
    });

    input.insertAdjacentElement('afterend', button);
    render();
});
