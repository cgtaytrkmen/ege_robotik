<?php
// modules/topics/controller.php - Konu yönetim kontrolcüsü
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// İşlem kontrolü
$action = isset($_GET['action']) ? $_GET['action'] : '';
$topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Silme işlemi
if ($action === 'delete' && $topic_id > 0) {
    try {
        // Konuyu sil
        $delete_query = "DELETE FROM topics WHERE id = ?";
        $result = safeQuery($delete_query, [$topic_id]);

        if ($result) {
            setAlert('Konu başarıyla silindi!', 'success');
        } else {
            setAlert('Konu silinirken bir hata oluştu!', 'danger');
        }
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }

    // Yönlendirme
    redirect('modules/topics/index.php');
}

// İşlem tanımlı değilse ana sayfaya yönlendir
redirect('modules/topics/index.php');
