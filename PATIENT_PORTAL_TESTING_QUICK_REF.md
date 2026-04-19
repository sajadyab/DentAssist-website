# Patient Portal Data Flow Testing - Quick Reference

Test that patient-facing pages load data **from Supabase first**, with fallback to local MySQL.

---

## 1-Minute Quick Test  

### Terminal (VS Code)

```bash
cd c:\xampp\htdocs\dental_test

# Check where patient data comes from
php patient_cloud_first_test.php 1 status
```

**Result:**
- ✓ "Data came from SUPABASE" = Working (cloud-first✓)
- ⚠ "Data only in LOCAL" = Not synced yet
- ✗ "Data not found" = Patient missing

---

## Patient Portal Access

### URL
```
http://localhost/dental_test/patient/
```

### Test Credentials
Use any test patient account already in your system, or create one:
```bash
mysql -u root dental_clinic_local -e "
  INSERT INTO users (email, password_hash, full_name, role, sync_status) 
  VALUES ('patient@test.com', SHA2('password123', 256), 'Test Patient', 'patient', 'pending');
  
  INSERT INTO patients (user_id, full_name, email, phone, sync_status)
  VALUES (LAST_INSERT_ID(), 'Test Patient', 'patient@test.com', '555-1234', 'pending');
"

php sync_to_supabase_fixed.php
```

Login with:
- Email: `patient@test.com`
- Password: `password123`

---

## Data Flow Diagram

```
PATIENT PORTAL (Browser)
    ↓
    ├→ Check Cloud First (Supabase)
    │   ├ Success? → Return cloud data ✓
    │   └ Fail? → Fallback to local MySQL
    │
patient_portal_fetch_patient_cloud_first()
    ├→ Call: supabase->select('patients', ['local_id' => $id])
    └→ If fails → $db->fetchOne('SELECT * FROM patients...')

DATA STORAGE (On Update)
    ├→ INSERT/UPDATE local MySQL
    ├→ Set: sync_status = 'pending'
    └→ When scheduled sync runs → Push to Supabase
```

---

## What Each Patient Page Uses

### `patient/index.php` (Dashboard)
```php
$patient = patient_portal_fetch_patient_cloud_first($patientId);
$allAppointments = patient_portal_fetch_appointments_cloud_first($patientId);
```
**Data Sources:** ✓ Cloud-first (patient + appointments)

### `patient/profile.php` (Profile)  
```php
$patient = patient_portal_fetch_patient_cloud_first($patientId);
```
**Data Sources:** ✓ Cloud-first (patient info)

### `patient/subscription.php` (Subscription)
```php
$allAppointments = patient_portal_fetch_appointments_cloud_first($patientId);
```
**Data Sources:** ✓ Cloud-first (appointments for subscription calc)

### Other Pages
```
/patient/bills.php          → Uses cloud-first for patient data
/patient/queue.php          → Uses cloud-first for appointments
/patient/points.php         → Uses cloud-first for patient points
/patient/referrals.php      → Uses cloud-first for patient referrals
```

---

## Testing Cycle (10 minutes)

### Phase 1: Verify Cloud-First Loading

```bash
# Terminal 1
php patient_cloud_first_test.php 1 load_cloud
```

Check output for "Data came from SUPABASE"

---

### Phase 2: Test Patient Portal

```bash
# 1. Open browser
http://localhost/dental_test/patient/

# 2. Login
Email: (your patient email)
Password: (your password)

# 3. Navigate to:
- Dashboard (index.php)
- Profile (profile.php)
- Appointments section

# 4. Verify data loads (check Network tab in F12)
```

**What to look for in DevTools → Network:**
- Requests to `supabase.co` = Cloud loading ✓
- Response from localhost only = Local fallback ⚠

---

### Phase 3: Test Data Updates

```bash
# Terminal 2
php patient_cloud_first_test.php 1 update
```

This will:
1. Update patient data locally
2. Mark as `sync_status = 'pending'`
3. Show you the pending row

**Then in browser:**
1. Go to patient profile: `/patient/profile.php`
2. Edit a field (e.g., phone number)
3. Save

**Check what happens:**
```bash
# Terminal 3 - Watch for pending syncs
mysql -u root dental_clinic_local -e "
  SELECT phone, sync_status FROM patients WHERE id = 1
"
# Should show: sync_status = 'pending'
```

