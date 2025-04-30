<?php
// check_curriculum_table.php - Curriculum tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Müfredat Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Müfredat Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Varlığı</h5>
        <?php
        $curriculum_exists = db()->query("SHOW TABLES LIKE 'curriculum'")->fetch();
        $weekly_topics_exists = db()->query("SHOW TABLES LIKE 'curriculum_weekly_topics'")->fetch();
        $classroom_curriculum_exists = db()->query("SHOW TABLES LIKE 'classroom_curriculum'")->fetch();
        $student_progress_exists = db()->query("SHOW TABLES LIKE 'student_curriculum_progress'")->fetch();
        $topics_column_exists = false;

        if (db()->query("SHOW TABLES LIKE 'topics'")->fetch()) {
            $column_check = db()->query("SHOW COLUMNS FROM topics LIKE 'curriculum_weekly_topic_id'")->fetch();
            $topics_column_exists = !empty($column_check);
        }
        ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Tablo</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>curriculum</td>
                        <td>
                            <?php if ($curriculum_exists): ?>
                                <span class="badge bg-success">Var</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Yok</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>curriculum_weekly_topics</td>
                        <td>
                            <?php if ($weekly_topics_exists): ?>
                                <span class="badge bg-success">Var</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Yok</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>classroom_curriculum</td>
                        <td>
                            <?php if ($classroom_curriculum_exists): ?>
                                <span class="badge bg-success">Var</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Yok</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>student_curriculum_progress</td>
                        <td>
                            <?php if ($student_progress_exists): ?>
                                <span class="badge bg-success">Var</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Yok</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>topics.curriculum_weekly_topic_id</td>
                        <td>
                            <?php if ($topics_column_exists): ?>
                                <span class="badge bg-success">Var</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Yok</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if (
            !$curriculum_exists || !$weekly_topics_exists || !$classroom_curriculum_exists ||
            !$student_progress_exists || !$topics_column_exists
        ): ?>
            <h5 class="mt-4">Müfredat Tabloları Oluşturma</h5>

            <div class="alert alert-info">
                <p><i class="bi bi-info-circle"></i> Müfredat sistemi için gerekli tablolar eksik.</p>
                <p>Aşağıdaki buton ile tüm gerekli tabloları oluşturabilirsiniz.</p>
            </div>

            <form method="POST" action="">
                <button type="submit" name="create_tables" class="btn btn-primary">
                    <i class="bi bi-database-add"></i> Müfredat Tablolarını Oluştur
                </button>
            </form>

            <?php
            if (isset($_POST['create_tables'])) {
                try {
                    // curriculum tablosu
                    if (!$curriculum_exists) {
                        $sql = "CREATE TABLE `curriculum` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `name` varchar(255) NOT NULL,
                          `description` text DEFAULT NULL,
                          `age_group` varchar(50) NOT NULL,
                          `period_id` int(11) NOT NULL,
                          `total_weeks` int(11) DEFAULT 4,
                          `status` enum('active','inactive') DEFAULT 'active',
                          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                          PRIMARY KEY (`id`),
                          KEY `period_id` (`period_id`),
                          CONSTRAINT `curriculum_ibfk_1` FOREIGN KEY (`period_id`) REFERENCES `periods` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci";

                        db()->exec($sql);
                        echo "<div class='alert alert-success mt-2'>curriculum tablosu oluşturuldu</div>";
                    }

                    // curriculum_weekly_topics tablosu
                    if (!$weekly_topics_exists) {
                        $sql = "CREATE TABLE `curriculum_weekly_topics` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `curriculum_id` int(11) NOT NULL,
                          `week_number` int(11) NOT NULL,
                          `topic_title` varchar(255) NOT NULL,
                          `description` text DEFAULT NULL,
                          `learning_objectives` text DEFAULT NULL,
                          `materials_needed` text DEFAULT NULL,
                          `homework` text DEFAULT NULL,
                          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `unique_curriculum_week` (`curriculum_id`,`week_number`),
                          CONSTRAINT `curriculum_weekly_topics_ibfk_1` FOREIGN KEY (`curriculum_id`) REFERENCES `curriculum` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci";

                        db()->exec($sql);
                        echo "<div class='alert alert-success mt-2'>curriculum_weekly_topics tablosu oluşturuldu</div>";
                    }

                    // classroom_curriculum tablosu
                    if (!$classroom_curriculum_exists) {
                        $sql = "CREATE TABLE `classroom_curriculum` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `classroom_id` int(11) NOT NULL,
                          `curriculum_id` int(11) NOT NULL,
                          `start_date` date NOT NULL,
                          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `unique_classroom_curriculum` (`classroom_id`,`curriculum_id`),
                          KEY `curriculum_id` (`curriculum_id`),
                          CONSTRAINT `classroom_curriculum_ibfk_1` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`id`) ON DELETE CASCADE,
                          CONSTRAINT `classroom_curriculum_ibfk_2` FOREIGN KEY (`curriculum_id`) REFERENCES `curriculum` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci";

                        db()->exec($sql);
                        echo "<div class='alert alert-success mt-2'>classroom_curriculum tablosu oluşturuldu</div>";
                    }

                    // student_curriculum_progress tablosu
                    if (!$student_progress_exists) {
                        $sql = "CREATE TABLE `student_curriculum_progress` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `student_id` int(11) NOT NULL,
                          `curriculum_id` int(11) NOT NULL,
                          `current_week` int(11) DEFAULT 1,
                          `completed_topics` text DEFAULT NULL,
                          `notes` text DEFAULT NULL,
                          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `unique_student_curriculum` (`student_id`,`curriculum_id`),
                          KEY `curriculum_id` (`curriculum_id`),
                          CONSTRAINT `student_curriculum_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
                          CONSTRAINT `student_curriculum_progress_ibfk_2` FOREIGN KEY (`curriculum_id`) REFERENCES `curriculum` (`id`) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci";

                        db()->exec($sql);
                        echo "<div class='alert alert-success mt-2'>student_curriculum_progress tablosu oluşturuldu</div>";
                    }

                    // topics tablosuna sütun ekleme
                    if (!$topics_column_exists) {
                        $sql = "ALTER TABLE `topics` 
                                ADD COLUMN `curriculum_weekly_topic_id` int(11) DEFAULT NULL,
                                ADD KEY `curriculum_weekly_topic_id` (`curriculum_weekly_topic_id`),
                                ADD CONSTRAINT `topics_ibfk_2` FOREIGN KEY (`curriculum_weekly_topic_id`) 
                                REFERENCES `curriculum_weekly_topics` (`id`)";

                        db()->exec($sql);
                        echo "<div class='alert alert-success mt-2'>topics tablosuna curriculum_weekly_topic_id sütunu eklendi</div>";
                    }

                    echo "<div class='alert alert-success'>Tüm müfredat tabloları başarıyla oluşturuldu!</div>";
                    echo "<a href='check_curriculum_table.php' class='btn btn-primary'>Sayfayı Yenile</a>";
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Hata: " . $e->getMessage() . "</div>";
                }
            }
            ?>
        <?php else: ?>
            <div class="alert alert-success mt-4">
                <i class="bi bi-check-circle"></i> Tüm müfredat tabloları mevcut ve kullanıma hazır.
            </div>

            <h5 class="mt-4">Örnek Müfredat Verilerini Yükle</h5>
            <form method="POST" action="">
                <button type="submit" name="load_sample_data" class="btn btn-primary"
                    onclick="return confirm('Örnek müfredat verilerini yüklemek istediğinize emin misiniz?');">
                    <i class="bi bi-upload"></i> Örnek Verileri Yükle
                </button>
            </form>

            <?php
            if (isset($_POST['load_sample_data'])) {
                try {
                    // Örnek müfredat verilerini yükle
                    $sample_data = file_get_contents('sample_curriculum_data.sql');
                    $queries = explode(';', $sample_data);

                    db()->beginTransaction();

                    foreach ($queries as $query) {
                        if (trim($query) != '') {
                            db()->exec($query);
                        }
                    }

                    db()->commit();
                    echo "<div class='alert alert-success mt-2'>Örnek müfredat verileri başarıyla yüklendi!</div>";
                } catch (Exception $e) {
                    db()->rollBack();
                    echo "<div class='alert alert-danger'>Hata: " . $e->getMessage() . "</div>";
                }
            }
            ?>

            <h5 class="mt-4">Mevcut Müfredatlar</h5>
            <?php
            $curriculum_data = db()->query("SELECT c.*, p.name as period_name 
                                           FROM curriculum c 
                                           JOIN periods p ON c.period_id = p.id 
                                           ORDER BY c.id")->fetchAll();

            if (empty($curriculum_data)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Henüz müfredat kaydı bulunmuyor.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Müfredat Adı</th>
                                <th>Yaş Grubu</th>
                                <th>Dönem</th>
                                <th>Hafta Sayısı</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($curriculum_data as $curriculum): ?>
                                <tr>
                                    <td><?php echo $curriculum['id']; ?></td>
                                    <td><?php echo htmlspecialchars($curriculum['name']); ?></td>
                                    <td><?php echo htmlspecialchars($curriculum['age_group']); ?></td>
                                    <td><?php echo htmlspecialchars($curriculum['period_name']); ?></td>
                                    <td><?php echo $curriculum['total_weeks']; ?></td>
                                    <td>
                                        <?php if ($curriculum['status'] == 'active'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>