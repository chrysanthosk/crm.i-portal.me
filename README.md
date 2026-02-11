# crm.i-portal.me (Custom files overlay)

These files are meant to be **copied into a fresh Laravel project** (created via `composer create-project`) and will add:

- Login (remember me + forgot password) + light/dark theme toggle
- Users CRUD (admin)
- Roles + Permission assignment (admin)
- Profile (edit info + password change + email change with confirmation email)
- 2FA (TOTP) management page
- Settings: SMTP + Configuration (admin)
- Audit Log (admin)

## 1) Create the Laravel project

```bash
composer create-project laravel/laravel crm.i-portal.me
cd crm.i-portal.me
```

## 2) Install auth scaffolding (Breeze)

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install
```

## 3) Optional (recommended): QR code generator for the 2FA page

```bash
composer require chillerlan/php-qrcode:^5.0
```

If you skip this package, the 2FA page still works (secret + code verify), but QR rendering may be blank.

## 4) Copy these custom files into your project root

Unzip/copy the contents of this overlay so it merges into your Laravel tree (it includes `routes/`, `app/`, `database/`, `resources/`).

## 5) Register middleware

### If your Laravel has `app/Http/Kernel.php` (Laravel 10 style)

Add:

```php
protected $middlewareGroups = [
  'web' => [
     // ...
     \App\Http\Middleware\EnsureTheme::class,
  ],
];

protected $routeMiddleware = [
  // ...
  'theme' => \App\Http\Middleware\EnsureTheme::class,
  'permission' => \App\Http\Middleware\RequirePermission::class,
];
```

### If your Laravel uses `bootstrap/app.php` middleware registration (Laravel 11/12 style)

Inside `->withMiddleware(function (Middleware $middleware) { ... })` add:

```php
$middleware->web(append: [
    \App\Http\Middleware\EnsureTheme::class,
]);

$middleware->alias([
    'theme' => \App\Http\Middleware\EnsureTheme::class,
    'permission' => \App\Http\Middleware\RequirePermission::class,
]);
```

## 6) Migrate + seed

```bash
php artisan migrate --seed
```

Default admin user:

- Email: `admin@example.com`
- Password: `ChangeMe123!!`
- Role: `admin`

## 7) Run

```bash
npm run build
php artisan view:clear
php artisan route:clear
php artisan optimize:clear
php artisan serve
```

Then open the URL shown by `php artisan serve`.
