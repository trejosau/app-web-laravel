# Security Matrix

## Roles Y MFA

| Acción | Guest | User | Admin |
|---|---:|---:|---:|
| Login password | Sí | Sí | Sí |
| Login TOTP | No | Sí | Sí |
| Login WebAuthn | No | No | Sí |
| Panel guest | Sí | Sí | Sí |
| Panel user | No | Sí | Sí |
| Panel admin | No | No | Sí |
| Cambiar password propia | Sí + password actual | Sí + password actual | Sí + password actual |
| Activar TOTP | No | Sí | Sí |
| Actualizar TOTP | No | Sí + password + TOTP actual | Sí + password + TOTP actual |
| Registrar Passkey | No | No | Sí + reauth |
| Ver auditoria | No | No | Sí + reauth |
| Cambiar roles | No | No | No implementado |
| Bloquear usuario | No | No | Sí + reauth |
| Cambiar correo de recuperación | Sí + reauth | Sí + reauth | Sí + reauth |
| Regenerar recovery codes | No | Sí + reauth | Sí + reauth |

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
| `/admin/users` | admin 3FA; reauth solo en mutaciones sensibles |
| `/admin/audit-logs` | admin 3FA, read-only |
| `/mfa/webauthn/setup` | admin autenticado + reauth, o admin pendiente tras TOTP |
| `/mfa/recovery-codes/regenerate` | auth, MFA completo, reauth, rate limit |

## Top 10 Seguridad 2025

| Código | Aplica | Control | Evidencia | Riesgo residual |
|---|---|---|---|---|
| A01 Broken Access Control | Sí | Policies, roles, MFA levels, reauth | `UserPolicy`, `SecurityAuditLogPolicy`, rutas admin | Error humano en nuevas rutas |
| A02 Security Misconfiguration | Sí | `.env.example`, headers, debug false | `SecurityHeaders`, `.env.example` | Config real fuera del repo |
| A03 Software Supply Chain Failures | Sí | audits y lockfiles | `composer audit`, `composer.lock` | npm/gitleaks si no disponibles |
| A04 Cryptographic Failures | Sí | Argon2id, Crypt, HMAC tokens | `config/hashing.php`, services | Rotacion de `APP_KEY` no cubierta |
| A05 Injection | Sí | Form Requests, Eloquent | requests admin/auth/MFA | Nuevas queries manuales |
| A06 Insecure Design | Sí | matriz roles, reauth, rate limits | docs y middlewares | WebAuthn manual pendiente |
| A07 Authentication Failures | Sí | MFA, reset seguro, no enumeration, reauth sensible | auth controllers/tests | reCAPTCHA depende de keys |
| A08 Integrity Failures | Sí | CSRF, hash chain, WebAuthn challenge | audit migration/service | Hash chain no es firma digital |
| A09 Logging/Alerting Failures | Sí | `security_audit_logs`, eventos, sanitizacion | `SecurityAuditService` | ELK opcional no implementado |
| A10 Exceptional Conditions | Sí | handler CSRF, errores genéricos, tests tokens | `Handler`, password reset tests | Errores externos dependen de config |

## Sesión Y Logs

| Control | Evidencia |
|---|---|
| Cookie `laravel_session` | `config/session.php`, `.env.example` |
| Payload de sesión cifrado | `SESSION_ENCRYPT=true` |
| Sesión autenticada solo tras factor final | `UserSessionService::completeLogin`, `MfaPendingSessionService` |
| Cierre de otras sesiones al login | `UserSessionService::completeLogin` |
| Trazabilidad sin tokens reales | `current_session_fingerprint`, `revoked_session_fingerprints` |
| Logs de auditoria | `storage/logs/audit.log`, `security_audit_logs` |
| Logs de autenticación | `storage/logs/authentication.log` |
| Logs de desarrollo | `storage/logs/development.log` |
