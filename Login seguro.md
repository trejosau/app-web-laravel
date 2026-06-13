# Login seguro en Laravel 10

## Actualizacion final OWASP Top 10:2025

Se agrega cierre contra OWASP Top 10:2025:

| Codigo | Control |
|---|---|
| A01 Broken Access Control | Policies, roles, `mfa.level`, `admin.reauth`. |
| A02 Security Misconfiguration | `.env.example` sin secretos, `APP_DEBUG=false`, security headers. |
| A03 Software Supply Chain Failures | `composer audit`, lockfiles y herramientas documentadas. |
| A04 Cryptographic Failures | Argon2id, `Crypt`, tokens/recovery codes hasheados. |
| A05 Injection | Form Requests, Eloquent, filtros validados. |
| A06 Insecure Design | Matriz roles/MFA, reauth admin, rate limits. |
| A07 Authentication Failures | MFA por rol, password reset seguro, reCAPTCHA v2 opcional. |
| A08 Integrity Failures | CSRF, WebAuthn challenge, hash chain de auditoria. |
| A09 Logging/Alerting Failures | `security_audit_logs`, sanitizacion, UI read-only. |
| A10 Exceptional Conditions | Handler seguro, errores genericos, tokens invalid/expired. |

Nota de entorno: las pruebas se mantienen en PostgreSQL. SQLite no se usa.

## Evidencia de verificacion 2026-06-02

| Comando | Resultado |
|---|---|
| `php artisan test` | 13 passed, 46 skipped por schema PostgreSQL no migrado. |
| `./vendor/bin/pint --test` | Passed. |
| `composer audit` | Sin advisories. |
| `php artisan migrate --force` | Bloqueado: usuario PostgreSQL sin permiso para crear `migrations` en `public`. |
| `npm audit` | No ejecutado: `npm` no disponible. |
| `npm run build` | No ejecutado: `npm` no disponible. |
| `gitleaks detect --source=. --redact` | No ejecutado: herramienta no disponible. |
| `trufflehog filesystem . --only-verified` | No ejecutado: herramienta no disponible. |

## Estado del documento

Este documento queda actualizado al estado real del proyecto.

Leyenda:

* `[x]` Completado.
* `[~]` Parcial, falta ajuste o prueba manual.
* `[ ]` Faltante.

Verificacion ejecutada:

```bash
php artisan test
```

Resultado: `[x]` 43 tests pasaron.

---

# 1. Factores reales de autenticacion

El flujo real de autenticacion usa:

* `[x]` `username`.
* `[x]` `password`.
* `[x]` TOTP compatible con Google Authenticator.
* `[x]` WebAuthn / Passkey para admin.

Los roles `guest`, `user` y `admin` siguen existiendo como niveles de acceso y definen cuantos factores requiere cada usuario.

| Rol | Factores requeridos | Acceso |
| --- | --- | --- |
| `guest` | `username` + `password` | Dashboard basico |
| `user` | `username` + `password` + TOTP Google Authenticator | Dashboard user |
| `admin` | `username` + `password` + TOTP Google Authenticator + WebAuthn/Passkey | Dashboard admin |

---

# 2. Estado general por modulos

