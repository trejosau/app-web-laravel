# Login Seguro

Proyecto Laravel 10 en desarrollo local para autenticación con roles y MFA.

## Stack

- Laravel 10
- Blade
- PostgreSQL
- TOTP
- WebAuthn / Passkeys
- Sesiones de base de datos

## Modulos

| Modulo | Archivos principales | Funcion |
|---|---|---|
| Auth | `app/Services/AuthService.php`, `app/Http/Controllers/Auth` | Login, registro, logout y reset de password |
| MFA | `app/Http/Controllers/Mfa`, `app/Services/TotpService.php`, `app/Services/WebauthnService.php` | TOTP, recovery codes y passkeys |
| RBAC | `app/Models/Role.php`, `app/Http/Middleware/EnsureRole.php`, `app/Policies` | Roles `guest`, `user`, `admin` |
| Reauth | `app/Http/Controllers/Account/ReauthController.php`, `app/Http/Controllers/Admin/ReauthController.php` | Reautenticación para acciones sensibles |
| Auditoría | `app/Services/SecurityAuditService.php`, `app/Models/SecurityAuditLog.php` | Logs de seguridad con sanitización y hash chain |
| Admin | `app/Http/Controllers/Admin` | Usuarios, auditoría y catálogo de errores |

## Seguridad aplicada

- Passwords con Argon2id.
- CSRF activo en rutas web.
- Rate limits por login, registro, MFA, recovery y admin.
- Sesión regenerada al login y logout.
- Sesión cifrada con cookie `laravel_session`.
- Cierre automático de otras sesiones al completar login.
- MFA por nivel de rol.
- TOTP con protección anti-replay.
- Passkeys restringidas a admin con MFA nivel 3 y reauth.
- Recovery codes hasheados y de un solo uso.
- TOTP secret y credential ID cifrados.
- Metadata sensible filtrada antes de auditoría.
- Logs separados: `audit.log`, `authentication.log`, `development.log`.

## Usuarios seed

`AdminUserSeeder` no contiene credenciales reales. Lee usuarios desde variables `.env`:

- `SEED_USER_<USERNAME>_EMAIL`
- `SEED_USER_<USERNAME>_PASSWORD_B64`

El password se guarda en Base64 solo para evitar caracteres especiales en `.env`; no es cifrado.

## Dependencias removidas

Sanctum fue retirado del código porque no hay uso activo de tokens/API.

En está máquina `composer.lock` no se regenero porque Composer no puede ejecutar sin `php` en PATH. Cuando PHP este disponible, regenerarlo con:

```bash
composer remove laravel/sanctum
```

## Verificación

```bash
php artisan test
```

Detalle de pruebas: `docs/security-testing.md`.
