const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = readFileSync(
    resolve(__dirname, '../../resources/js/webauthn/passkey-client.js'),
    'utf8',
);

const createButton = () => ({
    dataset: {
        autoStart: 'true',
        errorId: 'passkey-error',
        mode: 'register',
        optionsUrl: '/options',
        submitUrl: '/submit',
        warningId: 'passkey-warning',
    },
    disabled: false,
    listeners: [],
    addEventListener(event, listener) {
        this.listeners.push({ event, listener });
    },
});

const executeClient = (button, { secure = true, timers = [] } = {}) => {
    const elements = {
        'passkey-error': { hidden: true, textContent: '' },
        'passkey-warning': { hidden: true, textContent: '' },
    };

    vm.runInNewContext(source, {
        document: {
            getElementById: (id) => elements[id] || null,
            querySelector: () => ({ content: 'csrf-token' }),
            querySelectorAll: () => [button],
            readyState: 'complete',
        },
        navigator: { credentials: {} },
        Uint8Array,
        window: {
            PublicKeyCredential: function PublicKeyCredential() {},
            isSecureContext: secure,
            setTimeout: (callback) => timers.push(callback),
        },
    });

    return { elements, timers };
};

test('initializes a passkey button only once when the client is loaded twice', () => {
    const button = createButton();
    const timers = [];

    executeClient(button, { timers });
    executeClient(button, { timers });

    assert.equal(button.listeners.length, 1);
    assert.equal(timers.length, 1);
    assert.equal(button.dataset.webauthnInitialized, 'true');
});

test('does not initialize or auto-start passkeys outside a secure context', () => {
    const button = createButton();
    const { elements, timers } = executeClient(button, { secure: false });

    assert.equal(button.disabled, true);
    assert.equal(button.listeners.length, 0);
    assert.equal(timers.length, 0);
    assert.equal(elements['passkey-warning'].hidden, false);
});
