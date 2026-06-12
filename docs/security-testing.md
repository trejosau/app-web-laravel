# Pruebas Realizadas

## Automatizadas

| Prueba | Archivo | Cobertura |
|---|---|---|
| Login guest | `tests/Feature/LoginTest.php` | Login password-only, auditoria `login.success`, sesion no pendiente |
| Login MFA pendiente | `tests/Feature/LoginTest.php` | No autentica antes de TOTP/WebAuthn |
| Rate limit login | `tests/Feature/LoginTest.php` | 429 y auditoria `rate_limit.blocked` |
| Revocar otras sesiones | `tests/Feature/LoginTest.php` | Cierra sesiones de otro navegador y guarda huellas HMAC |
| Registro guest | `tests/Feature/RegisterTest.php` | Argon2id, rol guest, metadata de auditoria |
| Rate limit registro | `tests/Feature/RegisterTest.php` | Bloqueo por misma identidad |
| Dashboards por rol | `tests/Feature/DashboardAuthorizationTest.php` | Componentes `guest`, `user`, `admin` |
| Sesion cifrada | `tests/Feature/SecurityHardeningTest.php` | `SESSION_ENCRYPT=true`, cookie `laravel_session`, locale `es` |
| Inputs sin required HTML | `tests/Feature/SecurityHardeningTest.php` | Login y registro sin atributo `required` |
| Sanitizacion de auditoria | `tests/Unit/SecurityAuditServiceTest.php` | No guarda password, OTP, token ni challenge reales |

## Comando

```bash
php artisan test
```

## Resultado local

`php artisan test` ejecutado correctamente: 83 pruebas pasaron, 369 aserciones.

Nota: PHPUnit mostro una advertencia de esquema XML deprecado en `phpunit.xml`; no bloqueo la suite.
