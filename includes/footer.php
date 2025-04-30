<?php
// includes/footer.php - Ortak footer bölümü
?>
</div>
</main>

<!-- Footer -->
<footer class="bg-light py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h5><i class="bi bi-robot"></i> Ege Robotik Kodlama Kursu</h5>
                <p class="text-muted">Geleceğin teknoloji liderleri burada yetişiyor.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="text-muted mb-0">
                    &copy; <?php echo date('Y'); ?> Tüm hakları saklıdır.
                </p>
                <p class="text-muted">
                    <small>Sürüm 1.0.0</small>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<?php if (isset($datatable) && $datatable): ?>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.datatable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                },
                order: [
                    [0, 'desc']
                ],
                pageLength: 25
            });
        });
    </script>
<?php endif; ?>

<!-- FullCalendar JS -->
<?php if (isset($calendar) && $calendar): ?>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/tr.js'></script>
<?php endif; ?>

<!-- Chart.js -->
<?php if (isset($chart) && $chart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php endif; ?>

<!-- Genel JavaScript -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>

<!-- Sayfa özel JS -->
<?php if (isset($page_js)): ?>
    <?php foreach ($page_js as $js): ?>
        <script src="<?php echo BASE_URL; ?>/assets/js/<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>

</html>