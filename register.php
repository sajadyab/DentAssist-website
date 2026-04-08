<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$pageTitle = 'Patient Registration';
$authBodyClass = 'auth-shell--register';
$authNavActive = 'register';
$authIncludeIntlTel = true;
include 'layouts/auth_header.php';
?>

    <div class="auth-card">
        <div class="auth-header">
            <h1>DentAssist<span class="d-none d-md-inline"> </span><br class="d-md-none">Smart Dental Clinic</h1>
        
        </div>

        <div id="message"></div>

        <form method="POST" action="api/register.php" data-api="api/register.php" data-message-target="#message" class="register-form" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date of Birth *</label>
                    <input type="date" name="date_of_birth" class="form-control" required value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone Number *</label>
                    <input
                        type="tel"
                        id="phone"
                        class="form-control"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        placeholder="Enter phone number"
                        value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                        required
                    >
                    <input type="hidden" name="phone_country" id="phone_country" value="<?php echo htmlspecialchars($_POST['phone_country'] ?? ''); ?>">
                    <input type="hidden" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <hr>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Referral Code <small class="text-muted">(optional)</small></label>
                    <input type="text" name="referral_code" class="form-control" value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>">
                    <div class="form-text">A friend’s code so he can earn points.</div>
                </div>
            </div>

            <button type="submit" class="btn-register">Register</button>
        </form>

            <div class="text-center mt-3">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
    </div>

<?php
ob_start();
?>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js"></script>
<script>
const phoneInput = document.querySelector("#phone");
if (phoneInput) {
  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "lb",
    separateDialCode: true,
    preferredCountries: ["lb", "ae", "sa", "fr", "us"],
    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js"
  });
  const phoneLimits = { lb: 8, ae: 9, sa: 9, fr: 9, us: 10, ca: 10, gb: 10, de: 11, it: 10, es: 9 };
  function enforceLimit() {
    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, "");
    const country = iti.getSelectedCountryData().iso2;
    const max = phoneLimits[country] || 15;
    if (phoneInput.value.length > max) phoneInput.value = phoneInput.value.slice(0, max);
    phoneInput.maxLength = max;
  }
  function syncHidden() {
    const data = iti.getSelectedCountryData();
    const dial = String((data && data.dialCode) ? data.dialCode : "");
    document.querySelector("#phone_country").value = dial;
    document.querySelector("#phone_number").value = phoneInput.value;
  }
  function onChange() { enforceLimit(); syncHidden(); }
  phoneInput.addEventListener("input", onChange);
  phoneInput.addEventListener("countrychange", onChange);
  const form = phoneInput.closest("form");
  if (form) form.addEventListener("submit", onChange);
  onChange();
}
</script>

<script>
    (function () {
        const form = document.querySelector('form.register-form');
        if (!form) return;

        function clearValidationUi() {
            form.querySelectorAll('.required-badge').forEach(function (el) { el.remove(); });
            form.querySelectorAll('.field-invalid-required').forEach(function (el) {
                el.classList.remove('field-invalid-required');
            });
            form.querySelectorAll('.iti.field-invalid-wrap').forEach(function (el) {
                el.classList.remove('field-invalid-wrap');
            });
        }

        function markInvalidField(field) {
            if (!field || field.type === 'hidden' || field.type === 'submit') return;
            const wrapper = field.closest('.mb-3') || field.closest('.col-md-6') || field.parentElement;
            if (!wrapper) return;
            const label = wrapper.querySelector('label.form-label') || wrapper.querySelector('label:not(.form-check-label)');
            if (label && !label.querySelector('.required-badge')) {
                const badge = document.createElement('span');
                badge.className = 'required-badge';
                badge.setAttribute('aria-live', 'polite');
                badge.textContent = 'Required';
                label.appendChild(badge);
            }
            field.classList.add('field-invalid-required');
            const iti = field.closest('.iti');
            if (iti) iti.classList.add('field-invalid-wrap');
        }

        form.addEventListener('submit', function (e) {
            clearValidationUi();

            if (form.checkValidity()) {
                return;
            }

            e.preventDefault();

            const invalidFields = Array.from(form.querySelectorAll(':invalid'));
            invalidFields.forEach(markInvalidField);

            const first = invalidFields[0];
            if (first && typeof first.focus === 'function') {
                first.focus({ preventScroll: true });
                first.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });

        form.addEventListener('input', function (e) {
            const t = e.target;
            if (!t || !t.classList || !t.classList.contains('field-invalid-required')) return;
            if (typeof t.checkValidity === 'function' && t.checkValidity()) {
                t.classList.remove('field-invalid-required');
                const iti = t.closest('.iti');
                if (iti) iti.classList.remove('field-invalid-wrap');
                const wrapper = t.closest('.mb-3') || t.closest('.col-md-6');
                const label = wrapper && (wrapper.querySelector('label.form-label') || wrapper.querySelector('label:not(.form-check-label)'));
                const badge = label && label.querySelector('.required-badge');
                if (badge) badge.remove();
            }
        }, true);

        form.addEventListener('change', function (e) {
            const t = e.target;
            if (!t || !t.classList || !t.classList.contains('field-invalid-required')) return;
            if (typeof t.checkValidity === 'function' && t.checkValidity()) {
                t.classList.remove('field-invalid-required');
                const iti = t.closest('.iti');
                if (iti) iti.classList.remove('field-invalid-wrap');
                const wrapper = t.closest('.mb-3') || t.closest('.col-md-6');
                const label = wrapper && (wrapper.querySelector('label.form-label') || wrapper.querySelector('label:not(.form-check-label)'));
                const badge = label && label.querySelector('.required-badge');
                if (badge) badge.remove();
            }
        }, true);
    })();
</script>
<?php
$authFooterExtra = ob_get_clean();
include 'layouts/auth_footer.php';
