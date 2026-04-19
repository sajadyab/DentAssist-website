# Patient Data Flow Testing - Copy-Paste Commands

Fast reference for testing from VS Code terminal. Copy, paste, run.

---

## Quick Test Suite (Start Here)

### Test 1: Data Source Check (1 minute)
```bash
cd c:\xampp\htdocs\dental_test && php patient_cloud_first_test.php 1 status
```
**Expected:** `✓ CLOUD (Supabase)` or `⚠ LOCAL ONLY`

---

### Test 2: Appointments Cloud-First (1 minute)
```bash
php patient_cloud_first_test.php 1 appointments
```
**Expected:** Cloud appointment count ≥ local count

---

### Test 3: Update & Sync Cycle (5 minutes)
```bash
php patient_cloud_first_test.php 1 update
```
**Shows:** Test data ready to sync

---

### Test 4: All Diagnostics (3 minutes)
```bash
php patient_cloud_first_test.php 1 full_test
```
**Shows:** All 6 tests + scenarios

---

## Monitoring Commands

### Check Sync Status
```bash
mysql -u root dental_clinic_local -e "SELECT sync_status, COUNT(*) as count FROM patients GROUP BY sync_status"
```

### View Recent Syncs
```bash
mysql -u root dental_clinic_local -e "SELECT table_name, local_id, status, attempt_count, last_finished FROM sync_runtime_status WHERE table_name='patients' ORDER BY last_finished DESC LIMIT 5"
```

### Find Failed Syncs
```bash
mysql -u root dental_clinic_local -e "SELECT id, email, sync_error FROM patients WHERE sync_status='failed'"
```

### Monitor Pending Rows
```bash
mysql -u root dental_clinic_local -e "SELECT COUNT(*) as pending FROM patients WHERE sync_status='pending'"
```

---

## Manual Sync Commands

### Sync Local → Cloud
```bash
php sync_to_supabase_fixed.php
```

### Sync Cloud → Local
```bash
php sync_from_supabase.php
```

### Both Directions
```bash
php sync_to_supabase_fixed.php && php sync_from_supabase.php
```

---

## Schema Diagnostics

### Check Missing Columns
```bash
php check_missing_columns.php
```

### Generate Supabase Migrations
```bash
php auto_add_missing_supabase_columns.php
```

---

## Patient Portal Tests

### Test Patient 1
```bash
php patient_cloud_first_test.php 1 status
```

### Test Patient 5
```bash
php patient_cloud_first_test.php 5 status
```

### Test Patient NNN (replace with 3-digit number)
```bash
php patient_cloud_first_test.php NNN status
```

---

## Create Test Patient

### Create and Sync
```bash
mysql -u root dental_clinic_local -e "
  INSERT INTO patients (full_name, email, phone, sync_status) 
  VALUES ('Test Patient', 'test@local', '555-1234', 'pending')
" && php sync_to_supabase_fixed.php
```

### Check Created ID
```bash
mysql -u root dental_clinic_local -e "
  SELECT id, full_name, sync_status FROM patients ORDER BY id DESC LIMIT 1
"
```

---

## Verify Cloud vs Local Data

### Local Count
```bash
mysql -u root dental_clinic_local -e "SELECT COUNT(*) as local_patients FROM patients"
```

### Cloud Count (via SQL file in Supabase)
```sql
-- Paste in Supabase › SQL Editor
SELECT COUNT(*) as cloud_patients FROM patients;
```

### Missing from Cloud
```bash
mysql -u root dental_clinic_local -e "
  SELECT COUNT(*) as not_synced 
  FROM patients 
  WHERE sync_status IN ('pending', 'failed')
"
```

---

## Real-Time Monitoring Loop

### Terminal: Watch Pending Syncs
```bash
watch -n 2 "mysql -u root dental_clinic_local -se 'SELECT COUNT(*) as pending_rows FROM patients WHERE sync_status=\"pending\"'"
```

### Manual Loop (if watch not available)
```bash
for i in {1..10}; do 
  clear
  echo "=== Pending Syncs ==="
  mysql -u root dental_clinic_local -se "SELECT COUNT(*) FROM patients WHERE sync_status='pending'"
  sleep 2
done
```

---

## Quick Verification Steps

### Step-by-Step Verification
```bash
# 1. Check data source
php patient_cloud_first_test.php 1 status

# 2. Check appointments
php patient_cloud_first_test.php 1 appointments

# 3. Update test
php patient_cloud_first_test.php 1 update

# 4. Sync
php sync_to_supabase_fixed.php

# 5. Check status
mysql -u root dental_clinic_local -e "SELECT sync_status, COUNT(*) FROM patients GROUP BY sync_status"

# 6. Pull back
php sync_from_supabase.php
```

---

## Browser Testing

### Patient Portal URL
```
http://localhost/dental_test/patient/
```

### Reset Browser Cache Before Test
```
Ctrl+Shift+Delete    (clear cache)
Ctrl+F5              (hard refresh)
F12                  (DevTools → Network to watch data load)
```

---

## Error Recovery

### Clear Cache & Retry
```bash
php patient_cloud_first_test.php 1 status && echo "Test complete"
```

### Resync Everything
```bash
php sync_to_supabase_fixed.php && php sync_from_supabase.php
```

