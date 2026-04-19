# Patient-Side Cloud-First Testing Guide

Test that patient data loads from **Supabase (Cloud) first**, gets stored there, then syncs back to **local MySQL**.

---

## Quick Test (2 minutes)

### Terminal 1: Start Testing

```bash
cd c:\xampp\htdocs\dental_test

# Check if patient data loads from cloud
php patient_cloud_first_test.php 1 status
```

**What to look for in output:**
```
✓ CLOUD (Supabase)     ← Data came from Supabase (working!)
OR
⚠ LOCAL ONLY           ← Data not synced to Supabase yet
OR
✗ NONE (data not found) ← Patient doesn't exist
```

---

## Complete Testing Workflow (15 minutes)

### Setup: Identify a Test Patient

First, find a patient ID to use for testing:

```bash
# List existing patients
mysql -u root dental_clinic_local -e "
  SELECT id, full_name, email, sync_status 
  FROM patients 
  LIMIT 5
"
```

Pick a patient ID (e.g., `1`, `5`, `10`). We'll use `1` in examples.

---

## Test 1: Verify Data Source (Cloud vs Local)

**Goal:** Confirm patient data loads from Supabase first, not MySQL

### Step 1: Run Status Check

```bash
php patient_cloud_first_test.php 1 status
```

### Expected Output A: Data in Both Places ✓

```
==============================================================================
TEST 1: Data Source Verification (Cloud vs Local)
==============================================================================

→ Fetching patient ID 1 from SUPABASE...
  ✓ Found in Supabase

→ Fetching patient ID 1 from LOCAL MySQL...
  ✓ Found in local MySQL

→ Calling patient_portal_fetch_patient_cloud_first(1)...
  ✓ Data came from SUPABASE (cloud-first working!)

COMPARISON:
-----------
SUPABASE Data:
  id                       : 1
  local_id                 : 1
  full_name                : John Doe
  email                    : john@example.com
  ...

LOCAL MySQL Data:
  id                       : 1
  full_name                : John Doe
  email                    : john@example.com
  ...

Cloud-First Function Result:
  id                       : 1
  local_id                 : 1
  full_name                : John Doe
  ...

==============================================================================
RESULT: Data source = ✓ CLOUD (Supabase)
==============================================================================
```

### Expected Output B: Data Only in Local ⚠

```
→ Fetching patient ID 1 from SUPABASE...
  ✗ Not found in Supabase (local_id filter)

→ Fetching patient ID 1 from LOCAL MySQL...
  ✓ Found in local MySQL

...

RESULT: Data source = LOCAL ONLY
```

**What to do:** Run `php sync_to_supabase_fixed.php` to push to cloud

### Expected Output C: Data Missing ✗

```
RESULT: Data source = NONE (data not found anywhere)
```

**What to do:** Create a test patient first

---

## Test 2: Test Appointments Load from Cloud

**Goal:** Verify appointments load cloud-first for the patient

### Step 1: Run Appointment Test

```bash
php patient_cloud_first_test.php 1 appointments
```

### Expected Output: Mix of Sources

```
==============================================================================
TEST 4: Load Appointments (Cloud-First)
==============================================================================

→ Fetching appointments from SUPABASE...
  ✓ Found 3 appointments in Supabase

→ Fetching appointments from LOCAL MySQL...
  ✓ Found 2 appointments in local MySQL

→ Calling patient_portal_fetch_appointments_cloud_first(1)...
  ✓ Retrieved 3 appointments via cloud-first
```

**Interpretation:**
- If **cloud count ≥ local count**: ✓ Cloud-first working
- If **cloud count < local count**: Some appointments only in local (sync pending)
- If **cloud count = 0 & local count > 0**: Appointments not synced yet

---

## Test 3: Trigger Update & Watch Sync Cycle

**Goal:** Update patient data locally, watch it sync to cloud and back

### Step 1: Prepare Test Data

```bash
php patient_cloud_first_test.php 1 update
```

### Output Shows:

```
Step 1: Prepare test data
  - Test value: TEST_123456_abc123def
  - Test email: patient_xyz789@test.local

Step 2: Update LOCAL database
  ✓ Updated local database
    - notes = 'TEST_123456_abc123def'
    - email = 'patient_xyz789@test.local'
    - sync_status = 'pending'

Step 3: Verify sync_status is 'pending'
  Current sync_status: pending

Step 4: Ready to sync to Supabase
  Pending row:
    id                       : 1
    notes                    : TEST_123456_abc123def
    ...
    sync_status              : pending

  Next: Run 'php sync_to_supabase_fixed.php' to push to cloud
```

### Step 2: Push to Cloud

Open **another terminal** and run:

```bash
cd c:\xampp\htdocs\dental_test
php sync_to_supabase_fixed.php
```

**Watch for:**
```
Table patients: X pending/failed rows
  Syncing patients#1...
    → Attempting UPDATE...
    ✓ UPDATE succeeded
    ✓ Marked as synced in local
```

### Step 3: Verify in Supabase

Go to **Supabase Dashboard → SQL Editor** and run:

