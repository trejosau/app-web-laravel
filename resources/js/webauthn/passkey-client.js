const buttons = '[data-webauthn-button]';

const defaultMessages = {
    httpsRequired: 'Passkeys require HTTPS.',
    browserUnsupported: 'Passkeys are not available in this browser.',
    optionsFailed: 'Could not prepare the passkey request.',
    completionFailed: 'Could not complete the passkey request.',
    cancelledOrExpired: 'Operation cancelled or expired.',
    csrfMissing: 'CSRF token is missing.',
};

const readMessages = (button) => ({
    httpsRequired: button.dataset.messageHttpsRequired || defaultMessages.httpsRequired,
    browserUnsupported: button.dataset.messageBrowserUnsupported || defaultMessages.browserUnsupported,
    optionsFailed: button.dataset.messageOptionsFailed || defaultMessages.optionsFailed,
    completionFailed: button.dataset.messageCompletionFailed || defaultMessages.completionFailed,
    cancelledOrExpired: button.dataset.messageCancelledOrExpired || defaultMessages.cancelledOrExpired,
    csrfMissing: button.dataset.messageCsrfMissing || defaultMessages.csrfMissing,
});

const getElement = (id) => id ? document.getElementById(id) : null;

const setMessage = (element, message) => {
    if (!element) {
        return;
    }

    element.textContent = message || '';
    element.hidden = !message;
};

const decodeBase64Url = (value) => {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(base64.length + (4 - base64.length % 4) % 4, '=');
    const binary = window.atob(padded);
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes.buffer;
};

const encodeBase64Url = (buffer) => {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
};

const prepareCreationOptions = (options) => {
    options.publicKey.challenge = decodeBase64Url(options.publicKey.challenge);
    options.publicKey.user.id = decodeBase64Url(options.publicKey.user.id);
    options.publicKey.excludeCredentials = (options.publicKey.excludeCredentials || []).map((credential) => ({
        ...credential,
        id: decodeBase64Url(credential.id),
    }));

    return options;
};

const prepareRequestOptions = (options) => {
    options.publicKey.challenge = decodeBase64Url(options.publicKey.challenge);
    options.publicKey.allowCredentials = (options.publicKey.allowCredentials || []).map((credential) => ({
        ...credential,
        id: decodeBase64Url(credential.id),
    }));

    return options;
};

const credentialToJson = (credential) => ({
    id: credential.id,
    rawId: encodeBase64Url(credential.rawId),
    type: credential.type,
    response: {
        clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
        attestationObject: credential.response.attestationObject
            ? encodeBase64Url(credential.response.attestationObject)
            : undefined,
        authenticatorData: credential.response.authenticatorData
            ? encodeBase64Url(credential.response.authenticatorData)
            : undefined,
        signature: credential.response.signature ? encodeBase64Url(credential.response.signature) : undefined,
        userHandle: credential.response.userHandle ? encodeBase64Url(credential.response.userHandle) : null,
        transports: credential.response.getTransports ? credential.response.getTransports() : [],
    },
});

const parseJsonResponse = async (response, fallbackMessage) => {
    let payload = null;

    try {
        payload = await response.json();
    } catch {
        throw new Error(fallbackMessage);
    }

    if (!response.ok) {
        throw new Error(payload.code && payload.message ? `${payload.code}: ${payload.message}` : fallbackMessage);
    }

    return payload;
};

const fetchJson = async (url, options, fallbackMessage) => {
    let response;

    try {
        response = await window.fetch(url, options);
    } catch {
        throw new Error(fallbackMessage);
    }

    return parseJsonResponse(response, fallbackMessage);
};

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const validateSupport = (messages) => {
    if (!window.isSecureContext) {
        return messages.httpsRequired;
    }

    if (!window.PublicKeyCredential || !navigator.credentials) {
        return messages.browserUnsupported;
    }

    return '';
};

const runPasskeyFlow = async (button, error, messages) => {
    const { mode, optionsUrl, submitUrl } = button.dataset;

    if (!['login', 'register'].includes(mode) || !optionsUrl || !submitUrl) {
        throw new Error(messages.completionFailed);
    }

    const supportError = validateSupport(messages);
    if (supportError) {
        throw new Error(supportError);
    }

    const csrfToken = getCsrfToken();
    if (!csrfToken) {
        throw new Error(messages.csrfMissing);
    }

    const options = await fetchJson(optionsUrl, {
        headers: { Accept: 'application/json' },
    }, messages.optionsFailed);

    const credential = mode === 'register'
        ? await navigator.credentials.create(prepareCreationOptions(options))
        : await navigator.credentials.get(prepareRequestOptions(options));

    if (!credential) {
        throw new Error(messages.completionFailed);
    }

    const result = await fetchJson(submitUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(credentialToJson(credential)),
    }, messages.completionFailed);

    setMessage(error, '');

    if (result.redirect) {
        window.location.href = result.redirect;
    }
};

const initializeButton = (button) => {
    if (button.dataset.webauthnInitialized === 'true') {
        return;
    }

    button.dataset.webauthnInitialized = 'true';

    const error = getElement(button.dataset.errorId);
    const warning = getElement(button.dataset.warningId);
    const messages = readMessages(button);
    const supportWarning = validateSupport(messages);

    if (supportWarning) {
        setMessage(warning, supportWarning);
        button.disabled = true;

        return;
    }

    button.addEventListener('click', async () => {
        if (button.disabled) {
            return;
        }

        setMessage(error, '');
        button.disabled = true;

        try {
            await runPasskeyFlow(button, error, messages);
        } catch (exception) {
            setMessage(
                error,
                exception.name === 'NotAllowedError'
                    ? messages.cancelledOrExpired
                    : exception.message || messages.completionFailed,
            );
        } finally {
            button.disabled = false;
        }
    });

    if (button.dataset.autoStart === 'true' && !supportWarning) {
        window.setTimeout(() => button.click(), 250);
    }
};

const initializePasskeys = () => {
    document.querySelectorAll(buttons).forEach(initializeButton);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePasskeys);
} else {
    initializePasskeys();
}
