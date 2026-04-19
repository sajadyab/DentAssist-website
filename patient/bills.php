<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/patient_cloud_repository.php';

Auth::requireLogin();
if ($_SESSION['role'] != 'patient') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);

if (!$patientId) {
    die("Patient record not found.");
}

// Get clinic phone for WhatsApp
$clinicPhone = '';
$settingResult = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_phone'");
if ($settingResult && !empty($settingResult['setting_value'])) {
    $clinicPhone = $settingResult['setting_value'];
}
if (!function_exists('canViewSubscription')) {
    function canViewSubscription()
    {
        global $db;
        static $cached = null;
        if ($cached === null) {
            $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_subscription_view'");
            $cached = ($result && $result['setting_value'] == '1');
        }
        return $cached;
    }
}$showSub = canViewSubscription();
// Clean phone number: keep digits and optional leading '+'
$cleanPhone = preg_replace('/[^0-9+]/', '', $clinicPhone);
// Ensure it starts with '+' if it contains a plus, otherwise assume digits only
$whatsappUrl = '';
if (!empty($cleanPhone)) {
    $whatsappUrl = 'https://wa.me/' . ltrim($cleanPhone, '+'); // wa.me works with digits only
}

// Get invoices (cloud-first, local fallback)
$invoices = patient_portal_list_invoices_cloud_first((int) $patientId);

// Get subscription payments (cloud-first, local fallback)
$subscriptions = patient_portal_list_subscription_payments_cloud_first((int) $patientId);

// Normalize invoice fields for older schema or cloud payloads that may omit derived fields.
foreach ($invoices as &$inv) {
    $inv['total_amount'] = isset($inv['total_amount']) ? (float) $inv['total_amount'] : ((float) ($inv['subtotal'] ?? 0));
    $inv['paid_amount'] = isset($inv['paid_amount']) ? (float) $inv['paid_amount'] : 0.0;
    $inv['balance_due'] = isset($inv['balance_due'])
        ? (float) $inv['balance_due']
        : max(0.0, $inv['total_amount'] - $inv['paid_amount']);
}
unset($inv);

// Calculate totals
$totalDue = 0;
$totalPaid = 0;
foreach ($invoices as $inv) {
    $totalDue += $inv['balance_due'];
    $totalPaid += $inv['paid_amount'];
}

$pageTitle = 'My Bills';
include '../layouts/header.php';
?>

<div class="container-fluid bills-page patient-portal">
    <!-- Billing Header (queue-style gradient) -->
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-md-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-file-invoice-dollar me-2 opacity-90"></i>My Bills &amp; Payments
                </h2>
                <p class="mb-0 opacity-90">View and manage all your financial transactions</p>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="bills-balance-wrap">
                    <div class="bills-balance-box">
                        <small>Total balance due</small>
                        <p class="bills-balance-amount"><?php echo htmlspecialchars(formatCurrency($totalDue)); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards (dashboard stats style) -->
    <div class="row patient-stats-row mb-4 g-3">
        <div class="col-6 col-md-3 mb-3">
            <div class="bills-stats-card bills-stats-card--invoices">
                <div class="bills-stats-number"><?php echo count($invoices); ?></div>
                <div class="bills-stats-label">Total Invoices</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="bills-stats-card bills-stats-card--paid">
                <div class="bills-stats-number"><?php echo htmlspecialchars(formatCurrency($totalPaid)); ?></div>
                <div class="bills-stats-label">Total Paid</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="bills-stats-card bills-stats-card--due">
                <div class="bills-stats-number"><?php echo htmlspecialchars(formatCurrency($totalDue)); ?></div>
                <div class="bills-stats-label">Balance Due</div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
            <div class="bills-stats-card bills-stats-card--subs">
                <div class="bills-stats-number"><?php echo count($subscriptions); ?></div>
                <div class="bills-stats-label">Subscriptions</div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <!-- Treatment Invoices -->
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-stethoscope me-2" aria-hidden="true"></i>Treatment Invoices</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($invoices)): ?>
                        <div class="bills-empty-state text-center py-4 px-3">
                            <p class="text-muted small mb-3">No invoices yet.</p>
                            <a href="queue.php" class="btn btn-sm bills-cta bills-cta--book">Book an Appointment</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <?php
                            $invBadge = 'bills-badge bills-badge--blue';
                            switch ($inv['payment_status']) {
                                case 'paid':
                                    $invBadge = 'bills-badge bills-badge--green';
                                    break;
                                case 'partial':
                                    $invBadge = 'bills-badge bills-badge--yellow';
                                    break;
                                case 'pending':
                                    $invBadge = 'bills-badge bills-badge--blue';
                                    break;
                                case 'overdue':
                                    $invBadge = 'bills-badge bills-badge--red';
                                    break;
                            }
                            ?>
                            <div class="bills-dash-row">
                                <span class="bills-side-id"><?php echo htmlspecialchars($inv['invoice_number']); ?></span>
                                <div class="bills-dash-col-main">
                                    <span class="bills-dash-strong"><?php echo htmlspecialchars(formatDate($inv['invoice_date'])); ?></span>
                                    <span class="bills-dash-muted">Due <?php echo htmlspecialchars(formatDate($inv['due_date'])); ?> · Total <?php echo htmlspecialchars(formatCurrency($inv['total_amount'])); ?> · Paid <?php echo htmlspecialchars(formatCurrency($inv['paid_amount'])); ?></span>
                                </div>
                                <div class="bills-dash-actions">
                                    <span class="bills-dash-balance"><?php echo htmlspecialchars(formatCurrency($inv['balance_due'])); ?> due</span>
                                    <span class="<?php echo $invBadge; ?>"><?php echo htmlspecialchars(ucfirst($inv['payment_status'])); ?></span>
                                    <a href="view_invoice.php?id=<?php echo (int) $inv['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye me-1"></i>View</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Subscription Payments -->
            <div class="card bills-dash-section-card mb-0">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-crown me-2" aria-hidden="true"></i>Subscription Payments</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($subscriptions)): ?>
                        <div class="bills-empty-state text-center py-4 px-3">
                                <?php if ($showSub): ?>
    <p class="text-muted small mb-3">No subscription payments yet.</p>
        <a href="subscription.php" class="btn btn-sm bills-cta bills-cta--subscribe">Subscribe Now</a>
    <?php endif; ?>
