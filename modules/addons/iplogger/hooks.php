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
    try {
        Helper::logEvent($clientId, $action, $ip, $agent);
    } catch (Throwable $e) {
        iplogger_logSilently('HiData IP Logger failed to log event: ' . $e->getMessage());
    }
}

function iplogger_detectIp(): string
{
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $candidate = $remoteIp;

    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (iplogger_isTrustedProxy($remoteIp) && !empty($forwarded)) {
        $parts = array_filter(array_map('trim', explode(',', $forwarded)));
        foreach ($parts as $entry) {
            if (filter_var($entry, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $candidate = $entry;
                break;
            }
        }
    }

    if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
        $candidate = '0.0.0.0';
    }

    return $candidate;
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
        $cutoff = (new DateTimeImmutable('now'))
            ->modify("-{$retention} days")
            ->format('Y-m-d H:i:s');

        Capsule::table('mod_iplogger')
            ->where('time', '<', $cutoff)
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

    $modalId = 'iplogger-modal-' . $clientId;

    ob_start();
    ?>
    <div class="panel panel-default" id="iplogger-panel">
        <div class="panel-heading">
            <span>IP Logs</span>
            <button class="btn btn-default btn-xs pull-right" type="button" data-toggle="modal" data-target="#<?php echo $modalId; ?>">نمایش IPهای ثبت شده</button>
        </div>
    </div>
    <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" role="dialog" aria-labelledby="<?php echo $modalId; ?>-label">
        <div class="modal-dialog" role="document" style="width:800px; max-width:90%;">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="<?php echo $modalId; ?>-label">IPهای ثبت شده</h4>
                </div>
                <div class="modal-body">
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>
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

function iplogger_isTrustedProxy(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    static $proxyConfig = null;

    if ($proxyConfig === null) {
        $settings = Helper::getSettings();
        $allowlistRaw = $settings['trusted_proxies'] ?? '';

        if ($allowlistRaw === '' && !empty($_ENV['IPLOGGER_TRUSTED_PROXIES'])) {
            $allowlistRaw = $_ENV['IPLOGGER_TRUSTED_PROXIES'];
        }

        $proxyConfig = [
            'allowlist' => array_filter(array_map('trim', preg_split('/[,\\s]+/', $allowlistRaw))),
            'trust_private' => ($settings['trust_private_proxies'] ?? 'off') === 'on',
        ];
    }

    foreach ($proxyConfig['allowlist'] as $entry) {
        if (iplogger_ipMatchesProxy($ip, $entry)) {
            return true;
        }
    }

    if ($proxyConfig['trust_private'] && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return true;
    }

    return false;
}

function iplogger_ipMatchesProxy(string $ip, string $entry): bool
{
    if ($entry === '') {
        return false;
    }

    if (strpos($entry, '/') === false) {
        return $ip === $entry;
    }

    [$subnet, $prefix] = explode('/', $entry, 2);
    if ($prefix === null || $prefix === '') {
        return false;
    }

    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }

    $prefixLength = (int) $prefix;
    $maxPrefix = strlen($ipBin) * 8;
    if ($prefixLength < 0 || $prefixLength > $maxPrefix) {
        return false;
    }

    $fullBytes = intdiv($prefixLength, 8);
    $remainingBits = $prefixLength % 8;

    if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
        return false;
    }

    if ($remainingBits === 0) {
        return true;
    }

    $maskByte = (0xFF << (8 - $remainingBits)) & 0xFF;

    return (ord($ipBin[$fullBytes]) & $maskByte) === (ord($subnetBin[$fullBytes]) & $maskByte);
}

function iplogger_logSilently(string $message): void
{
    try {
        if (function_exists('logActivity')) {
            logActivity($message);
        }
    } catch (Throwable $e) {
        // Swallow all logging errors to avoid affecting the caller.
    }
}