```sql
SELECT id, local_id, notes, email, sync_status, updated_at 
FROM patients 
WHERE local_id = 1
LIMIT 1;
```

**Confirm:**
- ✓ `notes` contains the test value
- ✓ `email` is updated
- ✓ `sync_status = 'synced'`
- ✓ `updated_at` is recent

### Step 4: Verify Local Updated After Sync

```bash
mysql -u root dental_clinic_local -e "
  SELECT id, notes, email, sync_status, last_sync_attempt
  FROM patients 
  WHERE id = 1
"
```

**Confirm:**
- ✓ Local fields match cloud
- ✓ `sync_status = 'synced'`

---

## Test 4: Test Full Browser Visit from Patient Side

**Goal:** Access patient portal and watch it load data cloud-first

### Step 1: Open Browser

```
http://localhost/dental_test/patient/
```

### Step 2: Login as Patient

Use test patient credentials

### Step 3: Monitor Data Loading

Open **Browser DevTools** (F12) → **Network** tab

**Look for API calls to:**
- `supabase.co` (cloud - should be first)
- `localhost` (fallback to local if cloud fails)

### Step 4: Check JavaScript Console

```javascript
// In Browser Console, check if there are cloud API errors
// Should see either successful Supabase calls OR fallback to local
```

---

## Test 5: Monitor Sync Events

**Goal:** Track when syncs happen and their results

### View Recent Syncs

```bash
mysql -u root dental_clinic_local -e "
  SELECT 
    id,
    table_name, 
    local_id, 
    status, 
    message, 
    attempt_count,
    last_finished
  FROM sync_runtime_status
  WHERE local_id = 1
  ORDER BY last_finished DESC
  LIMIT 10
"
```

### Expected Output:

```
id | table_name | local_id | status | message           | attempt_count | last_finished
---|------------|----------|--------|-------------------|---------------|-------------------
42 | patients   | 1        | synced | Batch sync...     | 1             | 2026-04-13 10:15:30
41 | patients   | 1        | synced | Batch sync...     | 1             | 2026-04-13 10:10:20
```

---

## Test 6: Run All Diagnostic Tests

**Goal:** Full automated verification

```bash
php patient_cloud_first_test.php 1 full_test
```

**Shows all of:**
- Data source check (cloud vs local)
- Pre-sync status
- Appointment loading
- Sync runtime status  
- Full bidirectional scenario

---

## Test 7: Browser Patient Portal Full Cycle

**SCENARIO:** Patient updates profile → Data syncs to cloud → Data pulls back to local

### Prerequisites
- Patient portal running at: `http://localhost/dental_test/patient/`
- Scheduled sync tasks not running (disable to avoid interference)

### Step 1: Check Initial State

**Terminal 1:**
```bash
php patient_cloud_first_test.php 1 status
```
Note the `sync_status` value

**Supabase Dashboard:**
```sql
SELECT phone, email, sync_status FROM patients WHERE local_id = 1;
```
Note the phone number

### Step 2: Edit Patient Portal (Browser)

1. Navigate to: `http://localhost/dental_test/patient/profile.php` 
2. Edit phone number to: `555-TEST-001`
3. Click Save

**What happens in backend:**
- Form submits to `api/profile.php` or similar
- Code should execute: `UPDATE patients SET phone = '555-TEST-001', sync_status = 'pending'`

### Step 3: Verify Local Marked as Pending

**Terminal 1:**
```bash
mysql -u root dental_clinic_local -e "
  SELECT phone, sync_status FROM patients WHERE id = 1
"
```

Should show:
- `phone = '555-TEST-001'`
- `sync_status = 'pending'`

### Step 4: Run Manual Sync to Cloud

**Terminal 2:**
```bash
php sync_to_supabase_fixed.php
```

Watch for:
```
Table patients: 1 pending/failed rows
  Syncing patients#1...
    ✓ UPDATE succeeded
    ✓ Marked as synced in local
```

### Step 5: Verify in Supabase Dashboard

Go to Supabase SQL Editor:
```sql
SELECT phone, sync_status FROM patients WHERE local_id = 1;
```

Should show:
- `phone = '555-TEST-001'` ← Updated!
- `sync_status = 'synced'`

### Step 6: Run Sync from Cloud (Optional)

```bash
php sync_from_supabase.php
```

Pulls any cloud-side changes back to local

### Step 7: Reload Browser

```
http://localhost/dental_test/patient/profile.php
```

Should show:
- Phone: `555-TEST-001` ← From Supabase!
- Data loaded cloud-first

---

## Test 8: Verify Patient API Endpoints Use Cloud-First

**Goal:** Confirm API responses come from Supabase

### Check Patient API

```bash
# Get patient data via API
curl -s "http://localhost/dental_test/api/get_patient.php?id=1" | jq '.'
```

### Verify it's cloud-first by:

1. **Check source in code** - Look at API file:
```php
// api/get_patient.php should have:
require_once '../includes/patient_cloud_repository.php';

$patient = patient_portal_fetch_patient_cloud_first($patientId);
```

2. **Compare response to cloud** - Run:
```bash
# In Supabase SQL Editor:
SELECT * FROM patients WHERE local_id = 1 LIMIT 1;

# Compare to API response - should match
```

