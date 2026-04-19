# Patient Data Flow - Visual Testing Guide

Complete visual walkthrough of data flowing through your system.

---

## Architecture: Cloud-First with Local Fallback

```
┌─────────────────────────────────────────────────────────────┐
│                    PATIENT PORTAL                           │
│         http://localhost/dental_test/patient/               │
└─────────────────┬───────────────────────────────┬───────────┘
                  │                               │
                  │ Load Patient Data             │
                  ▼                               │
    ┌─────────────────────────────┐              │
    │  Cloud-First Function Call  │              │
    │  patient_portal_fetch_      │              │
    │    patient_cloud_first()    │              │
    └──────────┬────────────────┬─┘              │
               │                │                │
        ┌──────▼──────┐  ┌─────▼──────────┐    │
        │  TRY CLOUD  │  │  IF FAILS:     │    │
        │  ─────────  │  │  FALLBACK      │    │
        │             │  │  ─────────     │    │
        │  Supabase   │  │                │    │
        │  REST API   │  │  Local MySQL   │    │
        │             │  │  (backup)      │    │
        └──────┬──────┘  └─────┬──────────┘    │
               │                │               │
               └────────┬───────┘               │
                        │                       │
                        ▼                       │
              ╔═════════════════╗              │
              ║  Patient Data   ║              │
              ║  (from cloud)   ║              │
              ╚═════════════════╝              │
                        │                       │ Displays
                        │                       │ on page
          ┌─────────────────────────────┐     │
          │ Patient Info Section        │     │
          │ - Name                      │     │
          │ - Email                     │     │
          │ - Phone                     │     │
          │ - Appointments              │     │
          └─────────────────────────────┘ ◄──┘
```

---

## Test Flow: From Input to Cloud to Local

### Complete Cycle with Timestamps

```
TIME    LOCATION           ACTION           DATA STATE
────────────────────────────────────────────────────────────

T=00    Local Database     Patient Edit     local DB: pending
        (Browser Form)     "Phone: 555-NEW"

        ↓ UPDATE into:
        ┌──────────────────┐
        │ Local MySQL      │
        │ id:  1           │
        │ phone: 555-NEW   │
        │ sync_status:     │
        │   'pending'      │ ◄── Ready to push to cloud
        └──────────────────┘

────────────────────────────────────────────────────────────

T=05    Terminal 2:        Run Sync Script
        php sync_to_       Select from local:
        supabase_fixed.php sync_status='pending'
                           ↓
                           Insert/Update
                           to Supabase

        ┌──────────────────┐
        │ Supabase         │
        │ id:  UUID        │
        │ local_id: 1      │
        │ phone: 555-NEW   │
        │ sync_status:     │
        │   'synced'       │ ◄── Updated in cloud!
        └──────────────────┘

────────────────────────────────────────────────────────────

T=10    Local Database     Mark as synced
        UPDATE statement   (after cloud success)

        ┌──────────────────┐
        │ Local MySQL      │
        │ id:  1           │
        │ phone: 555-NEW   │
        │ sync_status:     │
        │   'synced'       │ ◄── Back in sync!
        │ last_sync:       │
        │  T=10            │
        └──────────────────┘

────────────────────────────────────────────────────────────

T=15    Portal Reload:     Fetch Patient    Cloud-first function
        /patient/profile   (Browser F5)     1. Try Supabase
                                            2. Get: 555-NEW
                                            3. Return data

        ┌──────────────────┐
        │ Browser Display  │
        │ Phone: 555-NEW   │ ◄── From Supabase!
        │ [Updated!]       │
        └──────────────────┘
```

---

## Step-by-Step Testing Walkthrough

### Setup Phase

```bash
# Terminal 1: Get a test patient ID
cd c:\xampp\htdocs\dental_test

mysql -u root dental_clinic_local -e "
  SELECT id, full_name, email 
  FROM patients 
  LIMIT 5
"

# Output:
# id | full_name             | email
# 1  | John Smith            | john@example.com
# 5  | Sarah Johnson         | sarah@clinic.com

# Choose one (we'll use ID=1)
```

### Test Phase 1: Verify Data Source

```bash
# Terminal 1: Check where patient 1 loads from
php patient_cloud_first_test.php 1 status

# Watch for:
# → Fetching patient ID 1 from SUPABASE...
#   ✓ Found in Supabase
#
# → Calling patient_portal_fetch_patient_cloud_first(1)...
#   ✓ Data came from SUPABASE (cloud-first working!)
#
# ✓ CLOUD (Supabase)  ◄── SUCCESS: Data is from cloud!
```

### Test Phase 2: Update in Browser

```bash
# Terminal 1: Note the current phone number
mysql -u root dental_clinic_local -e "
  SELECT id, phone, sync_status 
  FROM patients 
  WHERE id = 1
"
# Output: phone = "+1-555-1234", sync_status = "synced"

# Browser: Open http://localhost/dental_test/patient/profile.php
# Edit: Change phone number to "+1-555-CLOUD"
# Save: Click Save button

# This triggers: api/profile.php or similar
# Which executes: UPDATE patients SET phone='...', sync_status='pending'
```