### Force Resync Patient
```bash
mysql -u root dental_clinic_local -e "UPDATE patients SET sync_status='pending' WHERE id=1" && php sync_to_supabase_fixed.php
```

---

## Logging & Debugging

### View Sync Log (if saved)
```bash
type C:\xampp\logs\sync_to_cloud.log | tail -30
```

### Check MySQL Error Log
```bash
type C:\xampp\mysql\data\error.log | tail -50
```

### Check PHP Errors
```bash
type C:\xampp\apache\logs\error.log | tail -50
```

---

## Test Combinations

### Full Cloud-First Test
```bash
php patient_cloud_first_test.php 1 status && \
php patient_cloud_first_test.php 1 appointments && \
php patient_cloud_first_test.php 1 sync_status
```

### Test All Patients with Status
```bash
for id in 1 2 3 4 5; do 
  echo "=== Patient $id ===" 
  php patient_cloud_first_test.php $id status 2>&1 | grep -E "(CLOUD|LOCAL|NONE|synced|pending)"
done
```

---

## One-Liners for Quick Checks

### Is cloud-first working?
```bash
php patient_cloud_first_test.php 1 status 2>&1 | grep -o "CLOUD\|LOCAL\|NONE"
```

### How many pending?
```bash
mysql -u root dental_clinic_local -se "SELECT COUNT(*) FROM patients WHERE sync_status='pending'"
```

### All synced?
```bash
mysql -u root dental_clinic_local -se "SELECT COUNT(*) FROM patients WHERE sync_status!='synced'"
```

### Latest sync time?
```bash
mysql -u root dental_clinic_local -se "SELECT MAX(last_finished) FROM sync_runtime_status WHERE table_name='patients'"
```

---

## Testing Workflow (Copy All)

```bash
# Open PowerShell in c:\xampp\htdocs\dental_test

# Terminal 1: Monitor
watch -n 1 "mysql -u root dental_clinic_local -se 'SELECT sync_status, COUNT(*) FROM patients GROUP BY sync_status'"

# Terminal 2: Test
php patient_cloud_first_test.php 1 full_test

# Terminal 3: Sync
php sync_to_supabase_fixed.php

# Terminal 4: Verify
mysql -u root dental_clinic_local -e "SELECT * FROM patients WHERE id=1\G"
```

---

## Success Indicators

Each test should show:

### Test 1: Status
```
✓ CLOUD (Supabase)
```

### Test 2: Appointments
```
✓ Retrieved X appointments via cloud-first
```

### Test 3: Sync Status
```
(sync events showing synced)
```

### Monitor Command
```
sync_status | count
pending     | 0
synced      | 10
```

---

## Paste These Complete

### All Diagnostic Tests
```bash
cd c:\xampp\htdocs\dental_test
php patient_cloud_first_test.php 1 full_test
echo "---"
mysql -u root dental_clinic_local -e "SELECT sync_status, COUNT(*) FROM patients GROUP BY sync_status"
echo "---"
php sync_to_supabase_fixed.php
echo "---"
mysql -u root dental_clinic_local -e "SELECT MAX(last_finished) FROM sync_runtime_status"
```

### Quick Validation Loop
```bash
cd c:\xampp\htdocs\dental_test
echo "=== Data Source ===" 
php patient_cloud_first_test.php 1 status | grep -E "Data came|LocalOnly|not found"
echo ""
echo "=== Pending Rows ===" 
mysql -u root dental_clinic_local -se "SELECT COUNT(*) FROM patients WHERE sync_status='pending'"
echo ""
echo "=== Cloud Status ===" 
php sync_to_supabase_fixed.php | tail -5
```

---

## Terminal Shortcuts

### Open New Terminal Tab
```
Ctrl+Shift+`
```

### Split Terminal
```
Ctrl+Shift+5
```

### Clear Screen
```
clear
```

### Exit
```
exit
```

---

## Browser DevTools Check

Open `http://localhost/dental_test/patient/` then:

```javascript
// In Browser Console (F12 › Console):

// Check if cloud calls are happening
console.log('Network tab should show calls to supabase.co');

// If errors appear:
console.error('Check for cloud-first fallback')
```

---

## Fastest Test (30 seconds)

```bash
cd c:\xampp\htdocs\dental_test && php patient_cloud_first_test.php 1 status | grep "CLOUD\|LOCAL"
```

---

## Most Thorough Test (5 minutes)

```bash
cd c:\xampp\htdocs\dental_test && \
php patient_cloud_first_test.php 1 full_test && \
php sync_to_supabase_fixed.php && \
php sync_from_supabase.php && \
mysql -u root dental_clinic_local -e "SELECT sync_status, COUNT(*) FROM patients GROUP BY sync_status"
```

---

## Troubleshooting Quick Fixes

### Not syncing?
```bash
php sync_to_supabase_fixed.php 2>&1 | head -50
```

### Wrong data source?
```bash
php patient_cloud_first_test.php 1 status
```

### Patient missing?
```bash
mysql -u root dental_clinic_local -e "SELECT COUNT(*) FROM patients"
```

### Errors in sync?
```bash
mysql -u root dental_clinic_local -e "SELECT sync_error FROM patients WHERE sync_status='failed' LIMIT 3"
```

---

**Save this file and use it as your testing clipboard!**

Start with: `php patient_cloud_first_test.php 1 full_test` 🚀
