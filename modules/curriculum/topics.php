<?php
// modules/curriculum/topics.php - Müfredat haftalık konuları düzenleme sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Müfredat ID kontrolü
$curriculum_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$curriculum_id) {
    setAlert('Geçersiz müfredat ID!', 'danger');
    redirect('modules/curriculum/index.php');
}

// Müfredat bilgilerini getir
$curriculum_query = "SELECT c.*, p.name as period_name 
                   FROM curriculum c
                   JOIN periods p ON c.period_id = p.id
                   WHERE c.id = ?";
$curriculum = safeQuery($curriculum_query, [$curriculum_id])->fetch();

if (!$curriculum) {
    setAlert('Müfredat bulunamadı!', 'danger');
    redirect('modules/curriculum/index.php');
}

// Haftalık konuları getir
$topics_query = "SELECT * FROM curriculum_weekly_topics 
               WHERE curriculum_id = ? 
               ORDER BY week_number";
$topics = safeQuery($topics_query, [$curriculum_id])->fetchAll();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->beginTransaction();

        // Konu sayısı
        $total_weeks = intval($_POST['total_weeks']);

        // Önce müfredatın toplam hafta sayısını güncelle
        $update_curriculum = "UPDATE curriculum SET total_weeks = ? WHERE id = ?";
        safeQuery($update_curriculum, [$total_weeks, $curriculum_id]);

        // Her hafta için konu bilgilerini güncelle veya ekle
        for ($week = 1; $week <= $total_weeks; $week++) {
            $topic_data = [
                'curriculum_id' => $curriculum_id,
                'week_number' => $week,
                'topic_title' => clean($_POST['topic_title_' . $week] ?? ''),
                'description' => clean($_POST['description_' . $week] ?? ''),
                'learning_objectives' => clean($_POST['learning_objectives_' . $week] ?? ''),
                'materials_needed' => clean($_POST['materials_needed_' . $week] ?? ''),
                'homework' => clean($_POST['homework_' . $week] ?? '')
            ];

            // Konu başlığı boş olamaz
            if (empty($topic_data['topic_title'])) {
                throw new Exception('Hafta ' . $week . ' için konu başlığı boş olamaz!');
            }

            // Bu hafta için kayıt var mı kontrol et
            $check_query = "SELECT id FROM curriculum_weekly_topics 
                          WHERE curriculum_id = ? AND week_number = ?";
            $existing_topic = safeQuery($check_query, [$curriculum_id, $week])->fetch();

            if ($existing_topic) {
                // Güncelle
                $sql = "UPDATE curriculum_weekly_topics SET 
                        topic_title = :topic_title,
                        description = :description,
                        learning_objectives = :learning_objectives,
                        materials_needed = :materials_needed,
                        homework = :homework
                        WHERE curriculum_id = :curriculum_id AND week_number = :week_number";

                safeQuery($sql, $topic_data);
            } else {
                // Yeni ekle
                $sql = "INSERT INTO curriculum_weekly_topics 
                        (curriculum_id, week_number, topic_title, description, learning_objectives, materials_needed, homework) 
                        VALUES 
                        (:curriculum_id, :week_number, :topic_title, :description, :learning_objectives, :materials_needed, :homework)";

                safeQuery($sql, $topic_data);
            }
        }

        // Artık kullanılmayan haftaları temizle
        if ($total_weeks < count($topics)) {
            $delete_query = "DELETE FROM curriculum_weekly_topics 
                           WHERE curriculum_id = ? AND week_number > ?";
            safeQuery($delete_query, [$curriculum_id, $total_weeks]);
        }

        db()->commit();

        setAlert('Haftalık konular başarıyla güncellendi!', 'success');
        redirect('modules/curriculum/topics.php?id=' . $curriculum_id);
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Haftalık Konular - ' . $curriculum['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Haftalık Konular: <?php echo htmlspecialchars($curriculum['name']); ?>
        <small class="text-muted">(<?php echo htmlspecialchars($curriculum['age_group']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Müfredat Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>Müfredat Adı:</strong> <?php echo htmlspecialchars($curriculum['name']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Yaş Grubu:</strong> <?php echo htmlspecialchars($curriculum['age_group']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Dönem:</strong> <?php echo htmlspecialchars($curriculum['period_name']); ?></p>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Haftalık Konular</h5>
            <div>
                <div class="input-group">
                    <label class="input-group-text" for="total_weeks">Toplam Hafta</label>
                    <select class="form-select" id="total_weeks" name="total_weeks">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($curriculum['total_weeks'] == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> hafta
                            </option>
                        <?php endfor; ?>
                    </select>
                    <button type="button" id="update_weeks_btn" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat"></i> Güncelle
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="accordion" id="weeklyTopicsAccordion">
                <?php for ($week = 1; $week <= $curriculum['total_weeks']; $week++): ?>
                    <?php
                    // Bu hafta için konu bilgilerini bul
                    $topic = array_filter($topics, function ($t) use ($week) {
                        return $t['week_number'] == $week;
                    });
                    $topic = !empty($topic) ? reset($topic) : null;
                    ?>
                    <div class="accordion-item" id="week-<?php echo $week; ?>-container">
                        <h2 class="accordion-header" id="heading-week-<?php echo $week; ?>">
                            <button class="accordion-button <?php echo ($week != 1) ? 'collapsed' : ''; ?>" type="button"
                                data-bs-toggle="collapse" data-bs-target="#collapse-week-<?php echo $week; ?>"
                                aria-expanded="<?php echo ($week == 1) ? 'true' : 'false'; ?>" aria-controls="collapse-week-<?php echo $week; ?>">
                                <strong>Hafta <?php echo $week; ?>:</strong>
                                <span class="ms-2" id="week-<?php echo $week; ?>-title">
                                    <?php echo htmlspecialchars($topic['topic_title'] ?? 'Konu başlığı ekleyin'); ?>
                                </span>
                            </button>
                        </h2>
                        <div id="collapse-week-<?php echo $week; ?>" class="accordion-collapse collapse <?php echo ($week == 1) ? 'show' : ''; ?>"
                            aria-labelledby="heading-week-<?php echo $week; ?>" data-bs-parent="#weeklyTopicsAccordion">
                            <div class="accordion-body">
                                <div class="mb-3">
                                    <label for="topic_title_<?php echo $week; ?>" class="form-label">Konu Başlığı *</label>
                                    <input type="text" class="form-control topic-title" id="topic_title_<?php echo $week; ?>"
                                        name="topic_title_<?php echo $week; ?>"
                                        value="<?php echo htmlspecialchars($topic['topic_title'] ?? ''); ?>" required
                                        data-week="<?php echo $week; ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="description_<?php echo $week; ?>" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="description_<?php echo $week; ?>"
                                        name="description_<?php echo $week; ?>" rows="2"><?php echo htmlspecialchars($topic['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="learning_objectives_<?php echo $week; ?>" class="form-label">Öğrenme Hedefleri</label>
                                    <textarea class="form-control" id="learning_objectives_<?php echo $week; ?>"
                                        name="learning_objectives_<?php echo $week; ?>" rows="3"><?php echo htmlspecialchars($topic['learning_objectives'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Her kazanımı virgülle ayırarak yazabilirsiniz.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="materials_needed_<?php echo $week; ?>" class="form-label">Gerekli Malzemeler</label>
                                    <textarea class="form-control" id="materials_needed_<?php echo $week; ?>"
                                        name="materials_needed_<?php echo $week; ?>" rows="2"><?php echo htmlspecialchars($topic['materials_needed'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="homework_<?php echo $week; ?>" class="form-label">Ev Ödevi</label>
                                    <textarea class="form-control" id="homework_<?php echo $week; ?>"
                                        name="homework_<?php echo $week; ?>" rows="2"><?php echo htmlspecialchars($topic['homework'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="text-center mt-4">
                <p class="text-muted">Hafta sayısını değiştirmek için yukarıdaki "Toplam Hafta" seçeneğini kullanın.</p>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Tüm Haftalık Konuları Kaydet
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Konu başlıklarını canlı güncelleme
        document.querySelectorAll('.topic-title').forEach(input => {
            input.addEventListener('input', function() {
                const week = this.getAttribute('data-week');
                const titleSpan = document.getElementById('week-' + week + '-title');

                if (titleSpan) {
                    titleSpan.textContent = this.value || 'Konu başlığı ekleyin';
                }
            });
        });

        // Hafta sayısı güncelleme
        document.getElementById('update_weeks_btn').addEventListener('click', function() {
            const currentWeeks = <?php echo $curriculum['total_weeks']; ?>;
            const newWeeks = parseInt(document.getElementById('total_weeks').value);

            if (newWeeks === currentWeeks) return; // Değişiklik yoksa işlem yapma

            const container = document.getElementById('weeklyTopicsAccordion');

            // Hafta sayısı artırıldıysa yeni haftalar ekle
            if (newWeeks > currentWeeks) {
                for (let week = currentWeeks + 1; week <= newWeeks; week++) {
                    // Yeni hafta HTML'ini oluştur
                    const weekItem = document.createElement('div');
                    weekItem.className = 'accordion-item';
                    weekItem.id = 'week-' + week + '-container';

                    weekItem.innerHTML = `
                        <h2 class="accordion-header" id="heading-week-${week}">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#collapse-week-${week}" 
                                    aria-expanded="false" aria-controls="collapse-week-${week}">
                                <strong>Hafta ${week}:</strong>
                                <span class="ms-2" id="week-${week}-title">Konu başlığı ekleyin</span>
                            </button>
                        </h2>
                        <div id="collapse-week-${week}" class="accordion-collapse collapse" 
                             aria-labelledby="heading-week-${week}" data-bs-parent="#weeklyTopicsAccordion">
                            <div class="accordion-body">
                                <div class="mb-3">
                                    <label for="topic_title_${week}" class="form-label">Konu Başlığı *</label>
                                    <input type="text" class="form-control topic-title" id="topic_title_${week}" 
                                           name="topic_title_${week}" value="" required
                                           data-week="${week}">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description_${week}" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="description_${week}" 
                                              name="description_${week}" rows="2"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="learning_objectives_${week}" class="form-label">Öğrenme Hedefleri</label>
                                    <textarea class="form-control" id="learning_objectives_${week}" 
                                              name="learning_objectives_${week}" rows="3"></textarea>
                                    <small class="form-text text-muted">Her kazanımı virgülle ayırarak yazabilirsiniz.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="materials_needed_${week}" class="form-label">Gerekli Malzemeler</label>
                                    <textarea class="form-control" id="materials_needed_${week}" 
                                              name="materials_needed_${week}" rows="2"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="homework_${week}" class="form-label">Ev Ödevi</label>
                                    <textarea class="form-control" id="homework_${week}" 
                                              name="homework_${week}" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    `;

                    container.appendChild(weekItem);

                    // Yeni eklenen başlık giriş olayını dinle
                    const newTitleInput = document.getElementById('topic_title_' + week);
                    newTitleInput.addEventListener('input', function() {
                        const titleSpan = document.getElementById('week-' + week + '-title');
                        if (titleSpan) {
                            titleSpan.textContent = this.value || 'Konu başlığı ekleyin';
                        }
                    });
                }
            }
            // Hafta sayısı azaltıldıysa fazla haftaları kaldır
            else if (newWeeks < currentWeeks) {
                for (let week = currentWeeks; week > newWeeks; week--) {
                    const weekItem = document.getElementById('week-' + week + '-container');
                    if (weekItem) {
                        weekItem.remove();
                    }
                }
            }

            alert(`Hafta sayısı ${newWeeks} olarak güncellendi. Değişiklikleri kaydetmek için "Tüm Haftalık Konuları Kaydet" butonunu kullanın.`);
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>