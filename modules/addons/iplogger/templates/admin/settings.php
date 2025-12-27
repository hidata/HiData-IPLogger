<?php
$settings = $settings ?? HiDataIPLogger\Helper::getSettings();
?>
<div class="card">
    <div class="card-body">
        <div class="pull-right">
            <a href="?module=iplogger" class="btn btn-default">بازگشت به گزارش‌ها</a>
        </div>
        <h2 class="card-title">تنظیمات گزارش فعالیت مشتریان</h2>
        <p class="text-muted">روشن/خاموش کردن افزونه، انتخاب رویدادها و مدیریت شبکه/نگهداری.</p>
        <form method="post" class="form-horizontal" style="margin-top:15px;">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" />

            <div class="alert alert-info" style="margin-top:10px;">
                <strong>راهنما:</strong> بروزرسانی کشور/ISP در کران تکرارشونده انجام می‌شود؛ پاکسازی داده‌ها در کران روزانه.
            </div>

            <div class="row">
                <div class="col-sm-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">وضعیت افزونه</div>
                        <div class="panel-body">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="log_enabled" <?php echo ($settings['log_enabled'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                    فعال بودن ثبت لاگ
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading">رویدادهای قابل ثبت</div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="action_login" <?php echo ($settings['action_login'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                            ورود موفق
                                        </label>
                                    </div>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="action_password" <?php echo ($settings['action_password'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                            تغییر رمز عبور
                                        </label>
                                    </div>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="action_email" <?php echo ($settings['action_email'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                            تغییر ایمیل
                                        </label>
                                    </div>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="action_cancellation" <?php echo ($settings['action_cancellation'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                            درخواست لغو سرویس
                                        </label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="action_register" <?php echo ($settings['action_register'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                            ثبت‌نام / ایجاد مشتری
                                        </label>
                                    </div>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="action_profile" <?php echo ($settings['action_profile'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                            بروزرسانی پروفایل
                                        </label>
                                    </div>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="action_order" <?php echo ($settings['action_order'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                            ثبت سفارش جدید
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">شبکه و پروکسی</div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label class="control-label" for="trustedProxies">پروکسی‌های معتمد (IP یا CIDR)</label>
                                <textarea id="trustedProxies" name="trusted_proxies" class="form-control" rows="3" placeholder="مثال: 203.0.113.10, 203.0.113.0/24, 2001:db8::/32"><?php echo htmlspecialchars($settings['trusted_proxies'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <p class="help-block" style="margin-bottom:0;">فقط درخواست‌هایی که REMOTE_ADDR آنها در این لیست است به عنوان پروکسی معتمد جهت استفاده از X-Forwarded-For شناخته می‌شوند.</p>
                            </div>
                            <div class="checkbox" style="margin-top:10px;">
                                <label>
                                    <input type="checkbox" name="trust_private_proxies" <?php echo ($settings['trust_private_proxies'] ?? 'off') === 'on' ? 'checked' : ''; ?> />
                                    اعتماد به بازه‌های خصوصی/رزرو شده
                                </label>
                                <p class="help-block" style="margin-bottom:0;">فقط در صورت نیاز و آگاهی فعال شود.</p>
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-default">
                        <div class="panel-heading">نگهداری داده</div>
                        <div class="panel-body">
                            <div class="form-group" style="max-width:260px;">
                                <label class="control-label" for="retentionDays">مدت نگهداری (روز)</label>
                                <input id="retentionDays" type="number" class="form-control" min="0" name="retention_days" value="<?php echo (int) ($settings['retention_days'] ?? 180); ?>" />
                                <p class="help-block" style="margin-bottom:0;">0 یعنی بدون حذف خودکار.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-left" style="margin-top:10px;">
                <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
                <a href="?module=iplogger" class="btn btn-default">انصراف</a>
            </div>
        </form>
    </div>
</div>