### Test Phase 3: Verify Marked as Pending

```bash
# Terminal 1: Check local database
mysql -u root dental_clinic_local -e "
  SELECT id, phone, sync_status 
  FROM patients 
  WHERE id = 1
"

# Output:
# id | phone           | sync_status
# 1  | +1-555-CLOUD    | pending  ◄── Marked for sync!
```

### Test Phase 4: Push to Cloud

```bash
# Terminal 2 (new): Run sync script
php sync_to_supabase_fixed.php

# Watch output:
# Found 5 syncable tables
#
# Table patients: 1 pending/failed rows
#   Syncing patients#1...
#     → Attempting UPDATE...
#     ✓ UPDATE succeeded
#     ✓ Marked as synced in local
#
# ✓ Local → Cloud sync completed  ◄── SUCCESS: Pushed to cloud!
```

### Test Phase 5: Verify in Supabase

```bash
# Terminal 1: Check Supabase Dashboard
# Or use Supabase SQL Editor, paste:
SELECT id, local_id, phone, sync_status, updated_at 
FROM patients 
WHERE local_id = 1
LIMIT 1;

# Output:
# id                                  | local_id | phone         | sync_status | updated_at
# 550e8400-e29b-41d4-a716-446655440000 | 1        | +1-555-CLOUD  | synced      | 2026-04-13 10:15:30

# ✓ Phone updated in cloud!
```

### Test Phase 6: Verify Local Updated After Sync

```bash
# Terminal 1: Check local database
mysql -u root dental_clinic_local -e "
  SELECT id, phone, sync_status, last_sync_attempt 
  FROM patients 
  WHERE id = 1
"

# Output:
# id | phone           | sync_status | last_sync_attempt
# 1  | +1-555-CLOUD    | synced      | 2026-04-13 10:15:30

# ✓ Local marked as synced!
```

### Test Phase 7: Portal Loads Updated Data

```bash
# Browser: Hard refresh the profile page
# Ctrl+F5

# Or Terminal: Test cloud-first function
php patient_cloud_first_test.php 1 status | grep -A 5 "Cloud-First Function Result"

# Should show:
# phone: +1-555-CLOUD  ◄── From Supabase!

# ✓ Portal displays updated data from cloud!
```

---

## Complete Test Cycle Commands

Copy and paste this entire block into Terminal:

```bash
cd c:\xampp\htdocs\dental_test

echo "==========================================="
echo "STEP 1: Check initial data source"
echo "==========================================="
php patient_cloud_first_test.php 1 status | grep -E "CLOUD|LOCAL|NONE"

echo ""
echo "==========================================="
echo "STEP 2: Simulate in local database"
echo "==========================================="
echo "Updating patient 1 phone..."
mysql -u root dental_clinic_local -e "
  UPDATE patients SET phone=CONCAT('TEST-', TIME(NOW())), sync_status='pending' WHERE id=1
"

echo "Verifying marked as pending..."
mysql -u root dental_clinic_local -e "
  SELECT phone, sync_status FROM patients WHERE id=1
"

echo ""
echo "==========================================="
echo "STEP 3: Sync to cloud"
echo "==========================================="
php sync_to_supabase_fixed.php | tail -10

echo ""
echo "==========================================="
echo "STEP 4: Verify in local after sync"
echo "==========================================="
mysql -u root dental_clinic_local -e "
  SELECT phone, sync_status, last_sync_attempt FROM patients WHERE id=1
"

echo ""
echo "==========================================="
echo "STEP 5: Load from cloud"
echo "==========================================="
php patient_cloud_first_test.php 1 status | grep -A 10 "Cloud-First Function Result" | head -5

echo ""
echo "=== TEST COMPLETE ==="
```

---

## Monitoring Snapshots During Test

### Snapshot T=00 (Before Sync)
```
┌─ Local MySQL ──────────┐  ┌─ Supabase ─────────┐
│ id: 1                  │  │ id: UUID           │
│ phone: 555-TEST-00     │  │ local_id: 1        │
│ sync_status: pending   │  │ phone: 555-OLD     │
│ last_sync: T-60        │  │ sync_status:       │
│                        │  │   synced           │
└────────────────────────┘  └────────────────────┘
         ↓ OUT OF SYNC ↑
```

### Snapshot T=05 (After Sync)
```
┌─ Local MySQL ──────────┐  ┌─ Supabase ─────────┐
│ id: 1                  │  │ id: UUID           │
│ phone: 555-TEST-00     │  │ local_id: 1        │
│ sync_status: synced    │  │ phone: 555-TEST-00 │
│ last_sync: T=5         │  │ sync_status:       │
│                        │  │   synced           │
└────────────────────────┘  └────────────────────┘
         ✓ IN SYNC ✓
```