---

## Troubleshooting Common Issues

### Issue 1: "Data source = LOCAL ONLY"

**Problem:** Data is not syncing to Supabase

**Solutions:**
```bash
# 1. Ensure unique constraints exist in Supabase
php auto_add_missing_supabase_columns.php

# 2. Check for sync errors
php sync_to_supabase_fixed.php 2>&1 | head -30

# 3. Verify Supabase credentials
cat includes/config.php | grep SUPABASE
```

### Issue 2: "Data source = NONE"

**Problem:** Patient doesn't exist anywhere

**Solution:**
```bash
# Create test patient
mysql -u root dental_clinic_local -e "
  INSERT INTO patients (full_name, email, phone, sync_status) 
  VALUES ('Test Patient', 'test@example.com', '555-1234', 'pending')
"

# Then sync to cloud
php sync_to_supabase_fixed.php
```

### Issue 3: Appointment Count Mismatch

**Problem:** Cloud has more/fewer appointments than local

**Reason:** Sync lag or failed syncs

**Solution:**
```bash
# Run sync to bring them in sync
php sync_to_supabase_fixed.php
php sync_from_supabase.php

# Then retest
php patient_cloud_first_test.php 1 appointments
```

### Issue 4: Browser Shows Old Data

**Problem:** Patient portal shows stale data

**Root Causes:**
1. Browser cache
2. Cloud-first function falling back to local
3. Appointment query not pulling from cloud

**Solutions:**
```bash
# 1. Hard refresh browser
Ctrl+Shift+Delete (clear cache)
Ctrl+F5 (hard refresh)

# 2. Check if cloud-first is working
php patient_cloud_first_test.php 1 status

# 3. View server error logs
tail -f C:\xampp\apache\logs\error.log
tail -f C:\xampp\mysql\data\*.log
```

---

## Performance Baseline

For reference, typical cloud-first load times:

| Operation | Time | Source |
|-----------|------|--------|
| Load patient (10KB) | 200-500ms | Supabase + network |
| Load 10 appointments | 300-800ms | Supabase + network |
| Update patient | 100-300ms | Local insert into pending queue |
| Sync batch (100 rows) | 5-15 seconds | Supabase REST API |

---

## Monitoring Dashboard (Optional)

Create a quick dashboard to monitor cloud-first behavior:

```bash
# Save as patient_monitor.sh
while true; do
  clear
  echo "=== Patient Cloud-First Monitor ==="
  echo ""
  echo "Patients in cloud:"
  php -r "
    include 'includes/config.php';
    include 'supabase_client.php';
    \$s = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);
    \$rows = \$s->select('patients', ['select' => 'count', 'limit' => 1]);
    echo 'Count: ' . (\$rows ? count(\$rows) : 0);
  "
  
  echo ""
  echo "Pending syncs (local):"
  mysql -u root dental_clinic_local -se "
    SELECT COUNT(*) FROM patients WHERE sync_status = 'pending'
  "
  
  echo ""
  echo "Failed syncs (local):"
  mysql -u root dental_clinic_local -se "
    SELECT COUNT(*) FROM patients WHERE sync_status = 'failed'
  "
  
  sleep 5
done
```

Run with:
```bash
php patient_monitor.sh
```

---

## Step-by-Step Checklist

- [ ] Test 1: Check data source (cloud vs local)
- [ ] Test 2: Verify appointments load from cloud
- [ ] Test 3: Update locally and see it mark as pending
- [ ] Test 4: Run sync and confirm data in cloud
- [ ] Test 5: Check sync_runtime_status table
- [ ] Test 6: Full diagnostic test passes
- [ ] Test 7: Browser patient portal loads data
- [ ] Test 8: API endpoints return cloud data
- [ ] Browser shows updated data after sync
- [ ] Monitor: No errors in log files

---

## Next Steps

Once all tests pass:

1. **Set up Task Scheduler** to run syncs automatically (see TASK_SCHEDULER_SETUP.md)
2. **Monitor production** with sync dashboards
3. **Test offline sync** - disable internet, make changes, re-enable to watch catch-up
4. **Load test** - try 1000+ pending rows to ensure scale
5. **Train staff** on monitoring and troubleshooting

---

## Quick Command Reference

```bash
# Test patient data source
php patient_cloud_first_test.php 1 status

# Test appointments
php patient_cloud_first_test.php 1 appointments

# Simulate update
php patient_cloud_first_test.php 1 update

# Sync to cloud
php sync_to_supabase_fixed.php

# Sync from cloud  
php sync_from_supabase.php

# Check all syncs
php patient_cloud_first_test.php 1 sync_status

# Full test suite
php patient_cloud_first_test.php 1 full_test

# Monitor local pending
mysql -u root dental_clinic_local -e "SELECT sync_status, COUNT(*) FROM patients GROUP BY sync_status"

# Monitor cloud status
# In Supabase SQL Editor:
# SELECT sync_status, COUNT(*) FROM patients GROUP BY sync_status;
```

---

**Ready to test!** Start with `php patient_cloud_first_test.php 1 status` 🚀
