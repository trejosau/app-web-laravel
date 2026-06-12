@once
    <script>
        document.querySelectorAll('form[data-safe-submit]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');

                if (!button || button.disabled) {
                    return;
                }

                button.disabled = true;
                button.dataset.originalText = button.textContent;
                button.textContent = button.dataset.submitLabel || 'Procesando...';
            });
        });
    </script>
@endonce