### Snapshot T=10 (After Portal Reload)
```
Data flows:
    Browser (Portal)
            ↓
    Cloud-First Function
            ↓
    Try Supabase (attempt 1)
            ↓
    ✓ Found in cloud!
            ↓
    Return cloud data: phone: 555-TEST-00
            ↓
    Display to patient
```

---

## Visual Success Indicators

### Test 1: Data Source ✓
```
→ Fetching patient ID 1 from SUPABASE...
  ✓ Found in Supabase                          ◄── Green!

RESULT: Data source = ✓ CLOUD (Supabase)       ◄── GREEN!
```

### Test 2: Sync Script ✓
```
Table patients: 1 pending/failed rows
  Syncing patients#1...
    → Attempting UPDATE...
    ✓ UPDATE succeeded                         ◄── Green!
    ✓ Marked as synced in local

✓ Local → Cloud sync completed                 ◄── GREEN!
```

### Test 3: Cloud Updated ✓
```
Supabase Dashboard:
SELECT * FROM patients WHERE local_id = 1:

sync_status = 'synced'                         ◄── GREEN!
phone = (updated value)                        ◄── GREEN!
updated_at = (recent)                          ◄── GREEN!
```

### Test 4: Portal Shows Cloud Data ✓
```
/patient/profile.php displays:

Phone: (value from Supabase)                   ◄── GREEN!
[Data loaded from cloud]                       ◄── GREEN!
```

---

## Failure Indicators (Red Flags)

### Red Flag 1: Wrong Data Source
```
RESULT: Data source = LOCAL ONLY
```
→ Supabase not accessible or synced yet

### Red Flag 2: Sync Failed
```
✗ FAILED: {error message}
```
→ Check Supabase connection, column existence

### Red Flag 3: Duplicate Key Error
```
✗ FAILED: duplicate key value violates unique constraint 'patients_pkey'
```
→ Use `sync_to_supabase_fixed.php` (not old upsert)

### Red Flag 4: Data Doesn't Match
```
SUPABASE phone: 555-OLD
LOCAL phone: 555-NEW
```
→ Sync hasn't run yet, or failed silently

---

## Expected Timings

```
Action                          Expected Time   Status
────────────────────────────────────────────────────────
Data Source Check               0-2 sec         ✓
Appointment Load (10 items)     0.5-1 sec       ✓
Update Local Record             <0.1 sec        ✓
Sync (1 row)                    0.5-2 sec       ✓
Sync (100 rows)                 5-15 sec        ✓
Verification in Supabase        1-2 sec         ✓
Portal Reload & Display         2-5 sec         ✓
────────────────────────────────────────────────────────
Total Cycle Time                8-30 sec        ✓
```

---

## Multi-Terminal View (Advanced)

Set up 4 terminals for simultaneous monitoring:

```
┌─────────────────┬─────────────────┬─────────────────┐
│  Terminal 1     │  Terminal 2     │  Terminal 3     │
│ Test Commands   │ Monitor Pending │ Logs            │
├─────────────────┼─────────────────┼─────────────────┤
│ php patient_... │ watch mysql ... │ tail -f logs/.. │
│ Then observe    │ Shows count     │ Real-time       │
│                 │ decreasing      │ errors          │
└─────────────────┴─────────────────┴─────────────────┘
```

**Terminal 1:**
```bash
php patient_cloud_first_test.php 1 status
```

**Terminal 2** (new tab):
```bash
watch -n 1 "mysql -u root dental_clinic_local -se 'SELECT COUNT(*) as pending FROM patients WHERE sync_status=\"pending\"'"
```

**Terminal 3** (new tab):
```bash
# Run sync and watch progress
php sync_to_supabase_fixed.php
```

---

## Success Checklist

- [ ] Test 1: Data source = "CLOUD (Supabase)"
- [ ] Test 2: Appointment count ≥ local count
- [ ] Test 3: Update marked locally as "pending"
- [ ] Test 4: Sync script shows "UPDATE succeeded"
- [ ] Test 5: Supabase shows updated data
- [ ] Test 6: Local marked as "synced" after sync
- [ ] Test 7: Portal loads and displays cloud data
- [ ] Test 8: No errors in any terminal
- [ ] Test 9: Timings within expected ranges
- [ ] Test 10: Data stays in sync after reload

**All green?** ✓ Cloud-first is working! 🎉

---

## Next Steps After Success

1. **Automate syncs** → Set up Task Scheduler
2. **Monitor production** → Use monitoring queries
3. **Load test** → Sync 1000+ rows
4. **Stress test** → Concurrent updates
5. **Failover test** → Disable Supabase, verify fallback
6. **Recovery test** → Re-enable Supabase, catch up syncs

---

## Reference: File Locations

```
Test Script:           patient_cloud_first_test.php
Sync Script:           sync_to_supabase_fixed.php
Cloud-First Functions: includes/patient_cloud_repository.php
Config:                includes/config.php
Patient Portal:        patient/*.php
```

---

**Start here:** `php patient_cloud_first_test.php 1 full_test` 🚀
