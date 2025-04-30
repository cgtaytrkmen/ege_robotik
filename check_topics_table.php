<?php
// check_topics_table.php - Topics tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Konu Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Konular Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Yapısı</h5>
        <?php
        $check_table = db()->query("SHOW TABLES LIKE 'topics'")->fetch();

        if (!$check_table) {
            echo "<div class='alert alert-warning'>Topics tablosu bulunamadı!</div>";

            // Tablo oluşturma SQL'i göster
        ?>
            <h5 class="mt-4">Tablo Oluşturma SQL</h5>
            <pre class="bg-light p-3">
CREATE TABLE `topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lesson_id` int(11) DEFAULT NULL,
  `topic_title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` enum('planned','completed','cancelled') DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `topics_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;
            </pre>

            <form method="POST" action="">
                <button type="submit" name="create_table" class="btn btn-primary">
                    <i class="bi bi-database-add"></i> Tabloyu Oluştur
                </button>
            </form>
        <?php
        } else {
            $columns = db()->query("DESCRIBE topics")->fetchAll();
        ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Alan Adı</th>
                        <th>Tip</th>
                        <th>Null</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>Extra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($columns as $column): ?>
                        <tr>
                            <td><?php echo $column['Field']; ?></td>
                            <td><?php echo $column['Type']; ?></td>
                            <td><?php echo $column['Null']; ?></td>
                            <td><?php echo $column['Key']; ?></td>
                            <td><?php echo $column['Default']; ?></td>
                            <td><?php echo $column['Extra']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h5 class="mt-4">Eksik Kolonlar</h5>
            <?php
            $required_columns = [
                'id',
                'lesson_id',
                'topic_title',
                'description',
                'date',
                'status',
                'created_at',
                'updated_at'
            ];

            $existing_columns = array_column($columns, 'Field');
            $missing_columns = array_diff($required_columns, $existing_columns);

            if (empty($missing_columns)): ?>
                <div class="alert alert-success">Tüm gerekli kolonlar mevcut.</div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <p>Eksik kolonlar bulundu:</p>
                    <ul>
                        <?php foreach ($missing_columns as $column): ?>
                            <li><?php echo $column; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <h5 class="mt-4">Düzeltme SQL Komutları</h5>
                <pre class="bg-light p-3">
<?php foreach ($missing_columns as $column): ?>
<?php if ($column == 'status'): ?>
ALTER TABLE topics ADD COLUMN status enum('planned','completed','cancelled') DEFAULT 'planned';
<?php elseif ($column == 'created_at'): ?>
ALTER TABLE topics ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
<?php elseif ($column == 'updated_at'): ?>
ALTER TABLE topics ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
<?php endif; ?>
<?php endforeach; ?>
                </pre>

                <form method="POST" action="">
                    <button type="submit" name="fix_table" class="btn btn-primary">
                        <i class="bi bi-tools"></i> Eksik Kolonları Ekle
                    </button>
                </form>
            <?php endif; ?>
        <?php } ?>

        <?php
        // Tablo oluşturma işlemi
        if (isset($_POST['create_table'])) {
            try {
                $sql = "CREATE TABLE `topics` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `lesson_id` int(11) DEFAULT NULL,
                    `topic_title` varchar(255) DEFAULT NULL,
                    `description` text DEFAULT NULL,
                    `date` date DEFAULT NULL,
                    `status` enum('planned','completed','cancelled') DEFAULT 'planned',
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `lesson_id` (`lesson_id`),
                    CONSTRAINT `topics_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;";

                db()->exec($sql);
                setAlert('Topics tablosu başarıyla oluşturuldu!', 'success');
                redirect('check_topics_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }

        // Eksik kolonları ekle
        if (isset($_POST['fix_table']) && !empty($missing_columns)) {
            try {
                foreach ($missing_columns as $column) {
                    if ($column == 'status') {
                        db()->exec("ALTER TABLE topics ADD COLUMN status enum('planned','completed','cancelled') DEFAULT 'planned'");
                    } elseif ($column == 'created_at') {
                        db()->exec("ALTER TABLE topics ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                    } elseif ($column == 'updated_at') {
                        db()->exec("ALTER TABLE topics ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                    }
                }

                setAlert('Eksik kolonlar başarıyla eklendi!', 'success');
                redirect('check_topics_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <h5 class="mt-4">Mevcut Konu Kayıtları</h5>
        <?php
        if ($check_table) {
            $topics = db()->query("SELECT t.*, l.day, l.start_time, c.name as classroom_name 
                                  FROM topics t
                                  LEFT JOIN lessons l ON t.lesson_id = l.id
                                  LEFT JOIN classrooms c ON l.classroom_id = c.id
                                  ORDER BY t.date DESC, l.start_time
                                  LIMIT 10")->fetchAll();

            if (empty($topics)): ?>
                <div class="alert alert-warning">Henüz konu kaydı bulunmuyor.</div>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tarih</th>
                            <th>Sınıf/Ders</th>
                            <th>Konu</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topics as $topic): ?>
                            <tr>
                                <td><?php echo $topic['id']; ?></td>
                                <td><?php echo formatDate($topic['date']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($topic['classroom_name'] ?? 'Sınıf Bulunamadı'); ?>
                                    <br>
                                    <small><?php echo $topic['day'] ?? ''; ?> <?php echo substr($topic['start_time'] ?? '', 0, 5); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($topic['topic_title']); ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'planned' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $status_labels = [
                                        'planned' => 'Planlandı',
                                        'completed' => 'Tamamlandı',
                                        'cancelled' => 'İptal Edildi'
                                    ];
                                    $badge_class = $status_badges[$topic['status']] ?? 'secondary';
                                    $label = $status_labels[$topic['status']] ?? $topic['status'];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php } ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>