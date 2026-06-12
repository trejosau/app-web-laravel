# Security Matrix

## Roles Y MFA

| Accion | Guest | User | Admin |
|---|---:|---:|---:|
| Login password | Si | Si | Si |
| Login TOTP | No | Si | Si |
| Login WebAuthn | No | No | Si |
| Panel guest | Si | Si | Si |
| Panel user | No | Si | Si |
| Panel admin | No | No | Si |
| Cambiar password propia | Si + password actual | Si + password actual | Si + password actual |
| Activar TOTP | No | Si | Si |
| Actualizar TOTP | No | Si + password + TOTP actual | Si + password + TOTP actual |
| Registrar Passkey | No | No | Si + reauth |
| Ver auditoria | No | No | Si + reauth |
| Cambiar roles | No | No | No implementado |
| Bloquear usuario | No | No | Si + reauth |
| Soft delete usuario | No | No | Si + reauth |
| Cambiar correo de recuperacion | Si + reauth | Si + reauth | Si + reauth |
| Regenerar recovery codes | No | Si + reauth | Si + reauth |

## Rutas

| Ruta | Proteccion |
|---|---|
| `/login` | guest, rate limit, reCAPTCHA opcional |
| `/register` | guest, rate limit, reCAPTCHA opcional |
| `/forgot-password` | guest, rate limit, reCAPTCHA opcional |
| `/reset-password/{token}` | guest, token hasheado/expirable |
| `/profile/email` | auth, MFA completo, reauth |
| `/profile/email/verification` | auth, MFA completo, reauth, rate limit |
| `/dashboard/guest` | auth, `mfa.level:1` |
| `/dashboard/user` | auth, role, `mfa.level:2` |
| `/dashboard/admin` | auth, role, `mfa.level:3`, admin timeout |
| `/admin/reauth` | admin 3FA, rate limit |
| `/account/reauth` | auth, MFA completo, password y TOTP si aplica |
| `/admin/users` | admin 3FA, reauth |
| `/admin/audit-logs` | admin 3FA, reauth, read-only |
| `/mfa/webauthn/setup` | admin autenticado + reauth, o admin pendiente tras TOTP |
| `/mfa/recovery-codes/regenerate` | auth, MFA completo, reauth, rate limit |

## Top 10 Seguridad 2025

| Codigo | Aplica | Control | Evidencia | Riesgo residual |
|---|---|---|---|---|
| A01 Broken Access Control | Si | Policies, roles, MFA levels, reauth | `UserPolicy`, `SecurityAuditLogPolicy`, rutas admin | Error humano en nuevas rutas |
| A02 Security Misconfiguration | Si | `.env.example`, headers, debug false | `SecurityHeaders`, `.env.example` | Config real fuera del repo |
| A03 Software Supply Chain Failures | Si | audits y lockfiles | `composer audit`, `composer.lock` | npm/gitleaks si no disponibles |
| A04 Cryptographic Failures | Si | Argon2id, Crypt, HMAC tokens | `config/hashing.php`, services | Rotacion de `APP_KEY` no cubierta |
| A05 Injection | Si | Form Requests, Eloquent | requests admin/auth/MFA | Nuevas queries manuales |
| A06 Insecure Design | Si | matriz roles, reauth, rate limits | docs y middlewares | WebAuthn manual pendiente |
| A07 Authentication Failures | Si | MFA, reset seguro, no enumeration, reauth sensible | auth controllers/tests | reCAPTCHA depende de keys |
| A08 Integrity Failures | Si | CSRF, hash chain, WebAuthn challenge | audit migration/service | Hash chain no es firma digital |
| A09 Logging/Alerting Failures | Si | `security_audit_logs`, eventos, sanitizacion | `SecurityAuditService` | ELK opcional no implementado |
| A10 Exceptional Conditions | Si | handler CSRF, errores genericos, tests tokens | `Handler`, password reset tests | Errores externos dependen de config |

## Sesion Y Logs

| Control | Evidencia |
|---|---|
| Cookie `laravel_session` | `config/session.php`, `.env.example` |
| Payload de sesion cifrado | `SESSION_ENCRYPT=true` |
| Sesion autenticada solo tras factor final | `UserSessionService::completeLogin`, `MfaPendingSessionService` |
| Cierre de otras sesiones al login | `UserSessionService::completeLogin` |
| Trazabilidad sin tokens reales | `current_session_fingerprint`, `revoked_session_fingerprints` |
| Logs de auditoria | `storage/logs/audit.log`, `security_audit_logs` |
| Logs de autenticacion | `storage/logs/authentication.log` |
| Logs de desarrollo | `storage/logs/development.log` |
