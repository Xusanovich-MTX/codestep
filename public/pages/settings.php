<section class="settings-page" aria-labelledby="settingsTitle">
    <header class="settings-heading">
        <p class="settings-eyebrow">O‘ZINGIZGA MOSLANG</p>
        <h1 id="settingsTitle">Sozlamalar</h1>
        <p>Qulay ko‘rinish, o‘qish odatlari va shaxsiy ma’lumotlaringizni boshqaring.</p>
    </header>
    <p id="settingsStatus" class="settings-status" role="status" aria-live="polite">Tanlovlar shu brauzerda avtomatik saqlanadi.</p>
    <div class="settings-grid">
        <section class="settings-card" aria-labelledby="appearanceTitle">
            <h2 id="appearanceTitle">Ko‘rinish</h2>
            <div class="settings-row">
                <label for="themeToggle2">Kunduzgi rejim <small>Yumshoq qaymoq rangdagi fon</small></label>
                <label class="switch"><input type="checkbox" id="themeToggle2" aria-label="Kunduzgi rejim" <?php echo $theme === 'light' ? 'checked' : ''; ?>><span class="slider round"></span></label>
            </div>
            <div class="settings-row">
                <div><h3 id="textSizeLabel">Matn o‘lchami</h3><p>Sahifalar va menyulardagi matn</p></div>
                <div class="settings-stepper" role="group" aria-labelledby="textSizeLabel">
                    <button type="button" id="decreaseFont" aria-label="Matnni kichraytirish">A−</button>
                    <output id="currentFontSize" aria-live="polite">16 px</output>
                    <button type="button" id="increaseFont" aria-label="Matnni kattalashtirish">A+</button>
                </div>
            </div>
            <div class="settings-preview"><strong>O‘rganish kichik qadamlardan boshlanadi.</strong><p>Tanlangan o‘lcham shu zahoti barcha sahifalarga qo‘llanadi.</p></div>
        </section>
        <section class="settings-card" id="notificationSettings" aria-labelledby="notificationTitle">
            <h2 id="notificationTitle">Bildirishnomalar</h2>
            <p class="settings-description">Xabarlarni yuqoridagi qo‘ng‘iroq belgisidan ko‘ring.</p>
            <div class="settings-row">
                <label for="notifTopics">Yangi mavzular <small>Keyingi tashriflarda yangi qo‘shilgan mavzular</small></label>
                <label class="switch"><input type="checkbox" id="notifTopics" aria-label="Yangi mavzular haqida bildirishnomalar" checked><span class="slider round"></span></label>
            </div>
            <div class="settings-row">
                <label for="notifDaily">Kunlik eslatma <small>Dasturlash bilan shug‘ullanishni eslatadi</small></label>
                <label class="switch"><input type="checkbox" id="notifDaily" aria-label="Kunlik eslatma"><span class="slider round"></span></label>
            </div>
            <div class="settings-row">
                <label for="reminderTime">Eslatma vaqti <small>Qurilmangizning mahalliy vaqti</small></label>
                <input type="time" id="reminderTime" value="20:00" required disabled>
            </div>
            <p class="settings-note">Eslatma sayt ochiq turganda, belgilangan vaqtda yoki undan keyin kuniga bir marta ko‘rinadi.</p>
            <button type="button" id="testNotification" class="settings-button">Sinov bildirishnomasi</button>
            <p id="notificationSettingsStatus" class="settings-status" role="status" aria-live="polite"></p>
        </section>
        <section class="settings-card" aria-labelledby="editorSettingsTitle">
            <h2 id="editorSettingsTitle">Kod muharriri</h2>
            <div class="settings-row">
                <label for="editorFontSize">Kod matni o‘lchami <small>Muharrirdagi A− / A+ bilan ham boshqariladi</small></label>
                <select id="editorFontSize">
                    <?php for ($size = 12; $size <= 24; $size++): ?>
                    <option value="<?php echo $size; ?>" <?php echo $size === 14 ? 'selected' : ''; ?>><?php echo $size; ?> px</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="settings-row">
                <label for="lineNumbers">Qator raqamlari</label>
                <label class="switch"><input type="checkbox" id="lineNumbers" aria-label="Qator raqamlarini ko‘rsatish" checked><span class="slider round"></span></label>
            </div>
            <p class="settings-note">Python va C++ uchun chekinish 4 ta bo‘sh joy. Kod o‘lchami sahifa matnidan alohida boshqariladi.</p>
            <a class="settings-link" href="?page=code-editor">Muharrirni ochish <span aria-hidden="true">→</span></a>
        </section>
        <section class="settings-card" aria-labelledby="accountSettingsTitle">
            <h2 id="accountSettingsTitle">Profil va ma’lumotlar</h2>
            <div class="settings-profile">
                <?php if ($profileImageFile !== ''): ?>
                <img src="<?php echo topicText($profileImageFile); ?>" alt="Profil rasmi" class="settings-profile-image">
                <?php else: ?>
                <span class="settings-profile-initial" aria-hidden="true"><?php echo topicText(strtoupper(substr($currentUser['username'] ?? 'C', 0, 1))); ?></span>
                <?php endif; ?>
                <div><h3><?php echo topicText($currentProfile['display_name'] ?? $currentUser['username'] ?? 'Mehmon'); ?></h3><p><?php echo $currentUser ? '@' . topicText($currentUser['username']) : 'Yechimlarni hisobingizga saqlash uchun kiring.'; ?></p></div>
            </div>
            <a class="settings-link" href="?page=profile">Profilni boshqarish <span aria-hidden="true">→</span></a>
            <a class="settings-link" href="?page=privacy">Maxfiylik siyosati <span aria-hidden="true">→</span></a>
            <a class="settings-link" href="?page=terms">Foydalanish qo‘llanmasi va shartlari <span aria-hidden="true">→</span></a>
        </section>
        <section class="settings-card settings-release" aria-labelledby="versionTitle">
            <div class="settings-release-heading"><h2 id="versionTitle">CodeStep versiyasi</h2><span id="siteVersion" class="settings-version"><?php echo SITE_VERSION; ?></span></div>
            <p class="settings-description">Yangilanish: <time datetime="2026-10-12">12-oktabr, 2026</time></p>
            <ul><li>Kitobning uch bo‘limi bo‘yicha mavzu va masalalar.</li><li>Python va C++ muharriri, kod takliflari va bajarish natijalari.</li><li>Shaxsiy profil, saqlangan yechimlar va tekshirish tarixi.</li><li>Moslashuvchan ko‘rinish va sayt ichidagi bildirishnomalar.</li></ul>
        </section>
        <section class="settings-card" aria-labelledby="resetTitle">
            <h2 id="resetTitle">Boshlang‘ich sozlamalar</h2>
            <p class="settings-description">Matn, muharrir va bildirishnoma sozlamalarini dastlabki holatga qaytarish. Yozgan kodlaringiz va bazadagi yechimlaringiz saqlanib qoladi.</p>
            <button type="button" id="resetSettings" class="settings-button">Sozlamalarni tiklash</button>
        </section>
    </div>
</section>
