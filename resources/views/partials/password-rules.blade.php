<div class="password-guidance">
    <p class="password-summary" id="{{ ($target ?? 'password') }}-requirements">
        Mínimo 12 caracteres, mayúsculas, minúsculas, números y símbolos.
    </p>
    <div
        class="password-rules"
        data-password-rules
        data-password-target="{{ $target ?? 'password' }}"
        role="list"
        aria-label="Requisitos de la contraseña"
    >
        <p data-rule="length" role="listitem"><span class="rule-icon" aria-hidden="true"></span>Mínimo 12 caracteres</p>
        <p data-rule="upper" role="listitem"><span class="rule-icon" aria-hidden="true"></span>Una mayúscula</p>
        <p data-rule="lower" role="listitem"><span class="rule-icon" aria-hidden="true"></span>Una minúscula</p>
        <p data-rule="number" role="listitem"><span class="rule-icon" aria-hidden="true"></span>Un número</p>
        <p data-rule="symbol" role="listitem"><span class="rule-icon" aria-hidden="true"></span>Un símbolo (por ejemplo, @ # $ % !)</p>
    </div>
</div>
