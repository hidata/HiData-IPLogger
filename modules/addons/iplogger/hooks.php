<?php

use HiDataIPLogger\Helper;
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Helper.php';

/**
 * Core logger for supported actions.
 */
function iplogger_capture(int $clientId, string $action): void
{
    if ($clientId <= 0 || !Helper::shouldLogAction($action)) {
        return;
    }

    $ip = iplogger_detectIp();
    $agent = substr(iplogger_sanitizeAgent($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);
    Helper::logEvent($clientId, $action, $ip, $agent);
}

function iplogger_detectIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (!empty($forwarded)) {
        $parts = explode(',', $forwarded);
        $candidate = trim($parts[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    return $ip;
}

function iplogger_sanitizeAgent(string $agent): string
{
    $clean = strip_tags($agent);
    return preg_replace('/[\\x00-\\x1F\\x7F]/', '', $clean);
}

add_hook('ClientLogin', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    iplogger_capture($clientId, 'login');
});

add_hook('ClientChangePassword', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    iplogger_capture($clientId, 'password');
});

add_hook('ClientChangeEmail', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    iplogger_capture($clientId, 'email');
});

add_hook('AfterRequestCancellation', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    iplogger_capture($clientId, 'cancellation');
});

add_hook('ClientAdd', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    iplogger_capture($clientId, 'register');
});

add_hook('ClientEdit', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    iplogger_capture($clientId, 'profile');
});

add_hook('AfterShoppingCartCheckout', 1, function ($vars) {
    $clientId = (int) ($vars['ClientID'] ?? 0);
    if ($clientId <= 0) {
        $clientId = (int) ($vars['ClientDetails']['id'] ?? 0);
    }
    iplogger_capture($clientId, 'order');
});

add_hook('AfterCronJob', 1, function () {
    $settings = Helper::getSettings();

    $retention = max(0, (int) ($settings['retention_days'] ?? 0));
    if ($retention > 0) {
        Capsule::table('mod_iplogger')
            ->whereRaw('time < DATE_SUB(NOW(), INTERVAL ? DAY)', [$retention])
            ->delete();
    }

    if (($settings['log_enabled'] ?? 'off') !== 'on') {
        return;
    }

    $pending = Capsule::table('mod_iplogger')
        ->where(function ($query) {
            $query->whereNull('country')->orWhereNull('asn');
        })
        ->orderBy('time', 'desc')
        ->limit(20)
        ->get();

    foreach ($pending as $row) {
        $enriched = iplogger_fetchIpDetails($row->ip);
        if (!$enriched) {
            continue;
        }

        Capsule::table('mod_iplogger')
            ->where('id', $row->id)
            ->update([
                'country' => $enriched['country'],
                'asn' => $enriched['asn'],
            ]);
    }
});

add_hook('AdminAreaClientSummaryPage', 1, function ($vars) {
    if (!Helper::adminHasAccess()) {
        return '';
    }

    $clientId = (int) ($vars['userid'] ?? 0);
    if ($clientId <= 0) {
        return '';
    }

    $logs = Capsule::table('mod_iplogger')
        ->where('client_id', $clientId)
        ->orderBy('time', 'desc')
        ->limit(10)
        ->get();

    $ipList = [];
    foreach ($logs as $log) {
        $ipList[] = $log->ip;
    }

    $ipUsage = [];
    if (!empty($ipList)) {
        $usage = Capsule::table('mod_iplogger')
            ->select('ip', Capsule::raw('COUNT(DISTINCT client_id) as clients'))
            ->whereIn('ip', $ipList)
            ->groupBy('ip')
            ->get();
        foreach ($usage as $row) {
            $ipUsage[$row->ip] = (int) $row->clients;
        }
    }

    ob_start();
    ?>
    <div class="panel panel-default" id="iplogger-panel">
        <div class="panel-heading">
            <span>IP Logs</span>
            <button class="btn btn-default btn-xs pull-right" type="button" id="toggle-iplogger">نمایش IPهای ثبت شده</button>
        </div>
        <div class="panel-body" id="iplogger-body" style="display:none;">
            <?php if (count($logs) === 0): ?>
                <p class="text-muted">رکوردی برای این مشتری ثبت نشده است.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                                <th>تاریخ/ساعت</th>
                                <th>عملیات</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php $otherClients = ($ipUsage[$log->ip] ?? 0) - 1; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log->time, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($log->action, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($log->ip, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($otherClients > 0): ?>
                                        <div class="text-muted" style="font-size:10px;">ثبت شده برای <?php echo $otherClients; ?> مشتری دیگر</div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        (function() {
            var btn = document.getElementById('toggle-iplogger');
            var body = document.getElementById('iplogger-body');
            if (btn && body) {
                btn.addEventListener('click', function () {
                    if (body.style.display === 'none') {
                        body.style.display = 'block';
                        btn.textContent = 'مخفی کردن';
                    } else {
                        body.style.display = 'none';
                        btn.textContent = 'نمایش IPهای ثبت شده';
                    }
                });
            }
        })();
    </script>
    <?php
    return ob_get_clean();
});

function iplogger_fetchIpDetails(string $ip): ?array
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return null;
    }

    $url = 'https://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode,isp,message';

    try {
        $client = new \GuzzleHttp\Client(['timeout' => 5, 'http_errors' => false]);
        $response = $client->get($url);
        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return null;
        }

        return [
            'country' => $data['countryCode'] ?? null,
            'asn' => $data['isp'] ?? null,
        ];
    } catch (Exception $e) {
        return null;
    }
}
