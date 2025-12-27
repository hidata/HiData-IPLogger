<?php
$settings = $settings ?? HiDataIPLogger\Helper::getSettings();
?>
<div class="card">
    <div class="card-body">
        <h2 class="card-title">تنظیمات HiData IP Logger</h2>
        <p class="text-muted">مدیریت روشن/خاموش و انتخاب رویدادهای مورد نظر.</p>
        <form method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="log_enabled" <?php echo ($settings['log_enabled'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                    فعال بودن ثبت لاگ
                </label>
            </div>
            <hr />
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="action_login" <?php echo ($settings['action_login'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                    ثبت ورود موفق
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="action_password" <?php echo ($settings['action_password'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                    ثبت تغییر رمز
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="action_email" <?php echo ($settings['action_email'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                    ثبت تغییر ایمیل
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="action_cancellation" <?php echo ($settings['action_cancellation'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                    ثبت درخواست لغو سرویس
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="action_register" <?php echo ($settings['action_register'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                    ثبت ثبت‌نام (ایجاد مشتری)
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="action_profile" <?php echo ($settings['action_profile'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                    ثبت بروزرسانی پروفایل
                </label>
            </div>
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="action_order" <?php echo ($settings['action_order'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                    ثبت سفارش جدید
                </label>
            </div>
            <div class="form-group" style="margin-top:15px; max-width:320px;">
                <label for="retentionDays">مدت نگهداری (روز)</label>
                <input id="retentionDays" type="number" class="form-control" min="0" name="retention_days" value="<?php echo (int) ($settings['retention_days'] ?? 180); ?>" />
                <p class="help-block">0 یعنی بدون حذف خودکار.</p>
            </div>
            <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
            <a href="?module=iplogger" class="btn btn-default">بازگشت</a>
        </form>
    </div>
</div>
