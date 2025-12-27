<?php
$actionLabels = [
    'login' => 'ورود',
    'password' => 'تغییر رمز',
    'email' => 'تغییر ایمیل',
    'cancellation' => 'درخواست لغو سرویس',
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
                            $country = htmlspecialchars((string) $log->country, ENT_QUOTES, 'UTF-8');
                            $asn = htmlspecialchars((string) $log->asn, ENT_QUOTES, 'UTF-8');
                            $otherClients = ($ipUsage[$log->ip] ?? 0) - 1;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log->time, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $log->client_id; ?></td>
                            <td><?php echo $actionLabels[$log->action] ?? htmlspecialchars($log->action, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo $ipText; ?>
                                <?php if ($otherClients > 0): ?>
                                    <div class="text-muted" style="font-size:11px;">ثبت شده برای <?php echo $otherClients; ?> مشتری دیگر</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $country !== '' ? $country : '<span class="text-muted">نامشخص</span>'; ?></td>
                            <td><?php echo $asn !== '' ? $asn : '<span class="text-muted">نامشخص</span>'; ?></td>
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
                            <a href="?module=iplogger&amp;page=<?php echo $i; ?>&amp;client_id=<?php echo $search['client_id']; ?>&amp;ip=<?php echo $search['ip']; ?>&amp;action_name=<?php echo $search['action_name']; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
