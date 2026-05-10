<?php
/**
 * Per-day weekly booking + optional unified slot length (same parent card as profile / add user).
 *
 * @var array $wh from getDoctorBookingHours / decode (must include by_dow)
 * @var string $px field prefix: wh_ (profile) or add_wh_ (add user)
 * @var int|null $bookingSlotMinutes when set → show slot row
 * @var bool $bookingSlotCanEdit whether patient_slot_minutes is editable
 */
declare(strict_types=1);

if (!isset($wh) || !is_array($wh)) {
    $wh = defaultBookingCalendarHours();
}
$px = isset($px) && is_string($px) && $px !== '' ? $px : 'wh_';

$byDow = $wh['by_dow'] ?? [];
$dowLabels = [
    1 => (string) __('settings_dow_mon', 'Monday'),
    2 => (string) __('settings_dow_tue', 'Tuesday'),
    3 => (string) __('settings_dow_wed', 'Wednesday'),
    4 => (string) __('settings_dow_thu', 'Thursday'),
    5 => (string) __('settings_dow_fri', 'Friday'),
    6 => (string) __('settings_dow_sat', 'Saturday'),
    7 => (string) __('settings_dow_sun', 'Sunday'),
];
?>
<div class="settings-booking-schedule-block mb-4">
    <label class="form-label"><?php echo htmlspecialchars((string) __('settings_doctor_booking_hours', 'Weekly Schedule')); ?></label>
    <p class="text-muted small mb-3"><?php echo htmlspecialchars((string) __('settings_doctor_hours_hint_flexible', 'Set each day on its own. Mark days off as Closed.')); ?></p>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 doctor-booking-hours-table">
            <thead>
                <tr>
                    <th scope="col"><?php echo htmlspecialchars((string) __('settings_hours_day', 'Day')); ?></th>
                    <th scope="col" class="text-center doctor-booking-hours-table__closed-col"><?php echo htmlspecialchars((string) __('settings_hours_closed', 'Closed')); ?></th>
                    <th scope="col"><?php echo htmlspecialchars((string) __('settings_hours_open_close', 'Open / Close')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php for ($d = 1; $d <= 7; $d++):
                    $entry = $byDow[$d] ?? $byDow[(string) $d] ?? null;
                    $closed = $entry === null || !is_array($entry);
                    $o = $closed ? '09:00' : normalizeBookingTimeInput((string) ($entry['open'] ?? '09:00'));
                    $c = $closed ? '17:00' : normalizeBookingTimeInput((string) ($entry['close'] ?? '17:00'));
                    $cbId = $px . 'day_' . $d . '_closed_cb';
                    ?>
                <tr class="js-wh-dow-row" data-dow="<?php echo $d; ?>">
                    <th scope="row"><?php echo htmlspecialchars($dowLabels[$d] ?? ('Day ' . $d)); ?></th>
                    <td class="text-center">
                        <input class="form-check-input js-wh-day-closed m-0" type="checkbox" name="<?php echo htmlspecialchars($px); ?>day_<?php echo $d; ?>_closed" value="1" id="<?php echo htmlspecialchars($cbId); ?>" <?php echo $closed ? 'checked' : ''; ?> aria-label="<?php echo htmlspecialchars((string) __('settings_hours_closed', 'Closed')); ?>">
                    </td>
                    <td>
                        <div class="row g-2 align-items-stretch js-wh-day-fields flex-column flex-sm-row" style="<?php echo $closed ? 'display:none' : ''; ?>">
                            <div class="col-12 col-sm-6">
                                <label class="visually-hidden" for="<?php echo htmlspecialchars($px . 'day_' . $d . '_open'); ?>"><?php echo htmlspecialchars((string) __('settings_hours_open', 'Open')); ?></label>
                                <input type="time" class="form-control form-control-sm" id="<?php echo htmlspecialchars($px . 'day_' . $d . '_open'); ?>" name="<?php echo htmlspecialchars($px); ?>day_<?php echo $d; ?>_open" value="<?php echo htmlspecialchars($o); ?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="visually-hidden" for="<?php echo htmlspecialchars($px . 'day_' . $d . '_close'); ?>"><?php echo htmlspecialchars((string) __('settings_hours_close', 'Close')); ?></label>
                                <input type="time" class="form-control form-control-sm" id="<?php echo htmlspecialchars($px . 'day_' . $d . '_close'); ?>" name="<?php echo htmlspecialchars($px); ?>day_<?php echo $d; ?>_close" value="<?php echo htmlspecialchars($c); ?>">
                            </div>
                        </div>
                        <div class="js-wh-day-off text-muted small mb-0" style="<?php echo $closed ? '' : 'display:none'; ?>"><?php echo htmlspecialchars((string) __('settings_hours_day_off', 'Off')); ?></div>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($bookingSlotMinutes) && $bookingSlotMinutes !== null): ?>
    <div class="mt-3 mb-0">
        <?php if (!empty($bookingSlotCanEdit)): ?>
            <label class="form-label" for="<?php echo htmlspecialchars($px); ?>patient_slot_minutes"><?php echo htmlspecialchars((string) __('settings_booking_slot_minutes', 'Default appointment slot length (minutes)')); ?></label>
            <div class="doctor-booking-slot-input-wrap">
                <input type="number" class="form-control" id="<?php echo htmlspecialchars($px); ?>patient_slot_minutes" name="patient_slot_minutes" min="10" max="120" step="5" value="<?php echo (int) $bookingSlotMinutes; ?>" required>
            </div>
        <?php else: ?>
            <label class="form-label"><?php echo htmlspecialchars((string) __('settings_booking_slot_minutes', 'Default appointment slot length (minutes)')); ?></label>
            <div class="doctor-booking-slot-input-wrap">
                <div class="form-control-plaintext fw-semibold py-2"><?php echo (int) $bookingSlotMinutes; ?> <?php echo htmlspecialchars((string) __('minutes', 'minutes')); ?></div>
            </div>
            <small class="text-muted"><?php echo htmlspecialchars((string) __('settings_slot_readonly_hint', 'Only clinic administrators can change this.')); ?></small>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
