<div class="password-rules" data-password-rules data-password-target="{{ $target ?? 'password' }}">
    <p data-rule="length">Mínimo 12 caracteres</p>
    <p data-rule="upper">Una mayúscula</p>
    <p data-rule="lower">Una minúscula</p>
    <p data-rule="number">Un número</p>
    <p data-rule="symbol">Un símbolo</p>
</div>

@once
    <style>
        .password-rules { margin: -4px 0 12px; font-size: 13px; }
        .password-rules p { margin: 3px 0; color: #6b7280; }
        .password-rules p.met { color: #047857; font-weight: 600; }
    </style>
    <script>
        document.querySelectorAll('[data-password-rules]').forEach((list) => {
            const input = document.getElementById(list.dataset.passwordTarget);

            if (!input) {
                return;
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
                    list.querySelector(`[data-rule="${rule}"]`)?.classList.toggle('met', check(input.value));
                });
            };

            input.addEventListener('input', render);
            render();
        });
    </script>
@endonce
