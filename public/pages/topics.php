<?php
$topics = getTopics();
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-book"></i> Darsliklar</h1>
    <p class="page-subtitle">Quyidagi mavzulardan birini tanlang</p>
</div>

<div class="topics-grid">
    <?php if (empty($topics)): ?>
    <!-- Default mavzular -->
    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=1'">
        <div class="topic-icon"><i class="fas fa-code"></i></div>
        <h3 class="topic-title">C++ Kirish</h3>
        <p class="topic-description">C++ dasturlash tiliga kirish, asosiy tushunchalar va birinchi dastur</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 15 daqiqa</span><span><i class="fas fa-tasks"></i> 5 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=2'">
        <div class="topic-icon"><i class="fab fa-python"></i></div>
        <h3 class="topic-title">Python Kirish</h3>
        <p class="topic-description">Python dasturlash tiliga kirish, o'zgaruvchilar va ma'lumot turlari</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 12 daqiqa</span><span><i class="fas fa-tasks"></i> 4 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=3'">
        <div class="topic-icon"><i class="fas fa-random"></i></div>
        <h3 class="topic-title">Shart operatorlari</h3>
        <p class="topic-description">if-else, switch case shart operatorlari va ularning qo'llanilishi</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 20 daqiqa</span><span><i class="fas fa-tasks"></i> 8 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=4'">
        <div class="topic-icon"><i class="fas fa-redo"></i></div>
        <h3 class="topic-title">Sikllar</h3>
        <p class="topic-description">for, while, do-while sikllari va ularni amalda qo'llash</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 25 daqiqa</span><span><i class="fas fa-tasks"></i> 10 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=5'">
        <div class="topic-icon"><i class="fas fa-box"></i></div>
        <h3 class="topic-title">Funksiyalar</h3>
        <p class="topic-description">Funksiyalar, parametrlar va qaytarish qiymatlari</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 30 daqiqa</span><span><i class="fas fa-tasks"></i> 12 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=6'">
        <div class="topic-icon"><i class="fas fa-layer-group"></i></div>
        <h3 class="topic-title">Massivlar</h3>
        <p class="topic-description">Bir va ko'p o'lchamli massivlar, ular bilan ishlash</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 25 daqiqa</span><span><i class="fas fa-tasks"></i> 8 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=7'">
        <div class="topic-icon"><i class="fab fa-html5"></i></div>
        <h3 class="topic-title">HTML va CSS</h3>
        <p class="topic-description">Veb-sahifaning tuzilishi, uslublari va responsiv dizayn</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 18 daqiqa</span><span><i class="fas fa-tasks"></i> 6 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=8'">
        <div class="topic-icon"><i class="fab fa-js"></i></div>
        <h3 class="topic-title">JavaScript Asoslari</h3>
        <p class="topic-description">JavaScript o'zgaruvchilari, funksiyalari va hodisalar</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 22 daqiqa</span><span><i class="fas fa-tasks"></i> 9 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=9'">
        <div class="topic-icon"><i class="fas fa-cubes"></i></div>
        <h3 class="topic-title">OOP</h3>
        <p class="topic-description">Klasslar, obyektlar, meros va inkapsulyatsiya</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 28 daqiqa</span><span><i class="fas fa-tasks"></i> 7 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=10'">
        <div class="topic-icon"><i class="fas fa-database"></i></div>
        <h3 class="topic-title">SQL va ma'lumotlar bazalari</h3>
        <p class="topic-description">SELECT, INSERT, UPDATE va tabellar bilan ishlash</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 24 daqiqa</span><span><i class="fas fa-tasks"></i> 8 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=11'">
        <div class="topic-icon"><i class="fab fa-github"></i></div>
        <h3 class="topic-title">Git va GitHub</h3>
        <p class="topic-description">Versiyalarni boshqarish va loyihalarni hamkorlikda rivojlantirish</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 16 daqiqa</span><span><i class="fas fa-tasks"></i> 5 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=12'">
        <div class="topic-icon"><i class="fas fa-sitemap"></i></div>
        <h3 class="topic-title">Algoritmlar va ma'lumotlar tuzilmalari</h3>
        <p class="topic-description">Qidiruv, saralash va asosiy ma'lumotlar tuzilmalari</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 35 daqiqa</span><span><i class="fas fa-tasks"></i> 11 ta mashq</span></div>
    </div>

    <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=13'">
        <div class="topic-icon"><i class="fas fa-calculator"></i></div>
        <h3 class="topic-title">1-bob. Sonli ma'lumotlar</h3>
        <p class="topic-description">Mashina xotirasida sonlarni tasvirlash, ikkilik va o'nlik sanoq sistemalaridagi masalalar</p>
        <div class="topic-meta"><span><i class="far fa-clock"></i> 30 daqiqa</span><span><i class="fas fa-tasks"></i> 20 ta masala</span></div>
    </div>
    <?php else: ?>
        <?php foreach ($topics as $topic): ?>
        <div class="topic-card" onclick="window.location.href='?page=topic-detail&id=<?php echo $topic['id']; ?>'">
            <div class="topic-icon"><i class="<?php echo $topic['icon']; ?>"></i></div>
            <h3 class="topic-title"><?php echo htmlspecialchars($topic['title']); ?></h3>
            <p class="topic-description"><?php echo htmlspecialchars($topic['description']); ?></p>
            <div class="topic-meta"><span><i class="far fa-clock"></i> <?php echo $topic['duration']; ?></span><span><i class="fas fa-tasks"></i> <?php echo $topic['exercises']; ?> ta mashq</span></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
.page-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 20px;
}

.page-title {
    font-size: 32px;
    color: #10B981;
    margin-bottom: 10px;
}

.page-title i {
    margin-right: 10px;
}

.page-subtitle {
    opacity: 0.8;
    font-size: 16px;
}
</style>