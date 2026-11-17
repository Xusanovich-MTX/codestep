<?php
$authError = $_SESSION['auth_error'] ?? null;
unset($_SESSION['auth_error']);
?>
<section class="account-page">
    <a class="back-button" href="?page=login">Kirish sahifasiga qaytish</a>
    <div class="account-card">
        <p class="account-eyebrow">CODESTEP XAVFSIZLIK</p>
        <h1>Parolni qayta tiklash</h1>
        <p>Profilingizga bog‘langan email pochtangizni kiriting. Biz sizga 6 raqamli tasdiqlash kodini yuboramiz.</p>

        <?php if ($authError): ?>
            <p class="account-error" role="alert"><?php echo topicText($authError); ?></p>
        <?php endif; ?>

        <form class="account-form" method="post" action="api/auth.php">
            <input type="hidden" name="csrf_token" value="<?php echo topicText($csrfToken); ?>">
            <input type="hidden" name="action" value="forgot_password">

            <label for="forgotEmail">Email pochta</label>
            <input id="forgotEmail" type="email" name="email" required maxlength="128"
                   placeholder="misol@gmail.com" autocomplete="email" autocapitalize="none" spellcheck="false">
            <small>Ro‘yxatdan o‘tgan emailingizni kiriting.</small>

            <button class="account-button" type="submit">Tasdiqlash kodini olish</button>
        </form>

        <a class="account-switch" href="?page=login">
            Hisobingiz esingizdami? Kirish
        </a>
    </div>
</section>
