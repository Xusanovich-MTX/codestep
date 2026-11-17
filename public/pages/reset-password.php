<?php
$token = is_string($_GET['token'] ?? null) ? trim($_GET['token']) : '';
$authNotice = $_SESSION['auth_notice'] ?? null;
$authError = $_SESSION['auth_error'] ?? null;
$resetNotice = $_SESSION['reset_code_notice'] ?? null;
unset($_SESSION['auth_notice'], $_SESSION['auth_error']);
?>
<section class="account-page">
    <a class="back-button" href="?page=forgot-password">Qayta kod so‘rash</a>
    <div class="account-card">
        <p class="account-eyebrow">CODESTEP TASDIQLASH</p>
        <h1>Yangi parol o‘rnatish</h1>
        <p>Emailingizga yuborilgan 6 raqamli tasdiqlash kodini va yangi parolingizni kiriting.</p>

        <?php if ($authNotice): ?>
            <p class="account-success" role="status" style="color: #10b981; border-left: 4px solid #10b981; padding: 14px; background: rgba(16, 185, 129, 0.12); border-radius: 8px; margin: 16px 0;">
                <?php echo topicText($authNotice); ?>
            </p>
        <?php endif; ?>

        <?php if ($resetNotice && is_array($resetNotice) && !empty($resetNotice['code'])): ?>
            <div style="background: rgba(59, 130, 246, 0.15); border: 1px solid #3b82f6; border-radius: 10px; padding: 14px; margin: 16px 0;">
                <p style="margin: 0 0 6px; font-weight: 600; color: #60a5fa;">💡 Tezkor tasdiqlash kodi:</p>
                <div style="font-size: 1.5rem; letter-spacing: 4px; font-weight: bold; color: #93c5fd; font-family: monospace;">
                    <?php echo topicText($resetNotice['code']); ?>
                </div>
                <small style="color: #94a3b8; display: block; margin-top: 6px;">
                    (Agar emailingizga xat bormasa, ushbu koddan to‘g‘ridan-to‘g‘ri foydalanishingiz mumkin)
                </small>
            </div>
        <?php endif; ?>

        <?php if ($authError): ?>
            <p class="account-error" role="alert"><?php echo topicText($authError); ?></p>
        <?php endif; ?>

        <form class="account-form" method="post" action="api/auth.php">
            <input type="hidden" name="csrf_token" value="<?php echo topicText($csrfToken); ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="token" value="<?php echo topicText($token); ?>">

            <label for="resetCode">6 raqamli tasdiqlash kodi</label>
            <input id="resetCode" name="code" required minlength="6" maxlength="6" pattern="[0-9]{6}"
                   placeholder="123456" autocomplete="one-time-code" autocapitalize="none"
                   style="font-size: 1.25rem; letter-spacing: 4px; text-align: center; font-family: monospace;">

            <label for="newPassword">Yangi parol</label>
            <input id="newPassword" type="password" name="password" required minlength="8" maxlength="72"
                   placeholder="Kamida 8 ta belgi" autocomplete="new-password">

            <label for="confirmPassword">Yangi parolni takrorlang</label>
            <input id="confirmPassword" type="password" name="password_confirm" required minlength="8" maxlength="72"
                   placeholder="Parolni takrorlang" autocomplete="new-password">

            <button class="account-button" type="submit">Parolni yangilash</button>
        </form>

        <a class="account-switch" href="?page=login">
            Kirish sahifasiga qaytish
        </a>
    </div>
</section>
