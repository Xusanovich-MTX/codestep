<?php
if (!$currentUser): ?>
<section class="account-page"><div class="account-card"><h1>Shaxsiy profil</h1><p>Profilingiz, saqlangan kodlaringiz va natijalaringizni ko‘rish uchun hisobingizga kiring.</p><a class="account-button" href="?page=login">Hisobga kirish</a><a href="?page=register">Ro‘yxatdan o‘tish</a></div></section>
<?php return; endif;
if (!$currentProfile): ?>
<section class="account-page"><div class="account-card"><h1>Shaxsiy profil</h1><p class="account-error" role="alert">Profilni yuklab bo‘lmadi. Sahifani yangilab qayta urinib ko‘ring.</p><form action="api/auth.php" method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?php echo topicText($csrfToken); ?>"><button class="account-button" type="submit">Hisobdan chiqish</button></form></div></section>
<?php return; endif;
$stats = null;
try { $stats = profileStats($currentUser['id']); }
catch (Throwable $error) { error_log('CodeStep profile statistics: ' . $error->getMessage()); }
$joinedAt = (new DateTimeImmutable($currentProfile['created_at']))->setTimezone(new DateTimeZone('Asia/Tashkent'))->format('d.m.Y');
$initial = strtoupper(substr($currentUser['username'], 0, 1));
?>
<section class="solutions-page profile-page" id="profilePage" data-csrf-token="<?php echo topicText($csrfToken); ?>" data-initial="<?php echo topicText($initial); ?>">
    <a class="back-button" href="?page=home">Bosh sahifaga qaytish</a>
    <header class="account-card profile-hero">
        <div class="profile-avatar-group">
            <?php if ($profileImageFile !== ''): ?>
            <img class="profile-avatar" id="profileAvatarMain" data-profile-avatar src="<?php echo topicText($profileImageFile); ?>" alt="Profil rasmi" width="112" height="112">
            <?php else: ?>
            <span class="profile-avatar profile-initial" id="profileAvatarMain" data-profile-avatar aria-label="Profil rasmi"><?php echo topicText($initial); ?></span>
            <?php endif; ?>
        </div>
        <div class="profile-heading"><p class="profile-eyebrow">SHAXSIY PROFIL</p><h1 data-profile-name><?php echo topicText($currentProfile['display_name']); ?></h1><p class="account-muted">@<?php echo topicText($currentProfile['username']); ?> · <?php echo $joinedAt; ?> dan beri</p><p id="profileBioPreview" class="profile-bio"<?php echo $currentProfile['bio'] === '' ? ' hidden' : ''; ?>><?php echo topicText($currentProfile['bio']); ?></p></div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <a class="account-button" href="?page=solutions">Yechimlarim</a>
            <?php if ($currentUser['username'] === 'mamurovtohir'): ?>
            <a class="account-button" href="?page=admin" style="background: #2563eb; border-color: #2563eb; color: #fff !important;">👑 Admin Panel</a>
            <?php endif; ?>
        </div>
    </header>
    <section aria-labelledby="profileStatsTitle">
        <h2 id="profileStatsTitle">Mening natijalarim</h2>
        <?php if ($stats !== null): ?>
        <div class="profile-stats">
            <div class="account-card profile-stat"><strong data-stat="solved"><?php echo $stats['solved']; ?></strong><span>Yechilgan masalalar</span></div>
            <div class="account-card profile-stat"><strong data-stat="drafts"><?php echo $stats['drafts']; ?></strong><span>Saqlangan qoralamalar</span></div>
            <div class="account-card profile-stat"><strong data-stat="attempts"><?php echo $stats['attempts']; ?></strong><span>Tekshirish urinishlari</span></div>
        </div>
        <p class="account-muted profile-stats-note">Python: <?php echo $stats['python_attempts']; ?> ta urinish · C++: <?php echo $stats['cpp_attempts']; ?> ta urinish. Faqat “Qabul qilindi” natijali masalalar yechilgan deb hisoblanadi.</p>
        <?php else: ?><p class="account-error" role="alert">Statistikani yuklab bo‘lmadi. Keyinroq qayta urinib ko‘ring.</p><?php endif; ?>
    </section>
    <div class="profile-edit-grid">
        <section class="account-card" aria-labelledby="profileDetailsTitle">
            <h2 id="profileDetailsTitle">Profil ma’lumotlari</h2>
            <form class="account-form" id="profileForm" action="api/profile.php" method="post">
                <input type="hidden" name="action" value="save">
                <label for="profileLogin">Login</label>
                <input id="profileLogin" value="<?php echo topicText($currentProfile['username']); ?>" readonly aria-describedby="profileLoginHelp">
                <small id="profileLoginHelp">Hisobga shu login bilan kirasiz.</small>
                <label for="profileName">Ism-familiya</label>
                <input id="profileName" name="display_name" required autocomplete="name" aria-describedby="profileNameHelp" value="<?php echo topicText($currentProfile['display_name']); ?>">
                <small id="profileNameHelp">80 ta belgigacha.</small>
                <label for="profileBio">O‘zingiz haqingizda</label>
                <textarea id="profileBio" name="bio" rows="4" aria-describedby="profileBioCount" placeholder="Qaysi tillarni o‘rganyapsiz, maqsadingiz nima?"><?php echo topicText($currentProfile['bio']); ?></textarea>
                <small id="profileBioCount">280 ta belgigacha</small>
                <div><button type="submit" class="account-button" id="profileSave">O‘zgarishlarni saqlash</button></div>
                <p class="profile-status" id="profileSaveStatus" role="status" aria-live="polite"></p>
            </form>
        </section>
        <section class="account-card" aria-labelledby="profilePhotoTitle">
            <h2 id="profilePhotoTitle">Profil rasmi</h2>
            <p class="account-muted">O‘zingizga yoqqan rasmni tanlang. U profilingiz va yon menyuda ko‘rinadi.</p>
            <label class="profile-upload-control" for="profileAvatarInput">Rasm tanlash</label>
            <input class="profile-upload-input" type="file" id="profileAvatarInput" accept="image/jpeg,image/png,image/webp" aria-describedby="profileAvatarHelp">
            <p id="profileAvatarHelp" class="account-muted">JPG, PNG yoki WebP · 2 MB gacha · 4096 × 4096 pikselgacha</p>
            <button type="button" class="profile-secondary" id="profileAvatarRemove"<?php echo !$currentProfile['has_avatar'] ? ' hidden' : ''; ?>>Rasmni olib tashlash</button>
            <p class="profile-status" id="profileAvatarStatus" role="status" aria-live="polite"></p>
        </section>
    </div>
    <section class="account-card profile-security" aria-labelledby="profilePasswordTitle">
        <h2 id="profilePasswordTitle">Parolni yangilash</h2>
        <form class="account-form" id="profilePasswordForm" action="api/profile-password.php" method="post">
            <div class="profile-password-fields">
                <div><label for="profileCurrentPassword">Joriy parol</label><input type="password" id="profileCurrentPassword" name="current_password" required maxlength="72" autocomplete="current-password"></div>
                <div><label for="profileNewPassword">Yangi parol</label><input type="password" id="profileNewPassword" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password"></div>
                <div><label for="profileConfirmPassword">Yangi parolni takrorlang</label><input type="password" id="profileConfirmPassword" name="password_confirm" required minlength="8" maxlength="72" autocomplete="new-password"></div>
            </div>
            <small>Yangi parol kamida 8 ta belgidan iborat bo‘lsin.</small>
            <div><button class="account-button" id="profilePasswordSave" type="submit">Parolni yangilash</button></div>
            <p class="profile-status" id="profilePasswordStatus" role="status" aria-live="polite"></p>
        </form>
    </section>
    <section class="account-card profile-danger" id="deleteAccountSection" aria-labelledby="profileDeleteTitle" style="border-color: rgba(239, 68, 68, 0.45);">
        <h2 id="profileDeleteTitle" style="color: #f87171;">Hisobni butunlay o‘chirish</h2>
        <p class="account-muted">Hisobingizni o‘chirsangiz profilingiz, saqlangan kod qoralamalaringiz, yechimlar tarixi va musobaqa natijalaringiz bazadan qayta tiklanmaydigan qilib butunlay o‘chiriladi.</p>
        <form class="account-form" id="profileDeleteForm" action="api/profile.php" method="post">
            <input type="hidden" name="action" value="delete_account">
            <div>
                <label for="profileDeletePassword">Tasdiqlash uchun joriy parolingizni kiriting</label>
                <input type="password" id="profileDeletePassword" name="current_password" required maxlength="72" autocomplete="current-password" placeholder="Joriy parol">
            </div>
            <div>
                <button class="account-button" id="profileDeleteBtn" type="submit" style="background: #dc2626; border-color: #dc2626; color: #fff !important;">Hisobni butunlay o‘chirish</button>
            </div>
            <p class="profile-status" id="profileDeleteStatus" role="status" aria-live="polite"></p>
        </form>
    </section>
    <div class="profile-footer"><a class="account-button" href="?page=home">Masalalarni yechishga o‘tish</a><form action="api/auth.php" method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?php echo topicText($csrfToken); ?>"><button class="profile-secondary" type="submit">Hisobdan chiqish</button></form></div>
    <noscript><p class="account-error">Profilni tahrirlash uchun JavaScript’ni yoqing.</p></noscript>
</section>
