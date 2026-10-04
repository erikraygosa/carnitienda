<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Configuración de correo (SMTP) administrada desde Superadmin →
 * Configuración → Correo electrónico. Si está activa y completa, sustituye en
 * tiempo de ejecución el mailer del .env (que en estos servidores es "log").
 */
class MailSettings
{
    public const PROVEEDORES = [
        'gmail' => [
            'label' => 'Gmail / Google Workspace', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls',
            'ayuda' => 'Requiere verificación en 2 pasos y una "Contraseña de aplicación" (cuenta de Google → Seguridad). No sirve la contraseña normal.',
        ],
        'outlook' => [
            'label' => 'Outlook / Microsoft 365', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls',
            'ayuda' => 'En Microsoft 365 el administrador debe tener habilitado "SMTP autenticado" para el buzón; con verificación en 2 pasos usa una contraseña de aplicación. Para cuentas personales @outlook.com / @hotmail.com usa el host smtp-mail.outlook.com.',
        ],
        'personalizado' => [
            'label' => 'Correo propio (servidor SMTP)', 'host' => '', 'port' => 587, 'encryption' => 'tls',
            'ayuda' => 'Datos que te da tu proveedor de hosting o correo: servidor SMTP, puerto (587 con TLS o 465 con SSL), usuario y contraseña.',
        ],
    ];

    /** Lee la configuración guardada. La contraseña regresa descifrada, o null si no hay/ no se puede leer. */
    public static function cargar(): array
    {
        $pass = null;
        $passIlegible = false;
        $raw = SystemSetting::get('correo.password');
        if (filled($raw)) {
            try {
                $pass = Crypt::decryptString($raw);
            } catch (\Throwable $e) {
                $passIlegible = true;
            }
        }

        return [
            'activo'       => (bool) SystemSetting::get('correo.activo', false),
            'proveedor'    => SystemSetting::get('correo.proveedor', 'gmail'),
            'host'         => (string) SystemSetting::get('correo.host', ''),
            'port'         => (int) SystemSetting::get('correo.port', 587),
            'encryption'   => (string) SystemSetting::get('correo.encryption', 'tls'),
            'username'     => (string) SystemSetting::get('correo.username', ''),
            'password'     => $pass,
            'password_set' => filled($raw),
            'password_ilegible' => $passIlegible,
            'from_name'    => (string) SystemSetting::get('correo.from_name', ''),
            'from_address' => (string) SystemSetting::get('correo.from_address', ''),
        ];
    }

    public static function completa(array $c): bool
    {
        return $c['host'] !== '' && $c['username'] !== '' && filled($c['password']);
    }

    /** Aplica la configuración al mailer de Laravel (si está activa y completa). Devuelve true si se aplicó. */
    public static function aplicar(): bool
    {
        try {
            $c = self::cargar();
        } catch (\Throwable $e) {
            return false; // BD no disponible (migraciones, etc.): se queda con el .env
        }

        if (! $c['activo'] || ! self::completa($c)) {
            return false;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), [
                'transport' => 'smtp',
                'scheme'    => $c['encryption'] === 'ssl' ? 'smtps' : 'smtp',
                'url'       => null,
                'host'      => $c['host'],
                'port'      => $c['port'],
                'username'  => $c['username'],
                'password'  => $c['password'],
                'timeout'   => 20,
            ]),
            // Gmail/Outlook solo permiten enviar "de" la propia cuenta (o un alias
            // verificado); si no se capturó remitente se usa el usuario.
            'mail.from.address' => $c['from_address'] !== '' ? $c['from_address'] : $c['username'],
            'mail.from.name'    => $c['from_name'] !== '' ? $c['from_name'] : config('app.name'),
        ]);

        // Si el mailer ya se había resuelto antes de aplicar la config, se descarta.
        app('mail.manager')->forgetMailers();

        return true;
    }
}