---

### Phase 4: Trigger Sync

```bash
# Terminal 2
php sync_to_supabase_fixed.php
```

**Watch for:**
```
Table patients: 1 pending/failed rows
  Syncing patients#1...
    ✓ UPDATE succeeded
```

---

### Phase 5: Verify in Cloud

```bash
# In Supabase Dashboard → SQL Editor, paste:
SELECT phone, sync_status FROM patients WHERE local_id = 1;
```

Should show:
- `phone` = the new value you entered
- `sync_status` = 'synced'

---

### Phase 6: Check Local After Sync

```bash
mysql -u root dental_clinic_local -e "
  SELECT phone, sync_status FROM patients WHERE id = 1
"
```

Both should be updated

---

## Monitoring Queries

### Monitor Patient Sync Status

```bash
# Show how many patients are pending/synced
mysql -u root dental_clinic_local -e "
  SELECT sync_status, COUNT(*) as count 
  FROM patients 
  GROUP BY sync_status
"
```

Expected output:
```
sync_status | count
------------|-------
synced      | 15
pending     | 2
failed      | 0
```

### Monitor Recent Syncs

```bash
mysql -u root dental_clinic_local -e "
  SELECT 
    local_id,
    table_name,
    status,
    attempt_count,
    last_finished
  FROM sync_runtime_status
  WHERE table_name = 'patients'
  ORDER BY last_finished DESC
  LIMIT 10
"
```

### Find Problem Syncs

```bash
mysql -u root dental_clinic_local -e "
  SELECT 
    local_id,
    status,
    message,
    attempt_count
  FROM sync_runtime_status
  WHERE status = 'failed'
  LIMIT 10
"
```

### Check Sync Errors

```bash
mysql -u root dental_clinic_local -e "
  SELECT 
    id,
    email,
    sync_error,
    last_sync_attempt
  FROM patients
  WHERE sync_status = 'failed'
"
```

---

## Verify Cloud-First is Working

### Method 1: Delete Local, Verify Cloud Data Still Loads

**WARNING: For testing only, not production!**

```bash
# 1. Backup patient data
mysqldump -u root dental_clinic_local patients > /tmp/patients_backup.sql

# 2. Clear local
mysql -u root dental_clinic_local -e "DELETE FROM patients WHERE id = 1"

# 3. Test portal still loads
php patient_cloud_first_test.php 1 status

# Expected: Data comes from Supabase ✓

# 4. Restore
mysql -u root dental_clinic_local < /tmp/patients_backup.sql
```

### Method 2: Compare Timestamps

```bash
# Check if cloud data is fresher than local

# In Supabase SQL Editor:
SELECT local_id, id, updated_at FROM patients WHERE local_id = 1;

# Locally:
mysql -u root dental_clinic_local -e "
  SELECT id, updated_at FROM patients WHERE id = 1
"

# If cloud timestamp is newer → cloud is being used ✓
```

---

## Common Patient Portal Features & Data Source

| Feature | Component | Data Source | URL |
|---------|-----------|------------|-----|
| Dashboard Cards | `patient/index.php` | Cloud-first (patients + appointments) | `/patient/` |
| Profile View/Edit | `patient/profile.php` | Cloud-first (patient data) | `/patient/profile.php` |
| Appointments List | `patient/index.php` | Cloud-first (appointments) | `/patient/` |
| Subscription Info | `patient/subscription.php` | Cloud-first (patient + appointments) | `/patient/subscription.php` |
| Referrals | `patient/referrals.php` | Cloud-first (patient referral code) | `/patient/referrals.php` |
| Points | `patient/points.php` | Cloud-first (patient points) | `/patient/points.php` |
| Queue Status | `patient/queue.php` | Cloud-first (appointments) | `/patient/queue.php` |
| Bills/Invoices | `patient/bills.php` | Cloud-first (patient + invoices) | `/patient/bills.php` |

---

## End-to-End Test Scenario

### Scenario: New patient registers → All data goes cloud → Syncs back

#### Step 1: Register New Patient
```bash
# Via web form or direct SQL
mysql -u root dental_clinic_local -e "
  INSERT INTO users (email, password_hash, full_name, role, sync_status) 
  VALUES ('newpatient@example.com', SHA2('test123', 256), 'New Patient', 'patient', 'pending');
"
```