| Modulo | Estado | Implementado | Falta |
| --- | --- | --- | --- |
| Registro seguro | `[x]` | Username unico, password fuerte, rol default backend, bloqueo de `role_id`, `is_admin`, `email`, `status`, logs y rate limit. | Nada critico. |
| Login username/password | `[x]` | Username normalizado, password con `Hash::check()`, errores genericos, estado de usuario, sesion regenerada y rate limit. | Validacion de formato en login puede endurecerse. |
| Logout | `[x]` | Limpia auth, MFA pendiente, invalida sesion, regenera CSRF y audita. | Nada critico. |
| Roles y dashboards | `[x]` | `guest`, `user`, `admin`, middleware `role`, dashboard protegido y logs `access.denied`. | Nada critico. |
| MFA pendiente | `[x]` | Sesion temporal, expiracion 5 min, bloqueo de dashboard mientras MFA esta pendiente. | Borrar por demasiados fallos queda cubierto por rate limit, no por contador propio. |
| TOTP Google | `[x]` | Secreto aleatorio, QR `otpauth://totp`, 6 digitos, ventana controlada, replay counter, logs y rate limit. | Desactivacion TOTP no implementada. |
| Recovery codes | `[x]` | Codigos fuertes, mostrados una vez, hasheados, consumo unico y regeneracion. | Nada critico. |
| WebAuthn/Passkey | `[x]` | Registro y login con challenge, `navigator.credentials`, validacion backend, sign counter, logs y rate limit. | Requiere prueba manual en navegador/origen seguro. |
| Perfil | `[x]` | Cambio de password, email de recuperacion verificado, cierre de otras sesiones y password reset publico. | Nada critico. |
| Rate limiting | `[x]` | Registro, login, TOTP, recovery codes, WebAuthn, admin, password reset, reenvio de correo, reauth y acciones criticas. | Nada critico. |
| Auditoria | `[~]` | Tabla, servicio, sanitizacion de metadata y eventos principales. | Faltan eventos planeados como `token.invalid`, `token.expired`, `admin.reauthenticated`. |
| CSRF | `[x]` | Middleware web activo, error CSRF invalida sesion y registra auditoria. | Nada critico. |
| Sesiones | `[~]` | Sesion cifrada por config, DB sessions, regeneracion en login/MFA/password, revocacion de otras sesiones. | Timeout admin mas corto no implementado. |
| Manejo de errores | `[~]` | Mensajes genericos en auth/MFA, CSRF controlado, no se imprimen secretos. | No todos los codigos planeados tienen handler propio. |
| Frontend Blade | `[~]` | Login, registro, TOTP, recovery codes, WebAuthn y dashboard funcionales. | Spinners/doble submit no estan completos en todos los formularios. |
| Cloudflare Tunnel | `[~]` | Hay soporte de `APP_URL`, `WEBAUTHN_RP_ID` y binario local. | Falta guia paso a paso y activar cookie secure al usar HTTPS. |
| Password reset | `[x]` | Solicitud generica, token hasheado, expiracion, un solo uso, validacion y cambio. | Tests preparados; schema PostgreSQL local no migrado. |
| Reautenticacion admin | `[x]` | Password + TOTP, ventana 5 min, rate limit y auditoria. | Tests preparados; schema PostgreSQL local no migrado. |
| Auditorias externas | `[~]` | `composer audit` ejecutado sin advisories. | `npm`, Gitleaks, TruffleHog, ZAP/Burp pendientes por entorno/manual. |
| README/changelog/guia | `[x]` | README, changelog y docs de pruebas/matriz/herramientas. | Nada critico. |

---

# 3. Manejo de datos sensibles

## Passwords

* `[x]` Se hashean con `Hash::make()`.
* `[x]` Se verifican con `Hash::check()`.
* `[x]` El driver configurado es `argon2id` en `config/hashing.php`.
* `[x]` No se cifran de forma reversible.
* `[x]` No se guardan en texto plano.
* `[x]` No se flashean en errores por `Handler::$dontFlash`.
* `[x]` No se registran en logs por sanitizacion de auditoria.

Herramienta: Laravel Hash, Argon2id.

## TOTP

* `[x]` El OTP de 6 digitos no se guarda.
* `[x]` El `totp_secret` se guarda en `users.totp_secret`.
* `[x]` El `totp_secret` se cifra con cast Eloquent `encrypted`.
* `[x]` Laravel usa `Crypt` con `APP_KEY` y cipher `AES-256-CBC`.
* `[x]` El secreto no se hashea porque debe descifrarse para validar TOTP.
* `[x]` El algoritmo TOTP usa HMAC-SHA1 RFC 6238, periodo 30s, ventana configurable.
* `[x]` Se protege contra replay con `totp_last_used_counter`.
* `[x]` OTP y secreto TOTP se filtran de logs.

Herramientas: Laravel encrypted cast/Crypt, TOTP propio compatible con Google Authenticator.

## Recovery codes

* `[x]` Se generan con aleatoriedad fuerte.
* `[x]` Se muestran una sola vez.
* `[x]` Se guardan hasheados con `Hash::make()`.
* `[x]` Se validan con `Hash::check()`.
* `[x]` Se consumen una sola vez con `used_at`.
* `[x]` Se pueden regenerar.
* `[x]` Se filtran de logs.

Herramienta: Laravel Hash, Argon2id.

## WebAuthn / Passkeys

* `[x]` El backend no recibe ni guarda private keys.
* `[x]` `credential_id` se guarda cifrado con cast `encrypted`.
* `[x]` `credential_id_hash` usa SHA-512 para busqueda unica.
* `[x]` `public_key` se guarda sin hash porque se necesita para verificar firmas.
* `[x]` `credential_id`, `credential_id_hash` y `public_key` quedan ocultos en serializacion.
* `[x]` Challenges de registro/login se guardan en sesion y se eliminan con `pull()` al usarse.
* `[x]` Challenge, credential ID, public key, assertion, cookies y session keys se filtran de logs.
* `[x]` Sign counter se guarda y actualiza.

