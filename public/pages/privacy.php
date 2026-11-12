<?php
require_once dirname(__DIR__) . '/includes/code-execution.php';
$privacyExecutionProvider = codeExecutionProvider();
?>
<section class="solutions-page terms-page" aria-labelledby="privacy-title">
    <header class="terms-hero">
        <div class="terms-hero-copy">
            <p class="terms-eyebrow">CODESTEP · MA’LUMOTLARINGIZ</p>
            <h1 id="privacy-title">Maxfiylik siyosati</h1>
            <p class="terms-lead">Bu sahifada CodeStep’dan foydalanganda qaysi ma’lumotlar saqlanishi, ular nima uchun kerakligi va ularni qanday boshqarish mumkinligi tushuntiriladi.</p>
            <p class="terms-caption">Oxirgi yangilanish: <time datetime="2026-10-12">2026-yil 12-oktabr</time>.</p>
            <div class="terms-actions">
                <a class="account-button" href="?page=settings">Sozlamalarni ochish <span aria-hidden="true">→</span></a>
                <a class="terms-secondary" href="?page=contact">Maxfiylik bo‘yicha bog‘lanish</a>
            </div>
        </div>
        <div class="terms-hero-note">
            <span class="terms-code-mark" aria-hidden="true">{ }</span>
            <p>Ma’lumotlaringiz<br><strong>haqida ochiq.</strong></p>
            <span class="terms-language-tag">Hisob · Kod · Sozlamalar</span>
        </div>
    </header>

    <div class="terms-layout">
        <nav class="terms-toc" aria-label="Maxfiylik siyosati bo‘limlari">
            <h2>Shu sahifada</h2>
            <ol>
                <li><a href="#privacy-data"><span>01</span> Qanday ma’lumotlar</a></li>
                <li><a href="#privacy-purpose"><span>02</span> Nima uchun ishlatiladi</a></li>
                <li><a href="#privacy-browser"><span>03</span> Brauzerda saqlash</a></li>
                <li><a href="#privacy-access"><span>04</span> Kimga uzatiladi</a></li>
                <li><a href="#privacy-retention"><span>05</span> Saqlash va himoya</a></li>
                <li><a href="#privacy-control"><span>06</span> Sizning boshqaruvingiz</a></li>
                <li><a href="#privacy-contact"><span>07</span> Murojaat va yangilanishlar</a></li>
            </ol>
        </nav>

        <div class="terms-content">
            <section class="terms-card" id="privacy-data" aria-labelledby="privacy-data-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">01</span><h2 id="privacy-data-title">Qaysi ma’lumotlar saqlanadi?</h2></div>
                <ul class="terms-list">
                    <li><strong>Hisob:</strong> ro‘yxatdan o‘tishda tanlagan loginingiz, parolingizning xesh qiymati va hisob yaratilgan vaqt. Ro‘yxatdan o‘tish uchun email yoki telefon raqami so‘ralmaydi.</li>
                    <li><strong>Profil:</strong> ko‘rsatiladigan ism, o‘zingiz haqingizdagi izoh, yuklagan profil rasmingiz va yangilangan vaqt. Dastlab ko‘rsatiladigan ism sifatida login ishlatiladi. Izoh va rasmni qo‘shish ixtiyoriy.</li>
                    <li><strong>Yechimlar:</strong> hisobga kirib bazaga saqlagan kod, masala va mavzu raqami, dasturlash tili hamda saqlangan vaqt. Masalani bajarib tekshirganingizda yuborilgan kod, natija, test kirishi va tekshiruv xulosasi ham hisobingiz tarixiga yoziladi.</li>
                    <li><strong>Kodni qayta ishlash:</strong> bajarish uchun kod va kirish ma’lumotlari serverga yuboriladi. Sintaksisni tekshirish va formatlash funksiyalari ham kodni serverga yuborishi mumkin. Bu mehmon sifatida ishlaganingizda ham amal qiladi.</li>
                    <li><strong>Texnik ma’lumotlar:</strong> saytga ulanishda server IP manzili va brauzer yuborgan so‘rov ma’lumotlarini oladi. Kirish urinishlarini cheklash uchun IP manzili va login asosidagi xesh, urinishlar soni hamda cheklov vaqti ishlatiladi. Server sozlamalariga qarab so‘rovlar va xatolar jurnalda saqlanishi mumkin.</li>
                </ul>
                <p class="terms-note">Erkin muharrirni ishga tushirishning o‘zi hisobingizda yechimlar tarixini yaratmaydi. Mehmon qoralamalari esa brauzerda saqlanadi.</p>
            </section>

            <section class="terms-card" id="privacy-purpose" aria-labelledby="privacy-purpose-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">02</span><h2 id="privacy-purpose-title">Ma’lumotlar nima uchun kerak?</h2></div>
                <ul class="terms-list">
                    <li>Hisobga kirishni tekshirish va shaxsiy profilingizni ko‘rsatish.</li>
                    <li>Qoralamani davom ettirish, kodni bajarish, masala javobini tekshirish va urinishlar tarixini ochish.</li>
                    <li>Yechilgan masalalar va urinishlar statistikasini hisoblash.</li>
                    <li>Tanlagan ko‘rinish va muharrir sozlamalarini eslab qolish, sayt ichidagi bildirishnomalarni boshqarish.</li>
                    <li>Xatolarni aniqlash, hisobga haddan tashqari ko‘p kirish urinishlarini cheklash va murojaatingizga javob berish.</li>
                </ul>
                <p>Masala bo‘yicha xulosa dastur natijasini oldindan belgilangan testlar bilan solishtirish orqali avtomatik chiqariladi. Xulosalar profilingizdagi o‘quv statistikasiga ta’sir qiladi. Tekshiruv qanday ishlashini <a href="?page=terms#terms-results">foydalanish qo‘llanmasi</a>dan o‘qing.</p>
            </section>

            <section class="terms-card" id="privacy-browser" aria-labelledby="privacy-browser-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">03</span><h2 id="privacy-browser-title">Cookie va brauzer xotirasi</h2></div>
                <div class="terms-feature-grid">
                    <div class="terms-feature">
                        <h3>Cookie fayllari</h3>
                        <p>Seans cookie’si hisobga kirganingizni va so‘rov tasdig‘ini saqlashga yordam beradi. Ko‘rinish rejimi uchun <code>theme</code> cookie’si 30 kun, boshlang‘ich animatsiyani qayta ko‘rsatmaslik uchun <code>visited</code> cookie’si 1 soatga yoziladi. Tanlov yangilanganda bu muddat ham yangilanishi mumkin.</p>
                    </div>
                    <div class="terms-feature">
                        <h3>Shu qurilmadagi qoralamalar</h3>
                        <p>Kod, kirish qiymatlari va muharrir holati brauzerning mahalliy xotirasida saqlanadi. Hisobga kirish vaqtida qoralamani yo‘qotmaslik uchun vaqtincha seans xotirasi ham ishlatiladi. Mahalliy nusxa boshqa qurilmaga avtomatik ko‘chmaydi.</p>
                    </div>
                </div>
                <p>Matn o‘lchami, menyu holati, muharrir shrifti, qator sozlamalari, kod takliflari hamda bildirishnoma tanlovlari shu brauzerda eslab qolinadi. Bildirishnomalarning o‘qilgan yoki yopilgan holati ham shu qurilmaga bog‘liq.</p>
                <p>O‘quv eslatmalari sayt ichida, sayt ochiq paytda ishlaydi. Sozlamadagi bildirishnoma tanlovi email, SMS yoki sayt yopiq paytda ishlaydigan push xabariga obuna qilmaydi.</p>
                <p class="terms-note"><strong>Hisobdan chiqish brauzerdagi kod nusxalarini o‘chirmaydi.</strong> Umumiy kompyuterda ishlagan bo‘lsangiz, kerakli kodni yuklab olgach brauzer sozlamalaridan aynan CodeStep uchun saqlangan sayt ma’lumotlarini tozalang. Bu qurilmadagi qoralama va tanlovlar yo‘qoladi; serverdagi hisob va yechimlar alohida saqlanadi.</p>
            </section>

            <section class="terms-card" id="privacy-access" aria-labelledby="privacy-access-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">04</span><h2 id="privacy-access-title">Ma’lumotlarni kim olishi mumkin?</h2></div>
                <p>Hisob, profil va yechimlar CodeStep serverida qayta ishlanadi. Sayt sahifalarida shaxsiy profil, rasm va yechimlarni ko‘rish hisob egasining seansi orqali tekshiriladi. Ular uchun ommaviy profil yoki ommaviy yechimlar ro‘yxati mavjud emas. Server va ma’lumotlar bazasini boshqaradigan administrator texnik xizmat davomida bu ma’lumotlarga kirishi mumkin.</p>
                <?php if ($privacyExecutionProvider === 'wandbox'): ?>
                <p><strong>Wandbox:</strong> bajarish tugmasini yoki <kbd>Ctrl</kbd> + <kbd>Enter</kbd>ni bosganingizda kodingiz va kirish qiymatlari CodeStep serveri orqali Wandbox tashqi kompilyatsiya xizmatiga yuboriladi. Masalani tekshirishda har bir test uchun kod va test kirishi alohida yuborilishi mumkin. Bu mehmon sifatida ishlaganda ham amal qiladi.</p>
                <p>CodeStep Wandbox’ga hisobingiz paroli, seans cookie’si yoki profil ma’lumotlarini qo‘shib yubormaydi. Ammo kod va kirish qiymatlariga o‘zingiz yozgan ma’lumotlar yuboriladi. So‘rov shifrlangan HTTPS orqali jo‘natiladi. Kod uchun doimiy ulashish havolasi yaratish so‘ralmaydi; bu xizmat kodni umuman saqlamaydi degani emas. Wandbox’dagi saqlash muddati va ma’lumotlarni qayta ishlash tartibini CodeStep belgilamaydi.</p>
                <?php elseif ($privacyExecutionProvider === 'private'): ?>
                <p>Kod bajarish uchun administrator ulagan alohida xizmat ishlatiladi. Bajarish tugmasini bosganingizda kod va kirish qiymatlari CodeStep serveri orqali shu xizmatga yuboriladi. Hisobingiz paroli va seans cookie’si yuborilmaydi, ammo kod ichiga kiritgan ma’lumotlaringiz uzatiladi. Xizmat va undagi saqlash muddati haqida administrator bilan bog‘lanishingiz mumkin.</p>
                <?php elseif ($privacyExecutionProvider === 'local'): ?>
                <p>Kod bajarish uchun CodeStep serveridagi mahalliy WebAssembly muhiti tanlangan. Bajarishga yuborgan kod va kirish qiymatlari shu muhitda qayta ishlanadi; ular bajarish uchun tashqi kompilyatsiya xizmatiga yuborilmaydi.</p>
                <?php else: ?>
                <p>Kod bajarish xizmati hozir to‘g‘ri sozlanmagan. Bajarishga yuborilgan so‘rov CodeStep serveriga keladi, ammo boshqa xizmatga avtomatik yo‘naltirilmaydi.</p>
                <?php endif; ?>
                <p>Sintaksisni tekshirish va kodni formatlash CodeStep serveridagi vositalarda bajariladi. Bajarish xizmati javob bermasa, kod boshqa xizmatga avtomatik yuborilmaydi.</p>
                <p>Sahifalarda shriftlar uchun <strong>Google Fonts</strong> va belgilar uchun <strong>cdnjs</strong> resurslari yuklanadi. Brauzeringiz bu xizmatlarga bevosita so‘rov yuboradi; ular IP manzili, brauzer haqidagi ma’lumot va brauzer yuboradigan boshqa so‘rov ma’lumotlarini olishi mumkin. Ularning ma’lumotlarni qayta ishlashi o‘z siyosatlariga bog‘liq.</p>
                <p><a href="?page=contact">Bog‘lanish</a> sahifasidagi Telegram, Instagram yoki email havolasini ochsangiz, tegishli xizmatga o‘tasiz. U yerda yuborgan xabaringiz va aloqa ma’lumotlaringiz o‘sha xizmat orqali yetkaziladi.</p>
            </section>

            <section class="terms-card" id="privacy-retention" aria-labelledby="privacy-retention-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">05</span><h2 id="privacy-retention-title">Saqlash muddati va himoya</h2></div>
                <p>Hisob, profil, saqlangan qoralamalar va tekshiruv tarixi server bazasida keyinroq foydalanish uchun saqlanadi. Hozirgi versiyada ularni ma’lum muddatdan keyin avtomatik o‘chirish belgilanmagan. Hisobdan chiqish yoki brauzer tarixini tozalash bazadagi ma’lumotlarni o‘chirmaydi.</p>
                <p>Mahalliy qoralamalar va sozlamalar brauzer xotirasi tozalanguncha yoki brauzer ularni olib tashlaguncha qolishi mumkin. Maxfiy ko‘rish rejimida ular odatda shu seans bilan cheklanadi. Server jurnallari va zaxira nusxalari bo‘yicha saqlash muddatini administrator bilan aniqlashtirishingiz mumkin.</p>
                <p>Parol bazaga ochiq matn shaklida yozilmaydi, uning xesh qiymati saqlanadi. Shaxsiy ma’lumotlar bilan ishlaydigan so‘rovlarda hisob egasi va seans tasdig‘i tekshiriladi. CodeStep kod, kirish va qaytariladigan natija hajmini hamda javob kutish vaqtini cheklaydi. Tashqi bajarish xizmatining ichki resurs cheklovlarini o‘sha xizmat belgilaydi.</p>
                <p class="terms-note">Kod, kirish qiymatlari yoki profilga parol, maxfiy kalit va boshqa odamlarning shaxsiy ma’lumotlarini kiritmang. Profilga yuklangan rasm fayli saqlanadi; undagi joylashuv kabi qo‘shimcha ma’lumotlarni kerak bo‘lsa yuklashdan oldin olib tashlang.</p>
            </section>

            <section class="terms-card" id="privacy-control" aria-labelledby="privacy-control-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">06</span><h2 id="privacy-control-title">Ma’lumotlaringizni qanday boshqarasiz?</h2></div>
                <ul class="terms-list">
                    <li><a href="?page=profile">Profil</a>da ism va izohni yangilash, rasmni almashtirish yoki olib tashlash, parolni o‘zgartirish mumkin.</li>
                    <li><a href="?page=solutions">Yechimlarim</a>da bazadagi qoralamalar va tekshiruv tarixini ko‘ring. Muharrirdagi <strong>Yuklab olish</strong> tugmasi orqali kodingiz nusxasini qurilmangizga saqlang.</li>
                    <li><a href="?page=settings">Sozlamalar</a>da sayt ichidagi bildirishnomalar va ko‘rinish tanlovlarini o‘zgartiring.</li>
                    <li>Cookie va mahalliy qoralamalarni brauzerning sayt ma’lumotlarini boshqarish bo‘limidan o‘chiring. Cookie’larni bloklash hisobga kirish va kod yuborish kabi funksiyalarga ta’sir qilishi mumkin.</li>
                    <li><a href="?page=profile#deleteAccountSection">Profil</a> sahifasidagi <strong>Hisobni butunlay o‘chirish</strong> bo‘limi orqali joriy parolingizni tasdiqlab, hisobingizni va unga bog‘langan barcha ma’lumotlarni (profil, qoralamalar, yechimlar tarixi va musobaqa natijalarini) istalgan vaqtda bir zumda o‘chirishingiz mumkin.</li>
                </ul>
            </section>

            <section class="terms-card" id="privacy-contact" aria-labelledby="privacy-contact-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">07</span><h2 id="privacy-contact-title">Savollar va murojaatlar</h2></div>
                <p>CodeStep asoschisi va aloqa uchun mas’ul: <strong>Tohir Mamurov</strong>. Maxfiylik bo‘yicha savol yoki ma’lumotlaringizga oid so‘rovni <a href="mailto:mamurovtohir571@gmail.com">mamurovtohir571@gmail.com</a> manziliga yoki <a href="?page=contact">Bog‘lanish</a> sahifasidagi aloqa orqali yuboring.</p>
                <p>So‘rovda loginingiz va qaysi ma’lumot bo‘yicha murojaat qilayotganingizni yozing. <strong>Parolingizni yubormang.</strong> Hisobga tegishli ma’lumotni berish yoki o‘zgartirishdan oldin hisob egasi ekaningizni tasdiqlash talab qilinishi mumkin.</p>
                <p>Saytning ma’lumotlar bilan ishlash tartibi o‘zgarsa, ushbu sahifa va yuqoridagi yangilanish sanasi ham yangilanadi.</p>
                <div class="terms-contact">
                    <div><h3>Saytdan foydalanishni o‘rganing</h3><p>Kod yozish, tekshirish va saqlash tartibi alohida qo‘llanmada tushuntirilgan.</p></div>
                    <a class="account-button" href="?page=terms">Foydalanish shartlari <span aria-hidden="true">→</span></a>
                </div>
            </section>
            <a class="terms-back-top" href="#privacy-title">Sahifa boshiga qaytish <span aria-hidden="true">↑</span></a>
        </div>
    </div>
</section>
