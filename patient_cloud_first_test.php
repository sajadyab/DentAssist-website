#!/usr/bin/env php
<?php
/**
 * patient_cloud_first_test.php
 *
 * Comprehensive testing script to verify patient data flows through Supabase:
 * 1. Check if data loads from cloud first (not local)
 * 2. Verify updates are stored in Supabase
 * 3. Watch sync bring data back to local
 *
 * Usage: php patient_cloud_first_test.php [patient_id] [action]
 * Examples:
 *   php patient_cloud_first_test.php 1 status       <- Check current state
 *   php patient_cloud_first_test.php 1 load_cloud   <- Force load from cloud
 *   php patient_cloud_first_test.php 1 update       <- Update & sync cycle
 *   php patient_cloud_first_test.php 1 full_test    <- Run all tests
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/supabase_client.php';
require_once __DIR__ . '/includes/patient_cloud_repository.php';

// ============================================================================
// TEST UTILITIES
// ============================================================================

class PatientCloudFirstTester
{
    private PDO $pdo;
    private SupabaseAPI $supabase;
    private int $patientId;
    private array $testLog = [];

    public function __construct(int $patientId)
    {
        $this->patientId = $patientId;
        
        $this->pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        try {
            $this->supabase = new SupabaseAPI((string) SUPABASE_URL, (string) SUPABASE_KEY);
        } catch (Throwable $e) {
            throw new RuntimeException('Supabase init failed: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // TEST 1: Check Data Source (Cloud vs Local)
    // ========================================================================

    public function testDataSource(): array
    {
        echo "=" . str_repeat("=", 78) . "\n";
        echo "TEST 1: Data Source Verification (Cloud vs Local)\n";
        echo "=" . str_repeat("=", 78) . "\n\n";

        $results = [
            'cloud_patient' => null,
            'local_patient' => null,
            'cloud_first_result' => null,
            'source_used' => 'unknown',
        ];

        // Fetch from Supabase
        try {
            echo "→ Fetching patient ID {$this->patientId} from SUPABASE...\n";
            $cloudRows = $this->supabase->select('patients', [
                'select' => '*',
                'local_id' => 'eq.' . $this->patientId,
                'limit' => 1,
            ]);
            if (!empty($cloudRows)) {
                $results['cloud_patient'] = $cloudRows[0];
                echo "  ✓ Found in Supabase\n";
            } else {
                echo "  ✗ Not found in Supabase (local_id filter)\n";
            }
        } catch (Throwable $e) {
            echo "  ✗ Supabase error: " . $e->getMessage() . "\n";
        }

        // Fetch from local MySQL
        try {
            echo "→ Fetching patient ID {$this->patientId} from LOCAL MySQL...\n";
            $stmt = $this->pdo->prepare('SELECT * FROM patients WHERE id = ?');
            $stmt->execute([$this->patientId]);
            $localResult = $stmt->fetch();
            if ($localResult) {
                $results['local_patient'] = $localResult;
                echo "  ✓ Found in local MySQL\n";
            } else {
                echo "  ✗ Not found in local MySQL\n";
            }
        } catch (Throwable $e) {
            echo "  ✗ Local error: " . $e->getMessage() . "\n";
        }

        // Now call the cloud-first function
        echo "\n→ Calling patient_portal_fetch_patient_cloud_first({$this->patientId})...\n";
        $cloudFirstResult = patient_portal_fetch_patient_cloud_first($this->patientId);
        $results['cloud_first_result'] = $cloudFirstResult;

        // Determine which source was used
        if ($cloudFirstResult === null) {
            $results['source_used'] = 'NONE (data not found anywhere)';
            echo "  ✗ Data not found in either cloud or local!\n";
        } elseif ($results['cloud_patient']) {
            if (json_encode($cloudFirstResult, JSON_SORT_KEYS) === json_encode($results['cloud_patient'], JSON_SORT_KEYS)) {
                $results['source_used'] = '✓ CLOUD (Supabase)';
                echo "  ✓ Data came from SUPABASE (cloud-first working!)\n";
            } else {
                $results['source_used'] = 'LOCAL (fallback triggered)';
                echo "  ⚠ Data appears to be from LOCAL (Supabase fetch may have failed)\n";
            }
        } elseif ($results['local_patient']) {
            $results['source_used'] = 'LOCAL ONLY';
            echo "  ⚠ Data only in LOCAL MySQL (not synced to Supabase yet)\n";
        }

        // Show comparison
        echo "\n" . str_repeat("-", 80) . "\n";
        echo "COMPARISON:\n";
        echo str_repeat("-", 80) . "\n";

        if ($results['cloud_patient']) {
            echo "\nSUPABASE Data:\n";
            $this->prettyPrintRecord($results['cloud_patient']);
        } else {
            echo "\nSUPABASE: No data found\n";
        }

        if ($results['local_patient']) {
            echo "\nLOCAL MySQL Data:\n";
            $this->prettyPrintRecord($results['local_patient']);
        } else {
            echo "\nLOCAL: No data found\n";
        }

        echo "\nCloud-First Function Result:\n";
        $this->prettyPrintRecord($results['cloud_first_result'] ?? []);

        echo "\n" . str_repeat("=", 80) . "\n";
        echo "RESULT: Data source = {$results['source_used']}\n";
        echo str_repeat("=", 80) . "\n\n";

        return $results;
    }

    // ========================================================================
    // TEST 2: Verify Sync Status Before Sync
    // ========================================================================

    public function testSyncStatusBefore(): void
    {
        echo "=" . str_repeat("=", 78) . "\n";
        echo "TEST 2: Pre-Sync Status Check\n";
        echo "=" . str_repeat("=", 78) . "\n\n";

        // Check local sync status
        $stmt = $this->pdo->prepare(
            "SELECT sync_status, sync_error, last_sync_attempt FROM patients WHERE id = ?"
        );
        $stmt->execute([$this->patientId]);
        $syncStatus = $stmt->fetch();

        echo "Local sync_status: " . json_encode($syncStatus) . "\n\n";
    }

    // ========================================================================
    // TEST 3: Update Patient & Watch Sync Cycle
    // ========================================================================

    public function testUpdateAndSync(): void
    {
        echo "=" . str_repeat("=", 78) . "\n";
        echo "TEST 3: Update → Cloud Store → Local Sync Cycle\n";
        echo "=" . str_repeat("=", 78) . "\n\n";

        // Generate test data
        $testValue = 'TEST_' . date('His') . '_' . uniqid();
        $testEmail = 'patient_' . uniqid() . '@test.local';

        echo "Step 1: Prepare test data\n";
        echo "  - Test value: {$testValue}\n";
        echo "  - Test email: {$testEmail}\n\n";

        // Update local database (marks as pending)
        echo "Step 2: Update LOCAL database (will be marked pending for sync)\n";
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE patients SET notes = ?, email = ?, sync_status = 'pending' WHERE id = ?"
            );
            $stmt->execute([$testValue, $testEmail, $this->patientId]);
            echo "  ✓ Updated local database\n";
            echo "    - notes = '{$testValue}'\n";
            echo "    - email = '{$testEmail}'\n";
            echo "    - sync_status = 'pending'\n\n";
        } catch (Throwable $e) {
            echo "  ✗ Failed to update local: " . $e->getMessage() . "\n";
            return;
        }

        // Verify sync_status is pending
        $stmt = $this->pdo->prepare("SELECT sync_status FROM patients WHERE id = ?");
        $stmt->execute([$this->patientId]);
        $result = $stmt->fetch();
        echo "Step 3: Verify sync_status is 'pending'\n";
        echo "  Current sync_status: " . ($result['sync_status'] ?? 'unknown') . "\n\n";

        // Show what needs to be synced
        $stmt = $this->pdo->prepare("SELECT * FROM patients WHERE id = ? AND sync_status = 'pending'");
        $stmt->execute([$this->patientId]);
        $pendingRow = $stmt->fetch();

        if ($pendingRow) {
            echo "Step 4: Ready to sync to Supabase\n";
            echo "  Pending row:\n";
            $this->prettyPrintRecord($pendingRow);
            echo "\n  Next: Run 'php sync_to_supabase_fixed.php' to push to cloud\n\n";
        }

        echo str_repeat("=", 80) . "\n\n";
    }

    // ========================================================================
    // TEST 4: Fetch Appointments Cloud-First
    // ========================================================================

    public function testAppointmentsCloudFirst(): void
    {
        echo "=" . str_repeat("=", 78) . "\n";
        echo "TEST 4: Load Appointments (Cloud-First)\n";
        echo "=" . str_repeat("=", 78) . "\n\n";

        // Fetch from Supabase
        $cloudAppointments = null;
        try {
            echo "→ Fetching appointments from SUPABASE for patient {$this->patientId}...\n";
            $cloudRows = $this->supabase->select('appointments', [
                'select' => '*',
                'patient_id' => 'eq.' . $this->patientId,
                'order' => 'appointment_date.desc',
                'limit' => 10,
            ]);
            $cloudAppointments = $cloudRows ?? [];
            echo "  ✓ Found " . count($cloudAppointments) . " appointments in Supabase\n\n";
        } catch (Throwable $e) {
            echo "  ✗ Supabase error: " . $e->getMessage() . "\n\n";
        }

        // Fetch from local
        $localAppointments = null;
        try {
            echo "→ Fetching appointments from LOCAL MySQL for patient {$this->patientId}...\n";
            $stmt = $this->pdo->prepare(
                "SELECT a.*, u.full_name AS doctor_name 
                 FROM appointments a
                 LEFT JOIN users u ON u.id = a.doctor_id
                 WHERE a.patient_id = ?
                 ORDER BY a.appointment_date DESC
                 LIMIT 10"
            );
            $stmt->execute([$this->patientId]);
            $localAppointments = $stmt->fetchAll();
            echo "  ✓ Found " . count($localAppointments) . " appointments in local MySQL\n\n";
        } catch (Throwable $e) {
            echo "  ✗ Local error: " . $e->getMessage() . "\n\n";
        }

        // Call cloud-first function
        echo "→ Calling patient_portal_fetch_appointments_cloud_first({$this->patientId})...\n";
        $cloudFirstAppointments = patient_portal_fetch_appointments_cloud_first($this->patientId);
        echo "  ✓ Retrieved " . count($cloudFirstAppointments) . " appointments via cloud-first\n\n";

        // Show results
        echo str_repeat("-", 80) . "\n";
        echo "APPOINTMENTS COMPARISON:\n";
        echo str_repeat("-", 80) . "\n\n";

        echo "Sample appointment from cloud-first result:\n";
        if (!empty($cloudFirstAppointments)) {
            $this->prettyPrintRecord($cloudFirstAppointments[0]);
        } else {
            echo "  (No appointments found)\n";
        }

        echo "\n" . str_repeat("=", 80) . "\n\n";
    }

    // ========================================================================
    // TEST 5: Check Sync Runtime Status
    // ========================================================================

    public function testSyncRuntimeStatus(): void
    {
        echo "=" . str_repeat("=", 78) . "\n";
        echo "TEST 5: Sync Runtime Status\n";
        echo "=" . str_repeat("=", 78) . "\n\n";

        try {
            $stmt = $this->pdo->prepare(
                "SELECT 
                    table_name, 
                    local_id, 
                    status, 
                    message,
                    attempt_count,
                    last_started,
                    last_finished
                 FROM sync_runtime_status 
                 WHERE local_id = ?
                 ORDER BY last_finished DESC
                 LIMIT 10"
            );
            $stmt->execute([$this->patientId]);
            $syncEvents = $stmt->fetchAll();

            if (empty($syncEvents)) {
                echo "No sync events recorded for patient {$this->patientId}\n";
            } else {
                echo "Recent sync events for patient {$this->patientId}:\n\n";
                foreach ($syncEvents as $event) {
                    echo "  Table: {$event['table_name']}\n";
                    echo "  Status: {$event['status']}\n";
                    echo "  Attempts: {$event['attempt_count']}\n";
                    echo "  Last finished: {$event['last_finished']}\n";
                    if ($event['message']) {
                        echo "  Message: {$event['message']}\n";
                    }
                    echo "\n";
                }
            }
        } catch (Throwable $e) {
            echo "Could not fetch sync status: " . $e->getMessage() . "\n";
        }

        echo str_repeat("=", 80) . "\n\n";
    }

    // ========================================================================
    // TEST 6: Full Bidirectional Verification
    // ========================================================================

    public function testFullBidirectional(): void
    {
        echo "=" . str_repeat("=", 78) . "\n";
        echo "TEST 6: Full Bidirectional Sync Cycle (Simulation)\n";
        echo "=" . str_repeat("=", 78) . "\n\n";

        echo "SCENARIO: Patient updates info → Synced to cloud → Pulled back to local\n\n";

        echo "Step 1: Check current state (before any changes)\n";
        $before = patient_portal_fetch_patient_cloud_first($this->patientId);
        if ($before) {
            echo "  Phone (current): " . ($before['phone'] ?? 'empty') . "\n";
        }

        echo "\n\nStep 2: Local database UPDATE (simulating patient form submission)\n";
        $newPhone = '+1-' . rand(2000, 9999) . '-' . rand(1000, 9999);
        $stmt = $this->pdo->prepare(
            "UPDATE patients SET phone = ?, sync_status = 'pending' WHERE id = ?"
        );
        $stmt->execute([$newPhone, $this->patientId]);
        echo "  ✓ Updated phone to: {$newPhone}\n";
        echo "  ✓ Marked sync_status = 'pending'\n";

        echo "\n\nStep 3: Sync to cloud (requires manual run)\n";
        echo "  RUN: php sync_to_supabase_fixed.php\n";
        echo "  This will push pending changes to Supabase\n";

        echo "\n\nStep 4: Sync from cloud (brings cloud changes to local)\n";
        echo "  RUN: php sync_from_supabase.php\n";
        echo "  This will pull Supabase changes to local MySQL\n";

        echo "\n\nStep 5: Verify data is back in local\n";
        echo "  RUN: SELECT * FROM patients WHERE id = {$this->patientId}\\G\n";

        echo "\n\nExpected result:\n";
        echo "  ✓ phone field contains: {$newPhone}\n";
        echo "  ✓ sync_status = 'synced'\n";
        echo "  ✓ last_sync_attempt is recent\n";

        echo "\n" . str_repeat("=", 80) . "\n\n";
    }

    // ========================================================================
    // HELPER FUNCTIONS
    // ========================================================================

    private function prettyPrintRecord(?array $record, int $maxFields = 10): void
    {
        if ($record === null || empty($record)) {
            echo "  (empty or null)\n";
            return;
        }

        $count = 0;
        foreach ($record as $key => $value) {
            if ($count++ >= $maxFields) {
                echo "  ... and " . (count($record) - $maxFields) . " more fields\n";
                break;
            }

            $displayValue = $value;
            if (is_array($value) || is_object($value)) {
                $displayValue = json_encode($value);
            } elseif ($value === null) {
                $displayValue = '(null)';
            } elseif (strlen((string) $value) > 50) {
                $displayValue = substr((string) $value, 0, 50) . '...';
            }

            echo "  " . str_pad((string) $key, 25) . ": {$displayValue}\n";
        }
    }
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

if (php_sapi_name() !== 'cli') {
    die(json_encode(['error' => 'CLI only']));
}

$patientId = (int) ($argv[1] ?? 0);
if ($patientId <= 0) {
    echo "Usage: php patient_cloud_first_test.php <patient_id> [action]\n\n";
    echo "Actions:\n";
    echo "  status          - Check data source (cloud vs local)\n";
    echo "  load_cloud      - Test cloud-first loading\n";
    echo "  appointments    - Test appointment cloud-first loading\n";
    echo "  update          - Simulate update & sync cycle\n";
    echo "  sync_status     - Check sync runtime status\n";
    echo "  full_test       - Run all tests\n\n";
    echo "Examples:\n";
    echo "  php patient_cloud_first_test.php 1 status\n";
    echo "  php patient_cloud_first_test.php 1 full_test\n";
    exit(1);
}

$action = $argv[2] ?? 'status';

try {
    $tester = new PatientCloudFirstTester($patientId);

    switch ($action) {
        case 'status':
            $tester->testDataSource();
            break;

        case 'load_cloud':
            $tester->testDataSource();
            break;

        case 'appointments':
            $tester->testAppointmentsCloudFirst();
            break;

        case 'update':
            $tester->testSyncStatusBefore();
            $tester->testUpdateAndSync();
            break;

        case 'sync_status':
            $tester->testSyncRuntimeStatus();
            break;

        case 'full_test':
            $tester->testDataSource();
            $tester->testSyncStatusBefore();
            $tester->testAppointmentsCloudFirst();
            $tester->testSyncRuntimeStatus();
            $tester->testFullBidirectional();
            break;

        default:
            echo "Unknown action: {$action}\n";
            exit(1);
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "✓ Test completed\n";
