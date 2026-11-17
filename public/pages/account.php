<?php
$isRegister = $page === 'register';
$authNext = codeStepNextUrl($_GET['next'] ?? null);
$authError = $_SESSION['auth_error'] ?? null;
$authSuccess = $_SESSION['auth_success'] ?? null;
unset($_SESSION['auth_error'], $_SESSION['auth_success']);
?>
<section class="account-page">
    <a class="back-button" href="?page=home">Bosh sahifaga qaytish</a>
    <div class="account-card">
        <p class="account-eyebrow">CODESTEP HISOBI</p>
        <h1><?php echo $isRegister ? 'Ro‘yxatdan o‘tish' : 'Hisobga kirish'; ?></h1>
        <p>Yechimlaringizni saqlang va shu hisob orqali boshqa qurilmada ham davom ettiring.</p>

        <?php if ($authSuccess): ?>
            <p class="account-success" role="status" style="color: #10b981; border-left: 4px solid #10b981; padding: 14px; background: rgba(16, 185, 129, 0.12); border-radius: 8px; margin: 16px 0;">
                <?php echo topicText($authSuccess); ?>
            </p>
        <?php endif; ?>

        <?php if ($authError): ?>
            <p class="account-error" role="alert"><?php echo topicText($authError); ?></p>
        <?php endif; ?>

        <form class="account-form" method="post" action="api/auth.php">
            <input type="hidden" name="csrf_token" value="<?php echo topicText($csrfToken); ?>">
            <input type="hidden" name="action" value="<?php echo $isRegister ? 'register' : 'login'; ?>">
            <input type="hidden" name="next" value="<?php echo topicText($authNext); ?>">

            <label for="accountUsername"><?php echo $isRegister ? 'Login' : 'Login yoki Email'; ?></label>
            <input id="accountUsername" name="username" required minlength="3" maxlength="64"
                   placeholder="<?php echo $isRegister ? 'foydalanuvchi_nomi' : 'Login yoki emailingiz'; ?>"
                   autocomplete="username" autocapitalize="none" spellcheck="false">
            <?php if ($isRegister): ?>
                <small>3–32 ta lotin harfi, raqam yoki pastki chiziq.</small>

                <label for="accountEmail">Email pochta (ixtiyoriy)</label>
                <input id="accountEmail" type="email" name="email" maxlength="128"
                       placeholder="misol@gmail.com" autocomplete="email" autocapitalize="none" spellcheck="false">
                <small>Parolni tiklash va hisob xavfsizligi uchun foydalaniladi.</small>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                <label for="accountPassword">Parol</label>
                <?php if (!$isRegister): ?>
                    <a href="?page=forgot-password" style="font-size: 0.85rem; text-decoration: none; color: #10b981;">Parolni unutdingizmi?</a>
                <?php endif; ?>
            </div>
            <input id="accountPassword" type="password" name="password" required minlength="8" maxlength="72"
                   placeholder="Kamida 8 ta belgi"
                   autocomplete="<?php echo $isRegister ? 'new-password' : 'current-password'; ?>">

            <?php if ($isRegister): ?>
                <label for="accountConfirm">Parolni qayta yozing</label>
                <input id="accountConfirm" type="password" name="password_confirm" required minlength="8" maxlength="72"
                       placeholder="Parolni takrorlang"
                       autocomplete="new-password">
            <?php endif; ?>

            <button class="account-button" type="submit"><?php echo $isRegister ? 'Hisob yaratish' : 'Kirish'; ?></button>
        </form>

        <a class="account-switch" href="?page=<?php echo $isRegister ? 'login' : 'register'; ?>&amp;next=<?php echo rawurlencode($authNext); ?>">
            <?php echo $isRegister ? 'Hisobingiz bormi? Kirish' : 'Yangi hisob yaratish'; ?>
        </a>
    </div>
</section>