</div>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $sub): ?>
                            <?php
                            $subIcon = 'fa-credit-card';
                            if ($sub['payment_method'] === 'cash') {
                                $subIcon = 'fa-money-bill';
                            } elseif ($sub['payment_method'] === 'online') {
                                $subIcon = 'fa-globe';
                            } elseif ($sub['payment_method'] === 'clinic') {
                                $subIcon = 'fa-building';
                            }
                            $subBadge = 'bills-badge bills-badge--blue';
                            switch ($sub['status']) {
                                case 'completed':
                                    $subBadge = 'bills-badge bills-badge--green';
                                    break;
                                case 'pending':
                                    $subBadge = 'bills-badge bills-badge--blue';
                                    break;
                                case 'failed':
                                    $subBadge = 'bills-badge bills-badge--red';
                                    break;
                            }
                            $subRef = (string) ($sub['payment_reference'] ?? '');
                            $subRefDisp = $subRef !== '' ? $subRef : '—';
                            ?>
                            <div class="bills-dash-row">
                                <span class="bills-side-id"><?php echo htmlspecialchars(formatCurrency($sub['amount'])); ?></span>
                                <div class="bills-dash-col-main">
                                    <span class="bills-dash-strong"><?php echo htmlspecialchars(ucfirst($sub['subscription_type'])); ?> plan</span>
                                    <span class="bills-dash-muted"><i class="fas <?php echo htmlspecialchars($subIcon); ?> me-1" aria-hidden="true"></i><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $sub['payment_method']))); ?> · <?php echo htmlspecialchars(formatDate($sub['payment_date'])); ?></span>
                                </div>
                                <div class="bills-dash-actions">
                                    <span class="<?php echo $subBadge; ?>"><?php echo htmlspecialchars(ucfirst($sub['status'])); ?></span>
                                    <span class="bills-dash-muted text-end" style="max-width:10rem;">Ref: <?php echo htmlspecialchars($subRefDisp); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bills-dash-section-card bills-sidebar-card mb-3">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2" aria-hidden="true"></i>Payment Information</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="fw-semibold small text-uppercase mb-2" style="color:#64748b;letter-spacing:0.04em;">Accepted methods</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="bills-method-pill"><i class="fas fa-money-bill" aria-hidden="true"></i> Cash</span>
                        <span class="bills-method-pill"><i class="fab fa-cc-visa" aria-hidden="true"></i> Visa</span>
                        <span class="bills-method-pill"><i class="fab fa-cc-mastercard" aria-hidden="true"></i> Mastercard</span>
                        <span class="bills-method-pill"><i class="fab fa-cc-amex" aria-hidden="true"></i> Amex</span>
                        <span class="bills-method-pill"><i class="fas fa-university" aria-hidden="true"></i> Bank transfer</span>
                    </div>
                    <hr class="my-3 opacity-50">
                    <p class="small text-muted mb-0">
                        <i class="fas fa-clock me-1" aria-hidden="true"></i>Payments usually post within 2–3 business days. Contact billing with any questions.
                    </p>
                </div>
            </div>

            <div class="card bills-dash-section-card bills-sidebar-card mb-0">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-headset me-2" aria-hidden="true"></i>Need Help?</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-secondary mb-3 small">Questions about bills or payments?</p>
                    
                    <!-- WhatsApp button (primary help action) -->
                    <?php if (!empty($whatsappUrl)): ?>
                        <a href="<?php echo htmlspecialchars($whatsappUrl); ?>" target="_blank" class="btn btn-bills-outline btn-sm w-100 mb-2" style="background-color: #25D366; border-color: #25D366; color: white;">
                            <i class="fab fa-whatsapp me-1"></i> Chat with us on WhatsApp
                        </a>
                    <?php else: ?>
                        <div class="alert alert-warning small p-2 mb-2">Clinic phone number not configured. Please contact reception.</div>
                    <?php endif; ?>
                    
                    <!-- Secondary email contact -->
                    <div class="text-center mt-2">
                        <a href="mailto:billing@dentalclinic.com" class="small text-muted">
                            <i class="fas fa-envelope me-1"></i> Or send an email
                        </a>
                    </div>
                    
                    <hr class="my-3 opacity-25">
                    <div class="bills-alert-soft p-3 mb-0">
                        <i class="fas fa-question-circle me-1" style="color: var(--bills-accent-deep);" aria-hidden="true"></i>
                        <strong>Payment plans</strong>
                        <p class="small mb-0 mt-1 text-secondary">Ask about flexible plans for larger treatments.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
