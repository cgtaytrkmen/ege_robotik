<?php
// check_makeup_lessons_table.php - Makeup_lessons tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Telafi Dersleri Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Telafi Dersleri Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Varlığı</h5>
        <?php
        $table_exists = db()->query("SHOW TABLES LIKE 'makeup_lessons'")->fetch();
        ?>
        <div class="alert alert-<?php echo $table_exists ? 'success' : 'danger'; ?>">
            Makeup_lessons tablosu <?php echo $table_exists ? 'bulundu' : 'bulunamadı'; ?>
        </div>

        <?php if ($table_exists): ?>
            <h5 class="mt-4">Tablo Yapısı</h5>
            <?php
            $columns = db()->query("DESCRIBE makeup_lessons")->fetchAll();
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
                'student_id',
                'original_lesson_id',
                'original_date',
                'makeup_date',
                'topic_id',
                'status',
                'notes',
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
            <?php endif; ?>
        <?php else: ?>
            <h5 class="mt-4">Tablo Oluşturma</h5>
            <pre class="bg-light p-3">
CREATE TABLE makeup_lessons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    original_lesson_id INT,
    original_date DATE NOT NULL,
    makeup_date DATE,
    topic_id INT,
    status ENUM('pending', 'completed', 'missed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (original_lesson_id) REFERENCES lessons(id),
    FOREIGN KEY (topic_id) REFERENCES topics(id)
);
            </pre>

            <form method="POST" action="">
                <button type="submit" name="create_table" class="btn btn-primary">
                    <i class="bi bi-database-add"></i> Tabloyu Oluştur
                </button>
            </form>
        <?php endif; ?>

        <?php
        // Tabloyu oluştur
        if (isset($_POST['create_table']) && !$table_exists) {
            try {
                $sql = "CREATE TABLE makeup_lessons (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    student_id INT NOT NULL,
                    original_lesson_id INT,
                    original_date DATE NOT NULL,
                    makeup_date DATE,
                    topic_id INT,
                    status ENUM('pending', 'completed', 'missed', 'cancelled') DEFAULT 'pending',
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                    FOREIGN KEY (original_lesson_id) REFERENCES lessons(id),
                    FOREIGN KEY (topic_id) REFERENCES topics(id)
                )";

                db()->exec($sql);

                setAlert('Makeup_lessons tablosu başarıyla oluşturuldu!', 'success');
                redirect('check_makeup_lessons_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <h5 class="mt-4">İlişkili Tablo: makeup_lesson_topics</h5>
        <?php
        $related_table_exists = db()->query("SHOW TABLES LIKE 'makeup_lesson_topics'")->fetch();
        ?>
        <div class="alert alert-<?php echo $related_table_exists ? 'success' : 'danger'; ?>">
            Makeup_lesson_topics tablosu <?php echo $related_table_exists ? 'bulundu' : 'bulunamadı'; ?>
        </div>

        <?php if (!$related_table_exists): ?>
            <h5 class="mt-4">İlişkili Tablo Oluşturma</h5>
            <pre class="bg-light p-3">
CREATE TABLE makeup_lesson_topics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    makeup_lesson_id INT NOT NULL,
    topic_id INT NOT NULL,
    status ENUM('pending', 'completed', 'missed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (makeup_lesson_id) REFERENCES makeup_lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id),
    UNIQUE KEY unique_makeup_topic (makeup_lesson_id, topic_id)
);
            </pre>

            <form method="POST" action="">
                <button type="submit" name="create_related_table" class="btn btn-primary">
                    <i class="bi bi-database-add"></i> İlişkili Tabloyu Oluştur
                </button>
            </form>

            <?php
            // İlişkili tabloyu oluştur
            if (isset($_POST['create_related_table']) && !$related_table_exists && $table_exists) {
                try {
                    $sql = "CREATE TABLE makeup_lesson_topics (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        makeup_lesson_id INT NOT NULL,
                        topic_id INT NOT NULL,
                        status ENUM('pending', 'completed', 'missed') DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (makeup_lesson_id) REFERENCES makeup_lessons(id) ON DELETE CASCADE,
                        FOREIGN KEY (topic_id) REFERENCES topics(id),
                        UNIQUE KEY unique_makeup_topic (makeup_lesson_id, topic_id)
                    )";

                    db()->exec($sql);

                    setAlert('Makeup_lesson_topics tablosu başarıyla oluşturuldu!', 'success');
                    redirect('check_makeup_lessons_table.php');
                } catch (Exception $e) {
                    setAlert('Hata: ' . $e->getMessage(), 'danger');
                }
            }
            ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>