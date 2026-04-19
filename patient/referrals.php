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
    die('Patient record not found.');
}

$patient = patient_portal_fetch_patient_cloud_first((int) $patientId);
if (!$patient) {
    die('Patient record not found.');
}

if (empty($patient['referral_code'])) {
    $newCode = strtoupper(substr(md5($patientId . uniqid()), 0, 8));
    try {
        patient_portal_set_referral_code_cloud_first((int) $patientId, $newCode);
        $db->execute("UPDATE patients SET referral_code = ?, sync_status = 'pending' WHERE id = ?", [$newCode, $patientId], 'si');
        sync_push_row_now('patients', (int) $patientId);
        $patient = patient_portal_fetch_patient_cloud_first((int) $patientId);
    } catch (Throwable $e) {
        error_log('Patient referrals cloud-first code update failed: ' . $e->getMessage());
    }
}

$referred = patient_portal_list_referred_patients_cloud_first((int) $patientId);

$referralCount = count($referred);
$pointsEarned = $referralCount * 50;

$pageTitle = 'My Referrals';
include '../layouts/header.php';
?>


<div class="container-fluid bills-page patient-portal referrals-page">
 

    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-md-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-share-alt me-2 opacity-90" aria-hidden="true"></i>My Referrals
                </h2>
                <p class="mb-0 opacity-90">Share your unique code and earn points for every friend who joins!</p>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="bills-balance-wrap">
                    <div class="bills-balance-box">
                        <small>Points from referrals</small>
                        <p class="bills-balance-amount">+<?php echo (int) $pointsEarned; ?></p>
                        <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;"><?php echo (int) $referralCount; ?> friend<?php echo $referralCount !== 1 ? 's' : ''; ?> · 50 pts each</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row patient-stats-row mb-4 g-3">
        <div class="col-6 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--subs">
                <div class="bills-stats-number"><?php echo (int) $referralCount; ?></div>
                <div class="bills-stats-label">People referred</div>
            </div>
        </div>
        <div class="col-6 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--paid">
                <div class="bills-stats-number"><?php echo (int) $pointsEarned; ?></div>
                <div class="bills-stats-label">Points earned</div>
            </div>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--invoices">
                <div class="bills-stats-number"><?php echo (int) floor($pointsEarned / 250); ?></div>
                <div class="bills-stats-label">Rewards unlocked</div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-5">
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-code me-2" aria-hidden="true"></i>Share your referral code</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="ref-code-panel">
                        <div class="ref-code-display" id="referralCodeText"><?php echo htmlspecialchars($patient['referral_code']); ?></div>
                        <button type="button" class="btn btn-sm bills-cta bills-cta--book w-100" id="copyCodeBtn">
                            <i class="fas fa-copy me-1"></i>Copy code
                        </button>
                        <div class="ref-link-input-group">
                            <input type="text" id="referralLink" readonly value="<?php echo htmlspecialchars(url('register.php?ref=' . $patient['referral_code'])); ?>">
                            <button type="button" id="copyLinkBtn" title="Copy link"><i class="fas fa-copy"></i></button>
                        </div>
                        <small class="text-muted d-block mt-3">
                            <i class="fas fa-info-circle me-1"></i>Code <strong><?php echo htmlspecialchars($patient['referral_code']); ?></strong> is yours to share.
                        </small>
                    </div>
                    <button type="button" class="btn btn-whatsapp-ref w-100" id="whatsappBtn">
                        <i class="fab fa-whatsapp me-2"></i>Share via WhatsApp
                    </button>
                </div>
            </div>

            <div class="card bills-dash-section-card mb-0">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2" aria-hidden="true"></i>How it works</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <div class="me-3"><span class="ref-step-num">1</span></div>
                        <div>
                            <strong>Share your code</strong>
                            <p class="small text-muted mb-0">Send your link or code to friends and family.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3"><span class="ref-step-num">2</span></div>
                        <div>
                            <strong>They sign up</strong>
                            <p class="small text-muted mb-0">They use your code when creating an account.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3"><span class="ref-step-num">3</span></div>
                        <div>
                            <strong>You earn points</strong>
                            <p class="small text-muted mb-0">50 points per referral; they get a welcome bonus on their first visit.</p>
                        </div>
                    </div>
                    <div class="bills-alert-soft p-3 mb-0">
                        <i class="fas fa-gift me-1" style="color:var(--bills-accent-deep);" aria-hidden="true"></i>
                        <strong>No limits</strong> — refer as many friends as you like.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card bills-dash-section-card mb-0">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-users me-2" aria-hidden="true"></i>Your referred friends</h5>
                        </div>
                        <span class="bills-badge bills-badge--blue"><?php echo (int) $referralCount; ?> total</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($referred)): ?>
                        <div class="bills-empty-state text-center py-4 px-3">
                            <p class="text-muted small mb-3">You haven’t referred anyone yet.</p>
                            <p class="text-muted small mb-3">Share your code to earn 50 points per signup.</p>
                            <button type="button" class="btn btn-sm bills-cta bills-cta--book" id="emptyCopyBtn">
                                <i class="fas fa-copy me-1"></i>Copy your code
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($referred as $ref): ?>
                            <div class="bills-dash-row">
                                <span class="bills-side-id"><?php echo htmlspecialchars(formatDate($ref['created_at'])); ?></span>
                                <div class="bills-dash-col-main">
                                    <span class="bills-dash-strong"><?php echo htmlspecialchars($ref['full_name']); ?></span>
                                    <?php
                                    $refMeta = array_filter([(string) ($ref['email'] ?? ''), (string) ($ref['phone'] ?? '')]);
                                    $refMetaStr = implode(' · ', $refMeta);
                                    ?>
                                    <span class="bills-dash-muted"><?php echo $refMetaStr !== '' ? htmlspecialchars($refMetaStr) : '—'; ?></span>
                                </div>
                                <div class="bills-dash-actions">
                                    <span class="bills-badge bills-badge--green"><i class="fas fa-star me-1" aria-hidden="true"></i>+50 pts</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="p-3">
                            <div class="bills-alert-soft mb-0">
                                <i class="fas fa-chart-line me-1" style="color:var(--bills-accent-deep);" aria-hidden="true"></i>
                                <strong><?php echo (int) $referralCount; ?> referral<?php echo $referralCount !== 1 ? 's' : ''; ?></strong> earned you
                                <strong><?php echo (int) $pointsEarned; ?> points</strong>.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const referralCode = <?php echo json_encode($patient['referral_code']); ?>;
