<?php

namespace HiDataIPLogger;

use WHMCS\Database\Capsule;
use WHMCS\User\Admin;
use Throwable;

class Helper
{
    public const MODULE_NAME = 'iplogger';

    public static function defaults(): array
    {
        return [
            'log_enabled' => 'on',
            'action_login' => 'on',
            'action_password' => 'on',
            'action_email' => 'on',
            'action_cancellation' => 'on',
            'action_register' => 'on',
            'action_profile' => 'on',
            'action_order' => 'on',
            'debug_logging' => 'off',
            'trusted_proxies' => '',
            'trust_private_proxies' => 'off',
            'retention_days' => '180',
        ];
    }

    public static function getSettings(): array
    {
        $rows = [];
        if (Capsule::schema()->hasTable('mod_iplogger_conf')) {
            $rows = Capsule::table('mod_iplogger_conf')
                ->pluck('value', 'setting');
        }

        $settings = self::defaults();
        foreach ($rows as $key => $value) {
            $settings[$key] = $value;
        }

        return $settings;
    }

    public static function saveSettings(array $settings): void
    {
        if (!Capsule::schema()->hasTable('mod_iplogger_conf')) {
            self::createConfigTable();
        }

        foreach ($settings as $key => $value) {
            Capsule::table('mod_iplogger_conf')
                ->updateOrInsert(
                    ['setting' => $key],
                    ['value' => $value]
                );
        }
    }

    public static function ensureDefaults(): void
    {
        $current = self::getSettings();
        self::saveSettings($current);
    }

    public static function adminHasAccess(): bool
    {
        if (!defined('ADMINAREA') || !isset($_SESSION['adminid'])) {
            return false;
        }

        $admin = Admin::find((int) $_SESSION['adminid']);
        if (!$admin) {
            return false;
        }

        return $admin->hasPermission('Configure Addon Modules') || $admin->hasPermission('View Clients');
    }

    public static function isDebugEnabled(): bool
    {
        static $enabled = null;

        if ($enabled === null) {
            $settings = self::getSettings();
            $enabled = ($settings['debug_logging'] ?? 'off') === 'on';
        }

        return (bool) $enabled;
    }

    public static function debugLog(string $action, $request = '', $response = '', $data = ''): void
    {
        if (!self::isDebugEnabled()) {
            return;
        }

        try {
            if (function_exists('logModuleCall')) {
                logModuleCall(self::MODULE_NAME, $action, $request, $response, $data);
            } elseif (function_exists('logActivity')) {
                $parts = array_filter([
                    $action,
                    is_string($request) ? $request : json_encode($request),
                    is_string($response) ? $response : json_encode($response),
                    is_string($data) ? $data : json_encode($data),
                ]);
                logActivity('[iplogger debug] ' . implode(' | ', $parts));
            }
        } catch (Throwable $e) {
            self::logSilently('HiData IP Logger debug log failed: ' . $e->getMessage());
        }
    }

    public static function shouldLogAction(string $action): bool
    {
        $settings = self::getSettings();
        if (($settings['log_enabled'] ?? 'off') !== 'on') {
            return false;
        }

        $map = [
            'login' => 'action_login',
            'password' => 'action_password',
            'email' => 'action_email',
            'cancellation' => 'action_cancellation',
            'register' => 'action_register',
            'profile' => 'action_profile',
            'order' => 'action_order',
        ];

        $key = $map[$action] ?? null;
        return $key ? (($settings[$key] ?? 'off') === 'on') : false;
    }

    public static function logEvent(int $clientId, string $action, string $ip, string $agent): void
    {
        static $tableReady = null;

        if ($tableReady === null) {
            $tableReady = Capsule::schema()->hasTable('mod_iplogger');
        }

        if (!$tableReady) {
            return;
        }

        try {
            Capsule::table('mod_iplogger')->insert([
                'client_id' => $clientId,
                'action' => $action,
                'ip' => $ip,
                'asn' => null,
                'country' => null,
                'network' => null,
                'agent' => $agent,
                'time' => Capsule::raw('NOW()'),
            ]);
        } catch (Throwable $e) {
            self::logSilently('HiData IP Logger insert failed: ' . $e->getMessage());
        }
    }

    /**
     * Format a timestamp to both Gregorian and Jalali strings for display.
     */
    public static function formatTimeWithJalali($timestamp, string $separator = '/', ?string $timezone = null): array
    {
        $result = ['gregorian' => '', 'jalali' => ''];

        try {
            $tz = $timezone ? new \DateTimeZone($timezone) : new \DateTimeZone(date_default_timezone_get());
            $dt = new \DateTimeImmutable((string) $timestamp, $tz);
            $dt = $dt->setTimezone($tz);

            $result['gregorian'] = $dt->format('Y-m-d H:i:s');

            $gy = (int) $dt->format('Y');
            $gm = (int) $dt->format('n');
            $gd = (int) $dt->format('j');

            if (function_exists('gregorian_to_jalali')) {
                $jalaliParts = gregorian_to_jalali($gy, $gm, $gd);
                if (is_array($jalaliParts) && count($jalaliParts) === 3) {
                    [$jy, $jm, $jd] = $jalaliParts;
                    $result['jalali'] = sprintf('%04d%s%02d%s%02d', $jy, $separator, $jm, $separator, $jd);
                }
            }
        } catch (Throwable $e) {
            // Ignore formatting failures; leave empty values.
        }

        return $result;
    }

    public static function createConfigTable(): void
    {
        Capsule::schema()->create('mod_iplogger_conf', function ($table) {
            $table->string('setting')->unique();
            $table->text('value')->nullable();
        });
    }

    private static function logSilently(string $message): void
    {
        try {
            if (function_exists('logActivity')) {
                logActivity($message);
            }
        } catch (Throwable $e) {
            // Ignore logging failures to avoid affecting callers.
        }
    }
}
