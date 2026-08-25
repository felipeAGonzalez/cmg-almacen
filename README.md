# Boilerplate de Autenticación — Laravel

Boilerplate de login con Laravel 13, Breeze (Blade) y Bootstrap 5. Diseñado para clonar
como punto de partida en proyectos empresariales. Sin registro público, sin recuperación
de contraseña por correo. Los usuarios los gestiona el administrador.

---

## Stack

- **PHP** 8.2+
- **Laravel** 13.x
- **Autenticación** Laravel Breeze (stack Blade)
- **CSS** Bootstrap 5 vía CDN
- **Base de datos** MySQL / MariaDB (compatible con PostgreSQL)

---

## Instalación desde cero

```bash
composer create-project laravel/laravel nombre-proyecto
cd nombre-proyecto
composer require laravel/breeze --dev
php artisan breeze:install blade
```

> Si clonas este repositorio, omite los pasos anteriores y sigue directamente desde
> el punto **Arrancar el proyecto clonado**.

---

## Arrancar el proyecto clonado

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Copiar variables de entorno
cp .env.example .env

# 3. Generar clave de aplicación
php artisan key:generate

# 4. Configurar la base de datos en .env
#    DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 5. Ejecutar migraciones y seeders
php artisan migrate --seed

# 6. Levantar el servidor de desarrollo
php artisan serve
```

Accede en `http://localhost:8000`.

---

## Credenciales por defecto (seeder)

| Rol   | Email               | Contraseña  |
|-------|---------------------|-------------|
| Admin | admin@example.com   | Admin1234   |
| User  | user@example.com    | User1234    |

**Cambia estas credenciales antes de pasar a producción.**

---

## Crear usuarios adicionales

Via `php artisan tinker`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::create([
    'name'          => 'Nuevo',
    'last_name_one' => 'Usuario',
    'last_name_two' => null,
    'email'         => 'nuevo@example.com',
    'password'      => Hash::make('Contraseña1234'),
    'role'          => 'user', // o 'admin'
]);
```

O agrega el usuario directamente en `database/seeders/DatabaseSeeder.php` y vuelve a
ejecutar `php artisan migrate:fresh --seed`.

---

## Personalización por empresa

| Qué cambiar                          | Dónde                                              |
|--------------------------------------|----------------------------------------------------|
| Nombre de la aplicación              | `.env` → `APP_NAME`                               |
| Logo                                 | `resources/views/auth/login.blade.php` (comentario `<!-- logo -->`) |
| Color primario (botón Entrar, navbar)| Reemplaza `btn-primary` / `navbar-dark bg-primary` por clases Bootstrap o CSS propio |
| Fondo de la página de login          | `resources/views/auth/login.blade.php` → `body { background-color: ... }` |
| Contenido del home                   | `resources/views/home.blade.php`                  |
| Fuente tipográfica                   | Agrega `<link>` a Google Fonts en `layouts/app.blade.php` |

---

## Estructura de rutas

```
GET  /           → redirige a /login o /home según autenticación
GET  /login      → formulario de inicio de sesión (solo invitados)
POST /login      → autenticar
POST /logout     → cerrar sesión
GET  /home       → página principal protegida (auth + prevent.back)
```

---

## Middleware incluido

| Alias           | Clase                              | Uso                                              |
|-----------------|------------------------------------|--------------------------------------------------|
| `prevent.back`  | `PreventBackHistory`               | Evita que el botón Atrás muestre páginas privadas |
| `role`          | `EnsureUserHasRole`                | `Route::middleware('role:admin')`                |

---

## Checklist antes de producción

- [ ] `APP_ENV=production` en `.env`
- [ ] `APP_DEBUG=false` en `.env`
- [ ] Servidor con HTTPS habilitado
- [ ] `SESSION_SECURE_COOKIE=true` en `.env`
- [ ] Credenciales del seeder cambiadas
- [ ] `APP_KEY` generada (`php artisan key:generate`)
- [ ] Base de datos en servidor separado con credenciales fuertes
- [ ] Revisar permisos de `storage/` y `bootstrap/cache/` (775)
- [ ] `php artisan config:cache && php artisan route:cache`

---

## Verificar que el botón Atrás no muestra páginas privadas

1. Inicia sesión y navega a `/home`.
2. Haz clic en "Cerrar sesión".
3. Pulsa el botón Atrás del navegador.
4. Debe aparecer la página de login (o una página en blanco), **nunca** el contenido de `/home`.

Si ves el contenido de `/home` sin estar autenticado, verifica que el middleware
`PreventBackHistory` está aplicado al grupo `auth` en `routes/web.php`.
