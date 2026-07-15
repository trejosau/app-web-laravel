const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = readFileSync(
    resolve(__dirname, '../../resources/js/form-security.js'),
    'utf8',
);

const input = (mode, value) => ({
    dataset: { sanitize: mode },
    listeners: {},
    value,
    addEventListener(event, listener) {
        this.listeners[event] = listener;
    },
});

const execute = (inputs) => vm.runInNewContext(source, {
    document: {
        getElementById: () => null,
        querySelectorAll: (selector) => selector === '[data-sanitize]' ? inputs : [],
    },
});

test('sanitizes public identifiers without touching passwords', () => {
    const username = input('username', '  Us<er>\u0000_Name  ');
    const email = input('email', ' USER\r\n@Example.Test ');
    const password = input(undefined, ' Keep\u0000Exact! ');

    execute([username, email, password]);
    username.listeners.blur();
    email.listeners.blur();
    password.listeners.blur();

    assert.equal(username.value, 'user_name');
    assert.equal(email.value, 'user@example.test');
    assert.equal(password.value, ' Keep\u0000Exact! ');
});

test('restricts otp and recovery-code fields to their allowlists', () => {
    const otp = input('otp', ' 12a3-45\n6 ');
    const recoveryCode = input('recovery-code', ' abcd<script>-1234 ');

    execute([otp, recoveryCode]);
    otp.listeners.blur();
    recoveryCode.listeners.blur();

    assert.equal(otp.value, '123456');
    assert.equal(recoveryCode.value, 'ABCDSCRIPT-1234');
});
