const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = readFileSync(
    resolve(__dirname, '../../resources/js/admin-reauth.js'),
    'utf8',
);

const setup = (reauthenticatedFor) => {
    const listeners = {};
    const control = () => ({
        addEventListener() {},
        dataset: {},
        disabled: false,
        focus() {},
        setAttribute() {},
        textContent: '',
        value: '',
    });
    const password = control();
    const otp = control();
    const submitButton = control();
    submitButton.dataset.submitLabel = 'Verificando...';
    const cancelButton = control();
    const error = control();
    const actionLabel = control();
    const reauthForm = {
        addEventListener(event, listener) {
            listeners[`form:${event}`] = listener;
        },
        querySelector(selector) {
            if (selector === '[name="password"]') return password;
            if (selector === '[name="otp"]') return otp;
            return submitButton;
        },
        reset() {},
    };
    const dialog = {
        dataset: {
            endpoint: '/admin/reauth',
            reauthenticatedFor: String(reauthenticatedFor),
        },
        addEventListener(event, listener) {
            listeners[`dialog:${event}`] = listener;
        },
        close() {},
        querySelector(selector) {
            if (selector === '[data-admin-reauth-form]') return reauthForm;
            if (selector === '[data-admin-reauth-error]') return error;
            if (selector === '[data-admin-action-label]') return actionLabel;
            if (selector === '[data-admin-reauth-cancel]') return cancelButton;
            return submitButton;
        },
        showModalCalls: 0,
        showModal() {
            this.showModalCalls += 1;
        },
    };
    const document = {
        addEventListener(event, listener) {
            listeners[`document:${event}`] = listener;
        },
        querySelector(selector) {
            return selector === '[data-admin-reauth-dialog]' ? dialog : null;
        },
    };

    vm.runInNewContext(source, {
        Date: { now: () => 1_000_000 },
        FormData: class {},
        document,
        fetch: async () => {},
    });

    return { dialog, submit: listeners['document:submit'] };
};

const submitEvent = () => {
    const state = { prevented: false, stopped: false };

    return {
        state,
        event: {
            preventDefault() { state.prevented = true; },
            stopImmediatePropagation() { state.stopped = true; },
            target: {
                closest() {
                    return { dataset: { actionLabel: 'bloquear el acceso' } };
                },
            },
        },
    };
};

test('opens reauthentication modal when confirmation is missing', () => {
    const { dialog, submit } = setup(0);
    const { event, state } = submitEvent();

    submit(event);

    assert.equal(state.prevented, true);
    assert.equal(state.stopped, true);
    assert.equal(dialog.showModalCalls, 1);
});

test('keeps the original action direct while reauthentication is recent', () => {
    const { dialog, submit } = setup(300);
    const { event, state } = submitEvent();

    submit(event);

    assert.equal(state.prevented, false);
    assert.equal(dialog.showModalCalls, 0);
});
