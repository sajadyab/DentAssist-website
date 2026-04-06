<?php
/**
 * Staff dashboard: week/day booking grid (same slot rules as patient/queue.php) + staff states.
 * Sets: $staffCalDoctorId, $staffCalView, $staffCalWeekOffset, $staffCalDayYmd,
 *       $staffCalMonday, $staffCalWeekEnd, $staffCalColumns, $staffCalTimeRows,
 *       $staffCalSlotByDayTime, $staffCalSlotMinutes, $staffCalDoctorName
 */
declare(strict_types=1);

if (!isset($db) || !$db instanceof Database) {
    return;
}

$staffCalSlotMinutes = (int) (getClinicBookingCalendarConfig($db)['slot_minutes'] ?? 30);
if ($staffCalSlotMinutes < 10 || $staffCalSlotMinutes > 120) {
    $staffCalSlotMinutes = 30;
}
$hoursConfig = getClinicBookingCalendarConfig($db)['hours'];

if ($dashboardRole === 'doctor') {
    $staffCalDoctorId = $dashboardUserId;
} else {
    $staffCalDoctorId = (int) ($_GET['cal_doctor_id'] ?? 0);
    if ($staffCalDoctorId <= 0) {
        $staffCalDoctorId = $defaultCalDoctorId;
    }
    if (!repo_dashboard_find_active_doctor_row($staffCalDoctorId)) {
        $staffCalDoctorId = $defaultCalDoctorId;
    }
}
$staffCalDoctorName = repo_dashboard_find_user_full_name($staffCalDoctorId);

$staffCalView = isset($_GET['cal_view']) && $_GET['cal_view'] === 'day' ? 'day' : 'week';
$staffCalWeekOffset = (int) ($_GET['cal_week'] ?? 0);
if ($staffCalWeekOffset < -104 || $staffCalWeekOffset > 104) {
    $staffCalWeekOffset = 0;
}

$todayImm = new DateTimeImmutable('today');
$monday = $todayImm->modify('monday this week')->modify(($staffCalWeekOffset * 7) . ' days');
$weekEnd = $monday->modify('+6 days');

$staffCalMonday = $monday;
$staffCalWeekEnd = $weekEnd;

$staffCalDayYmd = (string) ($_GET['cal_day'] ?? '');
if ($staffCalView === 'day') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $staffCalDayYmd)) {
        $staffCalDayYmd = $todayImm->format('Y-m-d');
    }
    $dayImm = DateTimeImmutable::createFromFormat('Y-m-d', $staffCalDayYmd);
    if (!$dayImm) {
        $dayImm = $todayImm;
        $staffCalDayYmd = $dayImm->format('Y-m-d');
    }
    $rangeStart = $staffCalDayYmd;
    $rangeEnd = $staffCalDayYmd;
    $dayCols = [['date' => $dayImm, 'ymd' => $staffCalDayYmd]];
} else {
    $rangeStart = $monday->format('Y-m-d');
    $rangeEnd = $weekEnd->format('Y-m-d');
    $dayCols = [];
    for ($i = 0; $i < 7; $i++) {
        $d = $monday->modify("+{$i} days");
        $n = (int) $d->format('N');
        if (clinicHoursBandForWeekdayN($n, $hoursConfig) === null) {
            continue;
        }
        $dayCols[] = ['date' => $d, 'ymd' => $d->format('Y-m-d')];
    }
}

$appts = repo_dashboard_list_calendar_appointments($staffCalDoctorId, $rangeStart, $rangeEnd);

$reqs = repo_dashboard_list_calendar_requests($staffCalDoctorId, $rangeStart, $rangeEnd);

/**
 * Normalize to H:i:s.
 */
$staffCalNormalizeHis = static function (string $t): string {
    $t = trim($t);

    return strlen($t) === 5 ? $t . ':00' : $t;
};

$now = new DateTimeImmutable('now');

/**
 * @return array{0: string, 1: ?array} state and optional payload for modals
 */
$staffCalCellState = static function (
    string $ymd,
    string $timeHis,
    int $slotMinutes,
    array $hoursBand,
    DateTimeImmutable $now,
    array $appts,
    array $reqs,
    callable $normHis
): array {
    $slotStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' ' . $normHis($timeHis));
    if (!$slotStart) {
        return ['empty', null];
    }
    $slotEnd = $slotStart->modify('+' . $slotMinutes . ' minutes');
    $slotTs0 = $slotStart->getTimestamp();
    $slotTs1 = $slotEnd->getTimestamp();

    if ($slotEnd <= $now) {
        return ['past', null];
    }

    foreach ($reqs as $rq) {
        if ($rq['requested_date'] !== $ymd) {
            continue;
        }
        $dur = max(5, (int) $rq['duration_minutes']);
        $rStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' ' . $normHis((string) $rq['requested_time']));
        if (!$rStart) {
            continue;
        }
        $rEnd = $rStart->modify('+' . $dur . ' minutes');
        if ($slotTs0 < $rEnd->getTimestamp() && $slotTs1 > $rStart->getTimestamp()) {
            return ['request', $rq];
        }
    }

    foreach ($appts as $ap) {
        if ($ap['appointment_date'] !== $ymd) {
            continue;
        }
        $aStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' ' . $normHis((string) $ap['appointment_time']));
        if (!$aStart) {
            continue;
        }
        $durMin = max(1, (int) $ap['duration']);
        $aEnd = $aStart->modify('+' . $durMin . ' minutes');
        if ($slotTs0 < $aEnd->getTimestamp() && $slotTs1 > $aStart->getTimestamp()) {
            return ['scheduled', $ap];
        }
    }

    $openT = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $hoursBand['open']);
    $closeT = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $hoursBand['close']);
    if (!$openT || !$closeT) {
        return ['empty', null];
    }
    if ($slotStart < $openT || $slotEnd > $closeT) {
        return ['empty', null];
    }

    return ['free', null];
};

$staffCalSlotByDayTime = [];
$calendarTimeKeySet = [];

foreach ($dayCols as $col) {
    $ymd = $col['ymd'];
    $n = (int) $col['date']->format('N');
    $band = clinicHoursBandForWeekdayN($n, $hoursConfig);
    $slots = [];
    if ($band !== null) {
        $openDt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $band['open']);
        $closeDt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $band['close']);
        if ($openDt && $closeDt) {
            $cursor = $openDt;
            while ($cursor < $closeDt) {
                $next = $cursor->modify('+' . $staffCalSlotMinutes . ' minutes');
                if ($next > $closeDt) {
                    break;
                }
                $his = $cursor->format('H:i:s');
                [$st, $payload] = $staffCalCellState(
                    $ymd,
                    $his,
                    $staffCalSlotMinutes,
                    $band,
                    $now,
                    $appts,
                    $reqs,
                    $staffCalNormalizeHis
                );
                $slots[] = [
                    'time' => $his,
                    'label' => $cursor->format('g:i A'),
                    'state' => $st,
                    'payload' => $payload,
                ];
                $cursor = $next;
            }
        }
    }
    $staffCalSlotByDayTime[$ymd] = [];
    foreach ($slots as $sl) {
        $k = $sl['time'];
        $staffCalSlotByDayTime[$ymd][$k] = $sl;
        $calendarTimeKeySet[$k] = true;
    }
}

$staffCalTimeRows = array_keys($calendarTimeKeySet);
sort($staffCalTimeRows, SORT_STRING);

$staffCalColumns = $dayCols;
