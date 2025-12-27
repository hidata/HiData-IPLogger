<?php

namespace HiDataIPLogger;

use WHMCS\Database\Capsule;
use WHMCS\User\Admin;

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
            'retention_days' => '180',
        ];
    }

    public static function getSettings(): array
    {
        $rows = Capsule::table('tbladdonmodules')
            ->where('module', self::MODULE_NAME)
            ->pluck('value', 'setting');

        $settings = self::defaults();
        foreach ($rows as $key => $value) {
            $settings[$key] = $value;
        }

        return $settings;
    }

    public static function saveSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Capsule::table('tbladdonmodules')
                ->updateOrInsert(
                    ['module' => self::MODULE_NAME, 'setting' => $key],
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
        ];

        $key = $map[$action] ?? null;
        return $key ? (($settings[$key] ?? 'off') === 'on') : false;
    }

    public static function logEvent(int $clientId, string $action, string $ip, string $agent): void
    {
        Capsule::table('mod_iplogger')->insert([
            'client_id' => $clientId,
            'action' => $action,
            'ip' => $ip,
            'asn' => null,
            'country' => null,
            'agent' => $agent,
            'time' => Capsule::raw('NOW()'),
        ]);
    }
}
