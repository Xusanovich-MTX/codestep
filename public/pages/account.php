<?php
$isRegister = $page === 'register';
$authNext = codeStepNextUrl($_GET['next'] ?? null);
$authError = $_SESSION['auth_error'] ?? null;
unset($_SESSION['auth_error']);
?>
<section class="account-page">
    <a class="back-button" href="?page=home">Bosh sahifaga qaytish</a>
    <div class="account-card">
        <p class="account-eyebrow">CODESTEP HISOBI</p>
        <h1><?php echo $isRegister ? 'Ro‘yxatdan o‘tish' : 'Hisobga kirish'; ?></h1>
        <p>Yechimlaringizni saqlang va shu hisob orqali boshqa qurilmada ham davom ettiring.</p>
        <?php if ($authError): ?><p class="account-error" role="alert"><?php echo topicText($authError); ?></p><?php endif; ?>
        <form class="account-form" method="post" action="api/auth.php">
            <input type="hidden" name="csrf_token" value="<?php echo topicText($csrfToken); ?>">
            <input type="hidden" name="action" value="<?php echo $isRegister ? 'register' : 'login'; ?>">
            <input type="hidden" name="next" value="<?php echo topicText($authNext); ?>">
            <label for="accountUsername">Login</label>
            <input id="accountUsername" name="username" required minlength="3" maxlength="32" pattern="[A-Za-z0-9_]{3,32}" autocomplete="username" autocapitalize="none" spellcheck="false">
            <small>3–32 ta lotin harfi, raqam yoki pastki chiziq.</small>
            <label for="accountPassword">Parol</label>
            <input id="accountPassword" type="password" name="password" required minlength="8" maxlength="72" autocomplete="<?php echo $isRegister ? 'new-password' : 'current-password'; ?>">
            <?php if ($isRegister): ?>
            <label for="accountConfirm">Parolni qayta yozing</label>
            <input id="accountConfirm" type="password" name="password_confirm" required minlength="8" maxlength="72" autocomplete="new-password">
            <?php endif; ?>
            <button class="account-button" type="submit"><?php echo $isRegister ? 'Hisob yaratish' : 'Kirish'; ?></button>
        </form>
        <a class="account-switch" href="?page=<?php echo $isRegister ? 'login' : 'register'; ?>&amp;next=<?php echo rawurlencode($authNext); ?>"><?php echo $isRegister ? 'Hisobingiz bormi? Kirish' : 'Yangi hisob yaratish'; ?></a>
    </div>
</section>