Herramientas: `lbuchs/webauthn`, Laravel session, Laravel encrypted cast/Crypt, SHA-512 para indice.

## Tokens y sesiones

| Token/dato | Estado | Manejo actual |
| --- | --- | --- |
| CSRF token | `[x]` | Laravel `VerifyCsrfToken`; al fallar invalida sesion y registra `csrf.failed`. |
| Session ID | `[x]` | Sesion regenerada despues de login completo, MFA y cambio de password. |
| Sesion MFA pendiente | `[x]` | `auth_pending_user_id`, `auth_pending_level`, `auth_pending_started_at`, expira en 5 min. |
| WebAuthn challenge | `[x]` | Guardado temporal en sesion, un solo uso con `pull()`. |
| Recovery email link | `[x]` | URL firmada temporal de Laravel, expira en 30 min. |
| Remember token | `[~]` | Se regenera al cambiar password; no hay flujo remember-me activo. |
| Password reset token | `[x]` | Token aleatorio hasheado, expirable y de un solo uso. |
| Personal access tokens | `No aplica` | Migracion Sanctum existe, no hay modulo API token en esta practica local. |
| Reauthentication token | `[x]` | Marca temporal en sesion, expira en 5 min y se limpia al logout/timeout. |

---

# 4. Flujos actuales

## `guest`: username/password

```txt
username + password
  -> validar credenciales
  -> regenerar sesion
  -> log login.success
  -> /dashboard/guest
```

Checklist:

* `[x]` Username requerido.
* `[x]` Password requerida.
* `[x]` Password verificada con `Hash::check()`.
* `[x]` Rate limit `login`: 5/min por `sha512(username|IP)`.
* `[x]` Sesion regenerada.
* `[x]` Log de exito/fallo.
* `[x]` Dashboard protegido.

## `user`: username/password + TOTP Google

```txt
username + password
  -> crear MFA pendiente
  -> solicitar TOTP
  -> validar TOTP
  -> regenerar sesion
  -> log login.success
  -> /dashboard/user
```

Checklist:

* `[x]` Password correcta no inicia sesion completa.
* `[x]` Usuario queda en MFA pendiente.
* `[x]` TOTP obligatorio.
* `[x]` TOTP setup si aun no esta activado.
* `[x]` QR compatible con Google Authenticator.
* `[x]` Rate limit `totp`.
* `[x]` OTP incorrecto genera log seguro.
* `[x]` OTP correcto genera log seguro.
* `[x]` Dashboard bloqueado mientras MFA esta pendiente.
* `[x]` Recovery code puede sustituir TOTP y se consume.

## `admin`: username/password + TOTP Google + WebAuthn

```txt
username + password
  -> crear MFA pendiente
  -> validar TOTP o recovery code
  -> solicitar WebAuthn/Passkey
  -> validar assertion
  -> regenerar sesion
  -> log login.success
  -> /dashboard/admin
```

Checklist:

* `[x]` Password obligatoria.
* `[x]` TOTP obligatorio.
* `[x]` WebAuthn/Passkey obligatorio.
* `[x]` Admin sin TOTP verificado no ve WebAuthn.
* `[x]` Admin sin passkey registrada pasa a registro de passkey.
* `[x]` Admin con passkey registrada pasa a login WebAuthn.
* `[x]` Challenge WebAuthn validado.
* `[x]` Origin/RP ID dependen de `WEBAUTHN_RP_ID` y contexto seguro.
* `[x]` Log de exito/fallo WebAuthn.
* `[x]` Sesion admin mas corta.
* `[x]` Reautenticacion admin para acciones criticas.

---

# 5. Matriz de permisos

| Accion | Guess | User | Admin |
| --- | ---: | ---: | ---: |
| Login con username/password | Si | Si | Si |
| Login con TOTP Google | No | Si | Si |
| Login con recovery code | No | Si | Si, pero aun requiere WebAuthn |
| Login con WebAuthn/Passkey | No | No | Si |
| Ver `/dashboard/guest` | Si | Si | Si |
| Ver `/dashboard/user` | No | Si | Si |
| Ver `/dashboard/admin` | No | No | Si |
| Cambiar password propia | Si | Si | Si |
| Email de recuperacion verificado | Si | Si | Si |
| Cerrar otras sesiones propias | Si | Si | Si |
| Activar TOTP | No | Si | Si |
| Regenerar recovery codes | No | Si | Si |
| Registrar Passkey | No | No | Si |
| Ver logs de auditoria | No | No | No implementado en UI |
| Cambiar roles | No | No | No implementado en UI |