#### Step 2: Mark as Pending
```bash
mysql -u root dental_clinic_local -e "
  UPDATE users SET sync_status = 'pending' WHERE email = 'newpatient@example.com'
"
```

#### Step 3: Sync to Cloud
```bash
php sync_to_supabase_fixed.php
```

#### Step 4: Verify in Supabase
```sql
-- In Supabase SQL Editor:
SELECT email, full_name, sync_status FROM users WHERE email = 'newpatient@example.com';
```

#### Step 5: Pull Back to Local
```bash
php sync_from_supabase.php
```

#### Step 6: Portal Loads from Cloud
```bash
php patient_cloud_first_test.php <patient_id> status
```

Expected: "Data came from SUPABASE"

---

## Debugging Patient Portal Issues

### Issue: "Patient record not found"

**Check:**
```bash
# 1. Patient exists
mysql -u root dental_clinic_local -e "
  SELECT id, full_name, sync_status FROM patients WHERE full_name LIKE '%Test%'
"

# 2. Patient in cloud
# In Supabase SQL Editor:
SELECT * FROM patients WHERE full_name LIKE '%Test%';

# 3. Sync status
php sync_to_supabase_fixed.php
```

### Issue: Portal Shows Old Data

**Check:**
```bash
# 1. Clear browser cache (Ctrl+Shift+Delete)
# 2. Hard refresh (Ctrl+F5)
# 3. Check if cloud-first working
php patient_cloud_first_test.php 1 status

# 4. Check function source
grep -n "patient_portal_fetch_patient_cloud_first" includes/patient_cloud_repository.php | head -5
```

### Issue: Updates Don't Appear in Portal

**Check:**
```bash
# 1. Local DB updated
mysql -u root dental_clinic_local -e "
  SELECT phone, sync_status FROM patients WHERE id = 1
"

# 2. Sync ran
php sync_to_supabase_fixed.php

# 3. Cloud updated
# In Supabase SQL Editor:
SELECT phone FROM patients WHERE local_id = 1;

# 4. Reload portal
# Browser: Ctrl+F5
```

---

## Performance Checklist

- [ ] Cloud queries complete in <500ms
- [ ] Fallback to local is instant (<50ms)  
- [ ] Patient portal loads in <2 seconds
- [ ] Update takes <1 second to record locally
- [ ] Sync completes 100+ rows in <30 seconds
- [ ] No "duplicate key" errors in logs
- [ ] No timeout errors from Supabase

---

## Verification Checklist

- [ ] Patient portal loads (doesn't show "not found")
- [ ] Dashboard shows appointments
- [ ] Profile page displays patient info
- [ ] Update patient info → saves locally
- [ ] Run `php sync_to_supabase_fixed.php` → pushes to cloud
- [ ] Verify data in Supabase Dashboard
- [ ] Run `php sync_from_supabase.php` → pulls back to local
- [ ] Portal still shows data after refresh
- [ ] Browser DevTools shows Supabase API calls
- [ ] `sync_runtime_status` shows successful records

---

## ✓ Success Criteria

You'll know it's working when:

1. ✓ `php patient_cloud_first_test.php 1 status` shows "CLOUD (Supabase)"
2. ✓ Patient portal loads without errors  
3. ✓ Updates mark local as `sync_status = 'pending'`
4. ✓ `php sync_to_supabase_fixed.php` pushes to Supabase
5. ✓ Data appears in Supabase Dashboard
6. ✓ `sync_status` changes to 'synced' after sync
7. ✓ Portal shows updated data after refresh
8. ✓ No duplicate key or constraint errors

---

## Next Steps

1. Run the quick test: `php patient_cloud_first_test.php 1 status`
2. Access patient portal: `http://localhost/dental_test/patient/`
3. Run full test suite: `php patient_cloud_first_test.php 1 full_test`
4. Set up automatic syncs (see `TASK_SCHEDULER_SETUP.md`)
5. Monitor continuously (use the monitoring queries above)

**Start here:**
```bash
php patient_cloud_first_test.php 1 full_test
```

This runs all 6 tests and shows complete data flow verification. 🚀