const fullLink = <?php echo json_encode(url('register.php?ref=' . $patient['referral_code'])); ?>;

function showMessage(message) {
    const existing = document.querySelector('.ref-success-toast');
    if (existing) {
        existing.remove();
    }
    const msg = document.createElement('div');
    msg.className = 'ref-success-toast';
    msg.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + message;
    document.body.appendChild(msg);
    setTimeout(() => msg.remove(), 2200);
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        return navigator.clipboard.writeText(text).then(() => true).catch(() => false);
    }
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch (e) {
        ok = false;
    }
    document.body.removeChild(textarea);
    return Promise.resolve(ok);
}

function copyCode() {
    if (!referralCode) {
        alert('Referral code not found. Please refresh the page.');
        return;
    }
    copyToClipboard(referralCode).then((ok) => {
        if (ok) {
            showMessage('Code copied');
        } else {
            alert('Copy manually: ' + referralCode);
        }
    });
}

function copyLink() {
    copyToClipboard(fullLink).then((ok) => {
        if (ok) {
            showMessage('Link copied');
        } else {
            alert('Copy manually: ' + fullLink);
        }
    });
}

function shareWhatsApp() {
    if (!referralCode) {
        alert('Referral code not found.');
        return;
    }
    const text = 'Join me at Dental Clinic! Use my referral code: ' + referralCode + ' to get bonus points on your first visit!';
    window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
}

document.addEventListener('DOMContentLoaded', function () {
    if (!referralCode) {
        const el = document.getElementById('referralCodeText');
        if (el) {
            el.style.color = '#b91c1c';
            el.textContent = 'No code — contact support.';
        }
        return;
    }

    document.querySelectorAll('#copyCodeBtn, #emptyCopyBtn').forEach((btn) => btn.addEventListener('click', copyCode));
    const linkBtn = document.getElementById('copyLinkBtn');
    if (linkBtn) {
        linkBtn.addEventListener('click', copyLink);
    }
    const whatsappBtn = document.getElementById('whatsappBtn');
    if (whatsappBtn) {
        whatsappBtn.addEventListener('click', shareWhatsApp);
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