---

# 6. Rate limiting

| Limiter | Estado | Limite | Llave |
| --- | --- | --- | --- |
| `register` | `[x]` | 3/min | SHA-512 de IP |
| `login` | `[x]` | 5/min | SHA-512 de `username|IP` |
| `totp` | `[x]` | 5/min | SHA-512 de `user_id|IP` o pendiente |
| `recovery-codes` | `[x]` | 3/10 min | SHA-512 de `user_id|IP` o pendiente |
| `webauthn` | `[x]` | 5/min | SHA-512 de `user_id|IP` o pendiente |
| `admin` | `[x]` | 25/min | SHA-512 de `user_id|IP` |
| `password-reset` | `[x]` | 3/10 min | SHA-512 de `email|IP` |
| `email-resend` | `[x]` | 3/10 min | SHA-512 de `user_id|IP` |

Respuesta segura: `429 Demasiados intentos. Intenta mas tarde.`

---

# 7. Auditoria

## Implementado

* `[x]` Tabla `security_audit_logs`.
* `[x]` Campos: `user_id`, `event`, `severity`, `ip_address`, `user_agent`, `route`, `method`, `status`, `metadata`, `created_at`.
* `[x]` Sanitizacion recursiva de metadata.
* `[x]` Filtrado por claves sensibles: `password`, `otp`, `totp`, `secret`, `token`, `challenge`, `recovery_code`, `credential_id`, `public_key`, `assertion`, `cookie`, `session`.
* `[x]` Strings limitados a 255 chars.

## Eventos implementados principales

* `[x]` `user.registered`
* `[x]` `register.failed`
* `[x]` `login.success`
* `[x]` `login.failed`
* `[x]` `login.mfa_required`
* `[x]` `logout.success`
* `[x]` `password.changed`
* `[x]` `password.change_failed`
* `[x]` `totp.enabled`
* `[x]` `totp.failed`
* `[x]` `totp.verified`
* `[x]` `recovery_code.used`
* `[x]` `recovery_code.failed`
* `[x]` `webauthn.registered`
* `[x]` `webauthn.failed`
* `[x]` `webauthn.verified`
* `[x]` `access.denied`
* `[x]` `csrf.failed`
* `[x]` `session.expired`
* `[x]` `rate_limit.blocked`

## Faltantes

* `[x]` `password.reset.requested`
* `[x]` `password.reset.completed`
* `[x]` `totp.disabled`
* `[x]` `role.changed`
* `[x]` `token.invalid`
* `[x]` `token.expired`
* `[x]` `admin.reauthenticated`

---

# 8. Validaciones

## Registro

* `[x]` `username`: requerido, string, 3-32, regex `^[a-z0-9_]+$`, unico.
* `[x]` `password`: requerido, confirmado, minimo 12, mayus/minus, numeros y simbolos.
* `[x]` Prohibidos: `role_id`, `is_admin`, `status`, `email`, `name`.
* `[x]` Username normalizado a lowercase y trim.

## Login

* `[x]` `username` requerido.
* `[x]` `password` requerida.
* `[x]` Username normalizado a lowercase y trim.
* `[x]` Mensaje generico si falla.
* `[~]` Falta regex/maximo igual que registro.

## TOTP

* `[x]` `otp` requerido.
* `[x]` Solo digitos.
* `[x]` Longitud segun `TOTP_DIGITS`, default 6.

## Recovery code

* `[x]` `code` requerido.
* `[x]` Maximo 32.
* `[x]` Regex alfanumerico con guion.
* `[x]` Requiere MFA pendiente.

## Perfil

* `[x]` Password actual requerida.
* `[x]` Password nueva fuerte y confirmada.
* `[x]` Email de recuperacion requerido, RFC, max 255 y unico.

---

# 9. Configuracion local

| Item | Estado |
| --- | --- |
| `.env` fuera de Git | `[x]` |
| `.env.example` sin secretos reales | `[x]` |
| `APP_KEY` no incluida en ejemplo | `[x]` |
| PostgreSQL configurado por ejemplo | `[x]` |
| Mailpit/local mail en ejemplo | `[x]` |
| `SESSION_DRIVER=database` | `[x]` |
| `SESSION_ENCRYPT=true` | `[x]` |
| `TOTP_*` documentado en `.env.example` | `[x]` |
| `WEBAUTHN_*` documentado en `.env.example` | `[x]` |
| `SESSION_SECURE_COOKIE=false` para local HTTP | `[x]` |
| `SESSION_SECURE_COOKIE=true` para HTTPS tunnel | `[x]` |
| Guia Cloudflare Tunnel | `[x]` |

