<?php
require_once dirname(__DIR__) . '/includes/code-execution.php';
$termsExecutionProvider = codeExecutionProvider();
?>
<section class="solutions-page terms-page" aria-labelledby="terms-title">
    <header class="terms-hero">
        <div class="terms-hero-copy">
            <p class="terms-eyebrow">CODESTEP QO‘LLANMASI</p>
            <h1 id="terms-title">Foydalanish shartlari</h1>
            <p class="terms-lead">CodeStep — Python va C++ dasturlash tillarini mavzular, masalalar va namunalar orqali o‘rganish sayti. Bu qo‘llanma kod yozish, tekshirish va yechimlarni saqlashni tushuntiradi.</p>
            <p class="terms-caption">Oxirgi yangilanish: <time datetime="2026-10-12">2026-yil 12-oktabr</time>.</p>
            <div class="terms-actions">
                <a class="account-button" href="?page=home">Bo‘limni tanlash <span aria-hidden="true">→</span></a>
                <a class="terms-secondary" href="?page=code-editor">Muharrirni ochish</a>
            </div>
        </div>
        <div class="terms-hero-note">
            <span class="terms-code-mark" aria-hidden="true">&lt;/&gt;</span>
            <p>O‘qing. Yozing.<br><strong>Sinab ko‘ring.</strong></p>
            <span class="terms-language-tag">Python &amp; C++</span>
        </div>
    </header>

    <div class="terms-layout">
        <nav class="terms-toc" aria-label="Qo‘llanma bo‘limlari">
            <h2>Shu sahifada</h2>
            <ol>
                <li><a href="#terms-start"><span>01</span> Mavzu va masalalar</a></li>
                <li><a href="#terms-editor"><span>02</span> Kodni ishga tushirish</a></li>
                <li><a href="#terms-results"><span>03</span> Natijani tushunish</a></li>
                <li><a href="#terms-examples"><span>04</span> Namunalar bilan ishlash</a></li>
                <li><a href="#terms-saving"><span>05</span> Kodlar va tarix</a></li>
                <li><a href="#terms-profile"><span>06</span> Shaxsiy profil</a></li>
                <li><a href="#terms-theme"><span>07</span> Qulay ko‘rinish</a></li>
                <li><a href="#terms-use"><span>08</span> Foydalanish odobi</a></li>
                <li><a href="#terms-help"><span>09</span> Savol va muammolar</a></li>
            </ol>
        </nav>

        <div class="terms-content">
            <section class="terms-card" id="terms-start" aria-labelledby="terms-start-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">01</span><h2 id="terms-start-title">Mavzu va masalani tanlang</h2></div>
                <ol class="terms-steps">
                    <li><a href="?page=home">Asosiy sahifa</a>dagi uchta kitob bo‘limidan birini tanlang. Ochilgan sahifada shu bo‘limning boblari ko‘rinadi. Bobni, so‘ng uning ichidagi mavzuni oching.</li>
                    <li>Mavzudagi masalani tanlab, sharti va <strong>Qabul qilish talablari</strong>ni o‘qing. Qanday kirish ma’lumoti berilishi va qanday natija chiqarilishi kerakligini aniqlang.</li>
                    <li>Muharrirda Python yoki C++ tilini tanlab, o‘z yechimingizni yozing.</li>
                </ol>
                <p class="terms-note">Mavzular bosqichma-bosqich to‘ldiriladi. Masalasi yoki namunasi hali qo‘shilmagan mavzuda shu haqdagi xabar ko‘rsatiladi.</p>
            </section>

            <section class="terms-card" id="terms-editor" aria-labelledby="terms-editor-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">02</span><h2 id="terms-editor-title">Kod yozing va ishga tushiring</h2></div>
                <p><strong>Python</strong> va <strong>C++</strong> tugmalari orqali tilni almashtiring. Har bir til uchun yozgan kodingiz alohida saqlanadi.</p>
                <div class="terms-feature-grid">
                    <div class="terms-feature">
                        <h3>Erkin mashq</h3>
                        <p><a href="?page=code-editor">Kod muharriri</a>da dasturingizni yozing. <code>input()</code> yoki <code>cin</code> ishlatilsa, qiymatlarni <strong>Kirish ma’lumotlari (stdin)</strong> maydoniga oldindan kiriting. <strong>Kodni bajarish</strong>ni bosing.</p>
                    </div>
                    <div class="terms-feature">
                        <h3>Masala yechimi</h3>
                        <p>Masala ichida <strong>Bajarish va tekshirish</strong>ni bosing. Test kirishini sayt belgilaydi. Dastur natijasi va tekshiruv xulosasi <strong>Bajarish</strong> bo‘limida chiqadi.</p>
                    </div>
                </div>
                <?php if ($termsExecutionProvider === 'wandbox'): ?>
                <p>Hozir kodni <strong>Wandbox</strong> xizmati bajaradi: C++ uchun <strong>GCC 13.2.0, C++20</strong>, Python uchun <strong>CPython 3.12.7</strong> ishlatiladi. Bajarish tugmasi yoki <kbd>Ctrl</kbd> + <kbd>Enter</kbd> bosilganda kod va kirish qiymatlari shu tashqi xizmatga yuboriladi. <a href="?page=privacy#privacy-access">Ma’lumotlar uzatilishi haqida</a>.</p>
                <?php elseif ($termsExecutionProvider === 'local'): ?>
                <p>Hozir kod CodeStep serveridagi mahalliy WebAssembly muhitida bajariladi: C++17 va Python 3.12. <a href="?page=privacy#privacy-access">Ma’lumotlar qayta ishlanishi haqida</a>.</p>
                <?php elseif ($termsExecutionProvider === 'private'): ?>
                <p>Hozir kodni administrator ulagan alohida bajarish xizmati qayta ishlaydi. Bajarish tugmasi bosilganda kod va kirish qiymatlari shu xizmatga yuboriladi. <a href="?page=privacy#privacy-access">Ma’lumotlar uzatilishi haqida</a>.</p>
                <?php endif; ?>
                <p><strong>Kod tahlili</strong> yoki <strong>Tekshirish</strong> sintaksis va uslubdagi muammolarni topishga yordam beradi. <strong>Yozishda tekshirish</strong> tezkor tavsiyalar beradi. Bu tekshiruvlar CodeStep serverida bajariladi. Dasturingiz chiqishini ko‘rish uchun bajarish tugmasidan foydalaning.</p>
                <p><strong>Formatlash</strong> kod ko‘rinishini tartiblaydi. <strong>Bekor qilish</strong> oxirgi tahrirni qaytaradi. Python bloklarida chekinishga e’tibor bering: muharrir <kbd>Tab</kbd> bosilganda 4 ta bo‘sh joy qo‘yadi.</p>
                <p>C++ da kutubxonalardan keyin <code>using namespace std;</code> yozsangiz, <code>cin</code>, <code>cout</code>, <code>string</code> va <code>vector</code> oldiga <code>std::</code> yozish shart emas. Muharrirdagi shu nomli tugma qatorni qo‘shadi. Kerakli kutubxonalarni ham ulang: masalan, <code>&lt;iostream&gt;</code>, <code>&lt;string&gt;</code> va <code>&lt;vector&gt;</code>.</p>
                <div class="terms-feature-grid">
                    <div class="terms-feature">
                        <h3>Kod yozishda yordam</h3>
                        <p><strong>Kod takliflari</strong> kursor yonida mos nomlarni ko‘rsatadi. <strong>Yozishda takliflar</strong> yoqilganida ular avtomatik ochiladi. <strong>Tayyor kod</strong>dan buyruq yoki blokni tanlab, <strong>Kursorga qo‘shish</strong>ni bosing va bo‘sh joylarini to‘ldiring. C++ shablonlari kerakli kutubxonani ham qo‘shadi.</p>
                    </div>
                    <div class="terms-feature">
                        <h3>Tahrirlash vositalari</h3>
                        <p><strong>Qidirish</strong> va <strong>Almashtirish</strong> bilan matnni toping yoki yangilang. <strong>Qatorga o‘tish</strong> kerakli qatorni ochadi. <strong>Izoh</strong> tanlangan qatorlarni izohga oladi yoki izohdan chiqaradi. Bekor qilingan tahrirni <strong>Qaytarish</strong> orqali tiklang.</p>
                    </div>
                </div>
                <p><strong>Ko‘rinish va fayllar</strong> ichida shrift o‘lchamini o‘zgartirish, uzun qatorlarni o‘rash, bloklarni yig‘ish yoki yoyish mumkin. <strong>Fayl ochish</strong> UTF-8 formatidagi <code>.cpp</code>, <code>.py</code> yoki <code>.txt</code> kodni yuklaydi (64 KB gacha); mavjud kodni almashtirishdan oldin tasdiq so‘raladi. Kod va natijani alohida nusxalash, natija oynasini tozalash ham mumkin.</p>
                <p><strong>Kengaytirish</strong> muharrirga ko‘proq joy ajratadi. <kbd>Esc</kbd> ochiq taklif yoki qidiruv oynasini yopadi; ular yopilgach, kengaytirilgan ko‘rinishdan chiqaradi.</p>
                <div class="terms-shortcuts" aria-label="Muharrirning tezkor tugmalari">
                    <span><kbd>Ctrl</kbd> + <kbd>Enter</kbd> Bajarish</span>
                    <span><kbd>Ctrl</kbd> + <kbd>S</kbd> Saqlash</span>
                    <span><kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>F</kbd> Formatlash</span>
                    <span><kbd>Ctrl</kbd> + <kbd>Space</kbd> Kod takliflari</span>
                    <span><kbd>Ctrl</kbd> + <kbd>F</kbd> Qidirish</span>
                    <span><kbd>Ctrl</kbd> + <kbd>H</kbd> Almashtirish</span>
                    <span><kbd>Ctrl</kbd> + <kbd>G</kbd> Qatorga o‘tish</span>
                    <span><kbd>Ctrl</kbd> + <kbd>/</kbd> Izohga olish</span>
                </div>
                <p class="terms-caption">Mac qurilmalarida Ctrl o‘rniga Command ishlaydi.</p>
                <p class="terms-note">Muharrir konsol dasturlari uchun mo‘ljallangan. Grafik oyna ochish yoki qo‘shimcha paket o‘rnatish imkoniyati yo‘q. Bajarish vaqti va resurslar cheklangan; cheksiz takrorlanishlardan saqlaning.</p>
            </section>

            <section class="terms-card" id="terms-results" aria-labelledby="terms-results-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">03</span><h2 id="terms-results-title">Tekshiruv natijasini tushuning</h2></div>
                <dl class="terms-verdicts">
                    <div><dt><span class="terms-dot terms-dot-success" aria-hidden="true"></span>Qabul qilindi</dt><dd>Yechim shu masala uchun belgilangan avtomatik tekshiruvlardan o‘tdi.</dd></div>
                    <div><dt><span class="terms-dot terms-dot-error" aria-hidden="true"></span>Qabul qilinmadi</dt><dd>Natija talablarga mos kelmadi. Shart, hisoblash va chiqish matnini tekshiring; ortiqcha yozuvlar ham natijaga ta’sir qilishi mumkin.</dd></div>
                    <div><dt><span class="terms-dot terms-dot-warning" aria-hidden="true"></span>Tuzilishi mos · mazmun tasdiqlanmagan</dt><dd>Chiqishning belgilangan tuzilishi mos. Javobning mazmunan to‘g‘riligi tasdiqlanmagan; uni masala sharti bilan o‘zingiz solishtiring.</dd></div>
                    <div><dt><span class="terms-dot terms-dot-error" aria-hidden="true"></span>Bajarish xatosi</dt><dd>Xato xabarini o‘qing. Sintaksis, kirish ma’lumotlari yoki bajarilishdagi muammoni tuzatib, yana urinib ko‘ring.</dd></div>
                </dl>
                <p class="terms-note">Avtomatik testlar belgilangan holatlarni tekshiradi. Algoritmni va boshqa kirish qiymatlarini ham tahlil qiling. Kod o‘zgargach, avvalgi natija yangi nusxaga tegishli bo‘lmaydi — qayta bajaring.</p>
            </section>

            <section class="terms-card" id="terms-examples" aria-labelledby="terms-examples-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">04</span><h2 id="terms-examples-title">Namunalardan o‘rganing</h2></div>
                <p><strong>Namuna masalalar</strong> mavjud bo‘lgan mavzularda bosqichma-bosqich yechim, kod va natijani ko‘rishingiz mumkin. Namunani tushunib, keyin o‘z masalangizga qo‘llang.</p>
                <p>Namuna sahifasini masala muharriridan ochgan bo‘lsangiz, yuqoridagi <strong>masalani yechishga qaytish</strong> havolasi orqali davom eting.</p>
                <p>Namunadagi kiritish takliflari va tushuntirish matnlarini masalangizdagi chiqish talablariga moslang. Masala faqat sonni chiqarishni so‘rasa, ortiqcha matn qo‘shmang.</p>
                <p>Muharrirdagi <strong>Kod namunasi</strong> tugmasi boshlang‘ich dastur namunasini joylaydi. Bu tanlangan masalaning tayyor yechimi emas. Avvalgi kodingizni <strong>Bekor qilish</strong> orqali qaytarishingiz mumkin.</p>
            </section>

            <section class="terms-card" id="terms-saving" aria-labelledby="terms-saving-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">05</span><h2 id="terms-saving-title">Kodingizni saqlang va davom ettiring</h2></div>
                <div class="terms-feature-grid">
                    <div class="terms-feature">
                        <h3>Shu brauzerda</h3>
                        <p>Mehmon sifatida ham kod yozishingiz mumkin. Muharrir qoralamani shu brauzerda saqlashga urinadi. Brauzer ma’lumotlarini tozalash, maxfiy rejim yoki boshqa qurilmaga o‘tish bu nusxani yo‘qotishi mumkin.</p>
                    </div>
                    <div class="terms-feature">
                        <h3>Hisobingizda</h3>
                        <p>Hisobga kirib, masala muharririda <strong>Bazaga saqlash</strong>ni bosing va saqlash tasdig‘ini kuting. Keyingi o‘zgarishlarni ham qayta saqlang. Erkin muharrirdagi <strong>Saqlash</strong> esa brauzerdagi qoralama uchun ishlaydi.</p>
                    </div>
                </div>
                <p><a href="?page=solutions">Yechimlarim</a> sahifasida saqlangan qoralamalarni <strong>Kodni davom ettirish</strong> orqali ochasiz. Hisobga kirgan holda masalani tekshirish urinishlari tarixga yoziladi; saqlashda xato bo‘lsa, muharrir xabariga e’tibor bering.</p>
                <p><strong>Tekshirish tarixi</strong> yuborilgan kod va natijani ko‘rsatadi. <strong>Shu kodni muharrirda ochish</strong> bilan eski urinishni qayta o‘rganing. Qoralama va tekshiruv tarixi alohida: kodni saqlashning o‘zi uni tekshirmaydi.</p>
                <p class="terms-note"><strong>Yuklab olish</strong> orqali kodingizning <code>.py</code> yoki <code>.cpp</code> nusxasini qurilmangizga saqlashingiz mumkin.</p>
            </section>

            <section class="terms-card" id="terms-profile" aria-labelledby="terms-profile-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">06</span><h2 id="terms-profile-title">Profilingizni boshqaring</h2></div>
                <p>Yangi foydalanuvchi <a href="?page=register">ro‘yxatdan o‘tish</a> sahifasida login tanlaydi va parolini ikki marta kiritadi. Login 3–32 ta lotin harfi, raqam yoki pastki chiziqdan, parol esa kamida 8 ta belgidan iborat bo‘lsin. <strong>Hisob yaratish</strong> bosilgach, shaxsiy profilingiz avtomatik yaratiladi.</p>
                <p>Hisobingiz mavjud bo‘lsa, <a href="?page=login">Kirish</a> orqali login va parolingizni kiriting. Keyin <a href="?page=profile">Profil</a> menyusida ma’lumotlaringizni boshqaring. Login hisobga kirish uchun ishlatiladi; uni profilda o‘zgartirib bo‘lmaydi.</p>
                <ul class="terms-list">
                    <li><strong>Ism va izoh:</strong> ism-familiyani 80, o‘zingiz haqingizdagi matnni 280 ta belgigacha kiriting. <strong>O‘zgarishlarni saqlash</strong>ni bosing.</li>
                    <li><strong>Profil rasmi:</strong> JPG, PNG yoki WebP tanlang. Hajm 2 MB, eni va bo‘yi 4096 pikseldan oshmasin. Rasmni almashtirish yoki olib tashlash mumkin.</li>
                    <li><strong>Parol:</strong> joriy parolingizni va yangi parolni ikki marta kiriting, so‘ng <strong>Parolni yangilash</strong>ni bosing.</li>
                    <li><strong>Natijalar:</strong> yechilgan masalalar, qoralamalar va urinishlar sonini kuzating. Faqat <strong>Qabul qilindi</strong> natijasi yechilgan deb hisoblanadi. Bir masalaning Python va C++ dagi qabul qilingan yechimlari yechilgan masalalar sonida bir marta hisoblanadi.</li>
                    <li><strong>Hisobdan chiqish:</strong> profildagi <strong>Hisobdan chiqish</strong> tugmasidan foydalaning, ayniqsa umumiy qurilmada ishlaganingizdan keyin.</li>
                </ul>
            </section>

            <section class="terms-card" id="terms-theme" aria-labelledby="terms-theme-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">07</span><h2 id="terms-theme-title">O‘zingizga qulay ko‘rinishni tanlang</h2></div>
                <p>Kompyuterda bo‘limlar yon menyuda joylashadi. Menyu tugmasi bilan uni yopish yoki qayta ochish mumkin; tanlovingiz shu brauzerda eslab qolinadi. Telefon va kichik ekranlarda bo‘limlar pastdagi menyuda turadi.</p>
                <p>Yuqori paneldagi quyosh/oy belgisi orqali och va to‘q rejimni almashtiring. Muharrir ham tanlangan ko‘rinishga moslashadi.</p>
                <p><a href="?page=settings">Sozlamalar</a>dagi <strong>Matn o‘lchami</strong> bilan sahifalar va menyular matnini kattalashtiring yoki kichraytiring. <strong>Kod matni o‘lchami</strong> muharrir uchun alohida ishlaydi. Tanlovlar shu brauzerda eslab qolinadi.</p>
                <p>Yuqoridagi qo‘ng‘iroq belgisi sayt ichidagi bildirishnomalarni ochadi. Sozlamalarda yangi mavzular haqida xabar va kunlik eslatmani yoqish mumkin. Eslatma sayt ochiq turganda qurilmangiz vaqti bo‘yicha kuniga bir marta ko‘rsatiladi. <strong>Sinov bildirishnomasi</strong> bilan uning ishlashini tekshiring.</p>
                <p>Hisob, kod va brauzer sozlamalari qanday saqlanishi <a href="?page=privacy">Maxfiylik siyosati</a>da tushuntirilgan.</p>
                <p>Kichik ekranda bajarish natijasi kod maydonidan pastroqda joylashishi mumkin. Kodni bajargach, <strong>Bajarish</strong> bo‘limigacha pastga tushing.</p>
            </section>

            <section class="terms-card" id="terms-use" aria-labelledby="terms-use-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">08</span><h2 id="terms-use-title">O‘rganishga va boshqalarga hurmat bilan yondashing</h2></div>
                <ul class="terms-list">
                    <li>CodeStep’dan dasturlashni o‘rganish va o‘z yechimlaringizni sinash uchun foydalaning. Namunadagi yechimni ko‘chirishdan oldin uning ishlashini tushunishga harakat qiling.</li>
                    <li>Profil matni va rasmi uchun o‘zingizga tegishli yoki foydalanishga ruxsat berilgan materialni tanlang; haqoratli mazmun joylamang.</li>
                    <li>Kod va profilga parol, maxfiy kalit yoki boshqa odamlarning shaxsiy ma’lumotlarini kiritmang.</li>
                    <li>Boshqa hisobga kirishga, xizmatni buzishga yoki uni ataylab ortiqcha so‘rovlar bilan band qilishga urinmang.</li>
                </ul>
            </section>

            <section class="terms-card" id="terms-help" aria-labelledby="terms-help-title">
                <div class="terms-section-heading"><span class="terms-number" aria-hidden="true">09</span><h2 id="terms-help-title">Muammo bo‘lsa, nimani tekshirish kerak?</h2></div>
                <div class="terms-faq">
                    <details>
                        <summary>Kod bajarildi, lekin natija bo‘sh</summary>
                        <p>Dastur natijani chiqarayotganini tekshiring: Python’da <code>print()</code>, C++ da <code>std::cout</code> ishlatiladi. Kiritish so‘ralgan bo‘lsa, kerakli qiymatlarni oldindan bering. O‘zgaruvchiga qiymat yozishning o‘zi ekranga natija chiqarmaydi.</p>
                    </details>
                    <details>
                        <summary>Bajarish uzoq ketyapti yoki server xatosi chiqdi</summary>
                        <p>Xato xabarini o‘qing, internet aloqasini va dasturdagi takrorlanishlar tugashini tekshiring. Bajarish xizmati vaqtincha band yoki mavjud bo‘lmasligi mumkin. Javob kelmasa, vaqt chegarasidan so‘ng xabar chiqadi va qayta urinish mumkin bo‘ladi. Kod boshqa xizmatga avtomatik yuborilmaydi. Qayta urinishdan oldin joriy urinish javobini kuting. Sahifani yangilashdan avval kodingizni yuklab oling.</p>
                    </details>
                    <details>
                        <summary>Qoralama saqlanmadi yoki boshqa oynada o‘zgargan</summary>
                        <p>Hisobga kirganingizni va masala muharririda ishlayotganingizni tekshiring. Joriy kod nusxasini yuklab oling. <strong>Bazadagi kodni ochish</strong> serverdagi nusxani yuklaydi; uni o‘zingizdagi nusxa bilan solishtirib, kerakli o‘zgarishlarni saqlang.</p>
                    </details>
                    <details>
                        <summary>Seans yangilangani haqida xabar chiqdi</summary>
                        <p>Saqlanmagan kodingiz yoki profil matningizdan nusxa oling, sahifani yangilang va kerak bo‘lsa hisobga qayta kiring. So‘ng amalni takrorlang.</p>
                    </details>
                </div>
                <div class="terms-contact">
                    <div><h3>Yordam kerakmi?</h3><p><a href="?page=contact">Bog‘lanish</a> sahifasidagi aloqa usullaridan foydalaning. Mavzu va masala raqami, dasturlash tili, kodingiz hamda xato xabarini yuboring. Parolingizni yubormang.</p></div>
                    <a class="account-button" href="?page=contact">Bog‘lanish <span aria-hidden="true">→</span></a>
                </div>
            </section>
            <a class="terms-back-top" href="#terms-title">Sahifa boshiga qaytish <span aria-hidden="true">↑</span></a>
        </div>
    </div>
</section>
