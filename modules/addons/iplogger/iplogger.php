<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use HiDataIPLogger\Helper;
use WHMCS\Database\Capsule;
use WHMCS\Security\Csrf;

require_once __DIR__ . '/lib/Helper.php';

function iplogger_config()
{
    return [
        'name' => 'گزارش فعالیت مشتریان',
        'description' => 'ثبت دقیق IP و agent کاربران در رویدادهای امنیتی WHMCS.',
        'author' => 'HiData',
        'language' => 'persian',
        'version' => '1.0.0',
    ];
}

function iplogger_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_iplogger')) {
            Capsule::schema()->create('mod_iplogger', function ($table) {
                $table->increments('id');
                $table->integer('client_id')->unsigned();
                $table->string('action', 50);
                $table->string('ip', 45);
                $table->string('asn', 191)->nullable();
                $table->string('country', 191)->nullable();
                $table->text('agent');
                $table->timestamp('time')->useCurrent();
                $table->index('client_id');
                $table->index('ip');
                $table->index('action');
            });
        }

        if (!Capsule::schema()->hasTable('mod_iplogger_conf')) {
            Helper::createConfigTable();
        }

        Helper::ensureDefaults();
    } catch (Exception $e) {
        return ['status' => 'error', 'description' => $e->getMessage()];
    }

    return ['status' => 'success', 'description' => 'جدول mod_iplogger ایجاد شد.'];
}

function iplogger_deactivate()
{
    return ['status' => 'success', 'description' => 'افزونه غیرفعال شد؛ جدول حذف نشد تا داده‌ها باقی بمانند.'];
}

function iplogger_output($vars)
{
    if (!Helper::adminHasAccess()) {
        echo '<div class="alert alert-danger">دسترسی غیرمجاز</div>';
        return;
    }

    $view = isset($_GET['view']) && $_GET['view'] === 'settings' ? 'settings' : 'home';

    if ($view === 'settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['token'] ?? '';
        if (!iplogger_isValidToken($token)) {
            echo '<div class="alert alert-danger">توکن معتبر نیست.</div>';
            return;
        }

        $payload = [
            'log_enabled' => isset($_POST['log_enabled']) ? 'on' : 'off',
            'action_login' => isset($_POST['action_login']) ? 'on' : 'off',
            'action_password' => isset($_POST['action_password']) ? 'on' : 'off',
            'action_email' => isset($_POST['action_email']) ? 'on' : 'off',
            'action_cancellation' => isset($_POST['action_cancellation']) ? 'on' : 'off',
            'action_register' => isset($_POST['action_register']) ? 'on' : 'off',
            'action_profile' => isset($_POST['action_profile']) ? 'on' : 'off',
            'action_order' => isset($_POST['action_order']) ? 'on' : 'off',
            'debug_logging' => isset($_POST['debug_logging']) ? 'on' : 'off',
            'trusted_proxies' => trim((string) ($_POST['trusted_proxies'] ?? '')),
            'trust_private_proxies' => isset($_POST['trust_private_proxies']) ? 'on' : 'off',
            'retention_days' => max(0, (int) ($_POST['retention_days'] ?? 180)),
        ];
        Helper::saveSettings($payload);
    }

    $settings = Helper::getSettings();

    if ($view === 'settings') {
        $data = [
            'token' => iplogger_token(),
            'settings' => $settings,
        ];

        echo iplogger_render('settings', $data);
        return;
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 25;
    $offset = ($page - 1) * $limit;

    $searchClient = trim((string) ($_GET['client_id'] ?? ''));
    $searchIp = trim((string) ($_GET['ip'] ?? ''));
    $searchAction = trim((string) ($_GET['action_name'] ?? ''));
    $searchRaw = [
        'client_id' => $searchClient,
        'ip' => $searchIp,
        'action_name' => $searchAction,
    ];

    $query = Capsule::table('mod_iplogger');
    if ($searchClient !== '') {
        $query->where('client_id', (int) $searchClient);
    }
    if ($searchIp !== '') {
        $query->where('ip', 'like', '%' . $searchIp . '%');
    }
    if ($searchAction !== '') {
        $query->where('action', $searchAction);
    }

    $total = $query->count();

    $logs = $query->orderBy('time', 'desc')
        ->limit($limit)
        ->offset($offset)
        ->get();

    $clientNames = [];
    $clientIds = [];
    foreach ($logs as $log) {
        $clientIds[] = (int) $log->client_id;
    }

    $clientIds = array_values(array_unique(array_filter($clientIds)));
    if (!empty($clientIds)) {
        $clients = Capsule::table('tblclients')
            ->select('id', 'firstname', 'lastname')
            ->whereIn('id', $clientIds)
            ->get();

        foreach ($clients as $client) {
            $fullName = trim(trim((string) $client->firstname) . ' ' . trim((string) $client->lastname));
            $clientNames[(int) $client->id] = $fullName !== '' ? $fullName : 'بدون نام';
        }
    }

    $ipList = [];
    foreach ($logs as $log) {
        $ipList[] = $log->ip;
    }

    $ipUsage = [];
    if (!empty($ipList)) {
        $ipUsageRows = Capsule::table('mod_iplogger')
            ->select('ip', Capsule::raw('COUNT(DISTINCT client_id) as clients'))
            ->whereIn('ip', $ipList)
            ->groupBy('ip')
            ->get();
        foreach ($ipUsageRows as $row) {
            $ipUsage[$row->ip] = (int) $row->clients;
        }
    }

    $data = [
        'logs' => $logs,
        'ipUsage' => $ipUsage,
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'clientNames' => $clientNames,
        'search' => [
            'client_id' => htmlspecialchars($searchClient, ENT_QUOTES, 'UTF-8'),
            'ip' => htmlspecialchars($searchIp, ENT_QUOTES, 'UTF-8'),
            'action_name' => htmlspecialchars($searchAction, ENT_QUOTES, 'UTF-8'),
        ],
        'searchEncoded' => [
            'client_id' => rawurlencode($searchRaw['client_id']),
            'ip' => rawurlencode($searchRaw['ip']),
            'action_name' => rawurlencode($searchRaw['action_name']),
        ],
    ];

    echo iplogger_render('home', $data);
}

function iplogger_isValidToken(string $token): bool
{
    if (method_exists(Csrf::class, 'isValid')) {
        return Csrf::isValid($token);
    }

    if (method_exists(Csrf::class, 'validate')) {
        return (bool) Csrf::validate($token);
    }

    if (method_exists(Csrf::class, 'token')) {
        return hash_equals(Csrf::token(), $token);
    }

    if (!isset($_SESSION['iplogger_token'])) {
        return false;
    }

    return hash_equals($_SESSION['iplogger_token'], $token);
}

function iplogger_token(): string
{
    if (method_exists(Csrf::class, 'token')) {
        return Csrf::token();
    }

    if (!isset($_SESSION['iplogger_token'])) {
        $_SESSION['iplogger_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['iplogger_token'];
}

function iplogger_render(string $template, array $data = [])
{
    extract($data, EXTR_SKIP);

    ob_start();
    include __DIR__ . '/templates/admin/' . $template . '.php';
    return ob_get_clean();
}