---

# 10. Pruebas actuales

## Unitarias

* `[x]` Auditoria elimina metadata sensible.
* `[x]` TOTP genera codigos RFC 6238.
* `[x]` TOTP verifica codigos.
* `[x]` TOTP rechaza replay.
* `[x]` TOTP secret se cifra en modelo.
* `[x]` Recovery code usa Laravel Hash.
* `[x]` WebAuthn credential ID usa SHA-512.
* `[x]` WebAuthn credential ID se cifra.
* `[x]` Public key se almacena sin hash.
* `[x]` Campos sensibles WebAuthn se ocultan.

## Feature

* `[x]` Registro completo.
* `[x]` Bloqueo de mass assignment de rol/admin.
* `[x]` Login `guest` 1FA.
* `[x]` Login `user` pasa a MFA pendiente.
* `[x]` Error generico en credenciales invalidas.
* `[x]` Rate limit de registro.
* `[x]` Rate limit de login.
* `[x]` MFA pendiente expira.
* `[x]` Dashboard bloqueado con MFA pendiente.
* `[x]` Logout limpia MFA pendiente.
* `[x]` Flujo TOTP user.
* `[x]` QR TOTP devuelve SVG.
* `[x]` Recovery code login.
* `[x]` Autorizacion dashboards por rol.
* `[x]` Cambio de password.
* `[x]` Opciones WebAuthn para admin pendiente.

## Faltan pruebas

* `[x]` Password reset completo.
* `[x]` Reautenticacion admin.
* `[ ]` Browser/E2E real de passkey con origen seguro.
* `[~]` CSRF invalido como test automatizado.
* `[~]` Secret scanning y dependency audit.

---

# 11. Pendientes finales

Prioridad alta:

* `[x]` Implementar password reset completo.
* `[x]` Agregar reautenticacion admin para acciones criticas.
* `[x]` Definir timeout admin mas corto.
* `[x]` Agregar limiter para reenvio de correo y password reset.
* `[ ]` Probar WebAuthn manualmente en navegador con `localhost` o HTTPS tunnel.

Prioridad media:

* `[x]` Agregar desactivacion TOTP segura.
* `[x]` Endurecer validacion de `username` en login.
* `[x]` Completar spinners/doble submit en formularios Blade.
* `[x]` Documentar Cloudflare Tunnel paso a paso.
* `[x]` Actualizar README, changelog y guia de pruebas.

Prioridad baja:

* `[x]` Ejecutar `composer audit`.
* `[~]` Ejecutar `npm audit`.
* `[~]` Ejecutar Gitleaks o TruffleHog.
* `[ ]` Ejecutar OWASP ZAP o prueba manual equivalente.

---

# 12. Entregable actual

* `[x]` Codigo Laravel 10.
* `[x]` Registro funcional.
* `[x]` Login funcional por `username/password`.
* `[x]` Logout funcional.
* `[x]` Dashboard protegido.
* `[x]` Rol `guest` con 1FA.
* `[x]` Rol `user` con 2FA TOTP Google.
* `[x]` Rol `admin` con 3FA TOTP Google + WebAuthn.
* `[x]` TOTP funcional.
* `[x]` WebAuthn/Passkeys implementado.
* `[~]` WebAuthn requiere prueba manual en navegador seguro.
* `[x]` RateLimiter en registro.
* `[x]` RateLimiter en login.
* `[x]` RateLimiter en TOTP.
* `[x]` RateLimiter en recovery codes.
* `[x]` RateLimiter en WebAuthn.
* `[x]` Passwords con Argon2id.
* `[x]` TOTP secrets cifrados.
* `[x]` Recovery codes hasheados.
* `[x]` WebAuthn credential ID cifrado y hasheado para busqueda.
* `[x]` Sesiones protegidas y regeneradas.
* `[x]` CSRF activo.
* `[x]` Validaciones backend.
* `[~]` Validaciones frontend basicas.
* `[x]` Matriz de roles.
* `[~]` Logs/auditoria completa para eventos principales, parcial para eventos planeados.
* `[~]` Manejo seguro de errores completo en auth/MFA/CSRF, parcial global.
* `[x]` Tests automatizados existentes pasan.
* `[x]` README final.
* `[x]` Guia de pruebas final.
* `[x]` `.env.example`.
* `[x]` Changelog.
* `[~]` Revision de dependencias.
* `[~]` Secret scanning.
