<?php
$actionLabels = [
    'login' => 'ورود',
    'password' => 'تغییر رمز',
    'email' => 'تغییر ایمیل',
    'cancellation' => 'درخواست لغو سرویس',
    'register' => 'ثبت‌نام',
    'profile' => 'به‌روزرسانی پروفایل',
    'order' => 'ثبت سفارش',
];

$countryNamesFa = require __DIR__ . '/../assets/countries-fa.php';
$networkLabels = [
    'mobile' => 'موبایل',
    'proxy' => 'پراکسی',
    'hosting' => 'دیتاسنتر',
];

$totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;
?>
<div class="card">
    <div class="card-body">
        <div class="pull-right">
            <a class="btn btn-default" href="?module=iplogger&amp;view=settings">تنظیمات</a>
        </div>
        <h2 class="card-title">گزارش‌های IP</h2>
        <p class="text-muted">لاگ‌های امنیتی ثبت‌شده در رویدادهای مهم.</p>

        <form method="get" class="form-inline" style="margin-bottom:15px; gap:10px;">
            <input type="hidden" name="module" value="iplogger" />
            <div class="form-group">
                <label for="searchClient">شناسه مشتری</label>
                <input id="searchClient" class="form-control" type="number" name="client_id" value="<?php echo $search['client_id']; ?>" />
            </div>
            <div class="form-group" style="margin-left:10px;">
                <label for="searchIp">IP</label>
                <input id="searchIp" class="form-control" type="text" name="ip" value="<?php echo $search['ip']; ?>" />
            </div>
            <div class="form-group" style="margin-left:10px;">
                <label for="searchAction">عملیات</label>
                <select id="searchAction" name="action_name" class="form-control">
                    <option value="">همه</option>
                    <?php foreach ($actionLabels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $search['action_name'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit" style="margin-left:10px;">جستجو</button>
        </form>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>تاریخ/ساعت</th>
                        <th>شناسه مشتری</th>
                        <th>عملیات</th>
                        <th>IP</th>
                        <th>کشور</th>
                        <th>ASN</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($logs) === 0): ?>
                    <tr><td colspan="7" class="text-center">رکوردی یافت نشد.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                            $ipText = htmlspecialchars($log->ip, ENT_QUOTES, 'UTF-8');
                            $agent = htmlspecialchars($log->agent, ENT_QUOTES, 'UTF-8');
                            $countryCode = strtoupper(trim((string) $log->country));
                            $country = htmlspecialchars($countryCode, ENT_QUOTES, 'UTF-8');
                            $countryFa = $countryNamesFa[$countryCode] ?? null;
                            $countryFaText = $countryFa !== null ? htmlspecialchars($countryFa, ENT_QUOTES, 'UTF-8') : null;
                            $flagFile = __DIR__ . '/../assets/flags/' . strtolower($countryCode) . '.svg';
                            $flagUrl = is_file($flagFile) ? '../modules/addons/iplogger/templates/assets/flags/' . strtolower($countryCode) . '.svg' : null;
                            $asn = htmlspecialchars((string) $log->asn, ENT_QUOTES, 'UTF-8');
                            $networkLabel = $networkLabels[strtolower((string) $log->network)] ?? null;
                            $otherClients = ($ipUsage[$log->ip] ?? 0) - 1;
                            $clientName = $clientNames[(int) $log->client_id] ?? 'نامشخص';
                            $clientLabel = ((int) $log->client_id) . ' - ' . $clientName;
                            $clientUrl = 'clientssummary.php?userid=' . (int) $log->client_id;
                        ?>
                        <tr>
                            <?php $formattedTime = \HiDataIPLogger\Helper::formatTimeWithJalali($log->time, '/'); ?>
                            <td>
                                <div><?php echo htmlspecialchars($formattedTime['gregorian'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if ($formattedTime['jalali'] !== ''): ?>
                                    <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($formattedTime['jalali'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo $clientUrl; ?>" target="_blank">
                                    <?php echo htmlspecialchars($clientLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?php echo $actionLabels[$log->action] ?? htmlspecialchars($log->action, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo $ipText; ?>
                                <?php if ($otherClients > 0): ?>
                                    <div class="text-muted" style="font-size:11px;">ثبت شده برای <?php echo $otherClients; ?> مشتری دیگر</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($country !== ''): ?>
                                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                        <?php if ($flagUrl !== null): ?>
                                            <img src="<?php echo $flagUrl; ?>" alt="" style="width:20px; height:13px; object-fit:cover; border-radius:2px;">
                                        <?php endif; ?>
                                        <strong><?php echo $country; ?></strong>
                                    </div>
                                    <?php if ($countryFaText !== null): ?>
                                        <div class="text-muted" style="font-size:11px; margin-top:4px;"><?php echo $countryFaText; ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">نامشخص</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $asn !== '' ? $asn : '<span class="text-muted">نامشخص</span>'; ?>
                                <?php if ($networkLabel !== null): ?>
                                    <div class="text-muted" style="font-size:11px; margin-top:4px;">شبکه <?php echo htmlspecialchars($networkLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:220px; word-break:break-all;"> <?php echo $agent; ?> </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="IP logs pagination">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <a href="?module=iplogger&amp;page=<?php echo $i; ?>&amp;client_id=<?php echo htmlspecialchars($searchEncoded['client_id'], ENT_QUOTES, 'UTF-8'); ?>&amp;ip=<?php echo htmlspecialchars($searchEncoded['ip'], ENT_QUOTES, 'UTF-8'); ?>&amp;action_name=<?php echo htmlspecialchars($searchEncoded['action_name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
