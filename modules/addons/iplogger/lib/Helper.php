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
                'agent' => $agent,
                'time' => Capsule::raw('NOW()'),
            ]);
        } catch (Throwable $e) {
            self::logSilently('HiData IP Logger insert failed: ' . $e->getMessage());
        }
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
