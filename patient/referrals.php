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

$ensuredReferralCode = patient_portal_ensure_referral_code((int) $patientId);
$patient = patient_portal_fetch_patient_cloud_first((int) $patientId);
if (!$patient) {
    die('Patient record not found.');
}

/** Authoritative uppercase code for this page (cloud ensure + tolerant fallbacks). */
$referralCodeDisplay = '';
if ($ensuredReferralCode !== null && $ensuredReferralCode !== '') {
    $referralCodeDisplay = strtoupper(trim((string) $ensuredReferralCode));
}
if ($referralCodeDisplay === '' && !empty($patient['referral_code'])) {
    $referralCodeDisplay = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $patient['referral_code']));
}
if ($referralCodeDisplay === '') {
    $localPatient = $db->fetchOne('SELECT referral_code FROM patients WHERE id = ? LIMIT 1', [$patientId], 'i');
    if ($localPatient && trim((string) ($localPatient['referral_code'] ?? '')) !== '') {
        $referralCodeDisplay = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $localPatient['referral_code']));
    }
}
if ($referralCodeDisplay !== '') {
    $patient['referral_code'] = $referralCodeDisplay;
}

$referred = patient_portal_list_referred_patients_cloud_first((int) $patientId);

$referralCount = count($referred);
$pointsEarned = $referralCount * 50;
$signupLink = $referralCodeDisplay !== '' ? url('register.php?ref=' . rawurlencode($referralCodeDisplay)) : '';
$clinicLineName = preg_replace('/\s+/u', ' ', trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', (string) SITE_NAME))));

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
                <p class="mb-0 opacity-90">Share your unique code and earn points when friends join!</p>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="bills-balance-wrap">
                    <div class="bills-balance-box">
                        <small><?php echo htmlspecialchars(__('referrals_summary_label', 'Referrals')); ?></small>
                        <p class="bills-balance-amount"><?php echo (int) $referralCount; ?></p>
                        <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;">50 pts each</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row patient-stats-row mb-4 g-3 d-none d-md-flex">
        <div class="col-md-4 mb-md-3">
            <div class="bills-stats-card bills-stats-card--subs">
                <div class="bills-stats-number"><?php echo (int) $referralCount; ?></div>
                <div class="bills-stats-label">People referred</div>
            </div>
        </div>
        <div class="col-md-4 mb-md-3">
            <div class="bills-stats-card bills-stats-card--paid">
                <div class="bills-stats-number"><?php echo (int) $pointsEarned; ?></div>
                <div class="bills-stats-label">Points earned</div>
            </div>
        </div>
        <div class="col-md-4 mb-md-3">
            <div class="bills-stats-card bills-stats-card--invoices">
                <div class="bills-stats-number"><?php echo (int) max(0, (int) floor($pointsEarned / 250)); ?></div>
                <div class="bills-stats-label">Rewards unlocked</div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-5">
            <div class="card bills-dash-section-card referrals-share-card mb-4 overflow-hidden">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-share-alt me-2" aria-hidden="true"></i><?php echo htmlspecialchars(__('share_referral_code', 'Share your referral code')); ?></h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body referrals-share-card__body text-center px-3 px-sm-4 py-4">
                    <div class="referrals-share-code font-monospace" id="referralCodeText"><?php echo htmlspecialchars($referralCodeDisplay ?: '—'); ?></div>
                    <p class="referrals-share-sub mb-4"><?php echo htmlspecialchars(__('referral_yours_to_share', 'is yours to share.')); ?></p>
                    <?php if ($signupLink !== ''): ?>
                    <button type="button" class="referrals-share-btn referrals-share-btn--wa w-100 mb-3" id="whatsappBtn">
                        <i class="fab fa-whatsapp me-2" aria-hidden="true"></i><?php echo htmlspecialchars(__('share_via_whatsapp', 'Share via WhatsApp')); ?>
                    </button>
                    <button type="button" class="referrals-share-btn referrals-share-btn--copy w-100 mb-2" id="copyCodeBtn">
                        <i class="fas fa-copy me-2" aria-hidden="true"></i><?php echo htmlspecialchars(__('copy_code', 'Copy code')); ?>
                    </button>
                    <?php else: ?>
                    <p class="text-danger small mb-0"><?php echo htmlspecialchars(__('referral_code_unavailable', 'Your referral code could not be loaded. Refresh the page or contact support.')); ?></p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <div class="col-lg-7">
            <div class="card bills-dash-section-card mb-4">
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
                            <?php if ($signupLink !== ''): ?>
                            <button type="button" class="referrals-share-btn referrals-share-btn--copy" id="emptyCopyBtn">
                                <i class="fas fa-copy me-1" aria-hidden="true"></i>Copy your code
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($referred as $ref): ?>
                            <div class="bills-dash-row">
                                <span class="bills-side-id"><?php echo htmlspecialchars(formatDate($ref['created_at'])); ?></span>
                                <div class="bills-dash-col-main">
                                    <span class="bills-dash-strong"><?php echo htmlspecialchars($ref['full_name']); ?></span>
                                    <?php
                                    $refPhone = trim((string) ($ref['phone'] ?? ''));
                                    ?>
                                    <span class="bills-dash-muted"><?php echo $refPhone !== '' ? htmlspecialchars($refPhone) : '—'; ?></span>
                                </div>
                                <div class="bills-dash-actions">
                                    <span class="bills-badge bills-badge--green"><i class="fas fa-star me-1" aria-hidden="true"></i>+50 pts</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card bills-dash-section-card referrals-how-card mb-0 overflow-hidden">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2" aria-hidden="true"></i><?php echo htmlspecialchars(__('how_it_works', 'How it works')); ?></h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body pt-3 pt-md-4">
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
    </div>
</div>

<script>
const referralCode = <?php echo json_encode($referralCodeDisplay); ?>;
const fullLink = <?php echo json_encode($signupLink); ?>;
const clinicName = <?php echo json_encode($clinicLineName); ?>;

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
    let text = '';
    if (fullLink && fullLink.length > 0) {
        text = [
            clinicName ? 'Join me at ' + clinicName + '!' : 'Join DentAssist!',
            'Use referral code ' + referralCode + ' when you register.',
            'Sign up link: ' + fullLink,
        ].join('\n');
    } else {
        text = [
            clinicName ? 'Join me at ' + clinicName + '!' : 'Join DentAssist!',
            'Use my referral code: ' + referralCode,
        ].join('\n');
    }
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
