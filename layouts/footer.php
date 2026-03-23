    <?php if (Auth::isLoggedIn()): ?>
        </div> <!-- Close main-content -->
    <?php endif; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/dashboard.js"></script>
    
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>
    <!-- Tooth Chart Modal -->
<div class="modal fade" id="toothModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tooth <span id="modal-tooth-number"></span> Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tooth-number-input">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="tooth-status">
                        <option value="healthy">Healthy</option>
                        <option value="cavity">Cavity</option>
                        <option value="filled">Filled</option>
                        <option value="crown">Crown</option>
                        <option value="root-canal">Root Canal</option>
                        <option value="missing">Missing</option>
                        <option value="implant">Implant</option>
                        <option value="bridge">Bridge</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Diagnosis</label>
                    <textarea class="form-control" id="tooth-diagnosis" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Treatment</label>
                    <textarea class="form-control" id="tooth-treatment" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" id="tooth-notes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="toothChart.saveTooth()">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo url('assets/js/tooth-chart.js'); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
     <?php if (isset($patientId)): ?>
    toothChart.init(<?php echo $patientId; ?>);
<?php endif; ?>
    });
</script>
</body>
</html>