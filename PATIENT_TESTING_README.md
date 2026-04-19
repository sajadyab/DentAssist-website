# Patient Cloud-First Testing - Complete Package

Everything you need to test that patient data loads from **Supabase (cloud) first**, gets stored there, and syncs back to **local MySQL**.

---

## 📋 Files Created

### Testing Scripts
1. **`patient_cloud_first_test.php`** (PHP CLI)
   - Automated testing of data flow
   - 6 different test modes
   - Compare cloud vs local data

### Testing Guides
2. **`PATIENT_CLOUD_FIRST_TESTING.md`** (Comprehensive)
   - 8 complete test scenarios
   - Step-by-step walkthroughs
   - Troubleshooting guide
   
3. **`PATIENT_PORTAL_TESTING_QUICK_REF.md`** (Quick Reference)
   - One-page summary
   - Copy-paste URL
   - Verification checklist

4. **`PATIENT_TESTING_CHEATSHEET.md`** (Copy-Paste)
   - Commands for quick testing
   - Terminal recipes
   - Error recovery

5. **`PATIENT_DATA_FLOW_VISUAL.md`** (Visual Guide)
   - ASCII diagrams of data flow
   - Timeline of sync cycle
   - Multi-terminal setup guide

---

## 🚀 Start with This (2 minutes)

### Terminal: Copy & Paste

```bash
cd c:\xampp\htdocs\dental_test
php patient_cloud_first_test.php 1 full_test
```

**Expected Result:**
```
✓ CLOUD (Supabase)  ← Data loads from cloud!
✓ X appointments found in Supabase
✓ Recent sync events recorded
```

---

## ⚡ Quick Commands Reference

### Test 1: Check Data Source (1 minute)
```bash
php patient_cloud_first_test.php 1 status
```
Shows if data comes from Supabase (cloud) or MySQL (local)

### Test 2: Test Appointments (1 minute)
```bash
php patient_cloud_first_test.php 1 appointments
```
Verifies appointment cloud-first loading

### Test 3: Simulate Update Cycle (5 minutes)
```bash
php patient_cloud_first_test.php 1 update
php sync_to_supabase_fixed.php
```
Updates patient data → Syncs to cloud → Verify in Supabase

### Test 4: Full Diagnostic (3 minutes)
```bash
php patient_cloud_first_test.php 1 full_test
```
Runs all 6 tests + detailed output

---

## 📊 What Gets Tested

| Test | What | Tests | Expected |
|------|------|-------|----------|
| **1. Data Source** | Patient load | Cloud vs Local | ✓ CLOUD |
| **2. Appointments** | Appointment load | Cloud vs Local | ✓ Cloud count ≥ local |
| **3. Update Cycle** | Update → Sync | Local marking | ✓ sync_status='pending' |
| **4. Sync Runtime** | Sync tracking | Database logs | ✓ Recent synced events |
| **5. Full Bidirectional** | Complete flow | All systems | ✓ All steps work |
| **6. Browser Portal** | Patient UI | Live page | ✓ Data displays |

---

## 🔍 Data Flow Tested

```
Patient Update (Browser)
    ↓
Local DB (sync_status = 'pending')
    ↓
php sync_to_supabase_fixed.php
    ↓
Supabase Update
    ↓
Local DB (sync_status = 'synced')
    ↓
Portal Reload
    ↓
Cloud-First Function
    ↓
Load from Supabase
    ↓
Display to Patient ✓
```

---

## 🎯 Success Indicators

### Green (Working)
```
✓ CLOUD (Supabase)
✓ Found in Supabase
✓ UPDATE succeeded
✓ Recent sync events
✓ Data came from SUPABASE
```

### Yellow (Needs Attention)
```
⚠ LOCAL ONLY
⚠ Not found in Supabase
⚠ Empty in Supabase
```

### Red (Broken)
```
✗ Data not found anywhere
✗ FAILED: duplicate key
✗ Connection timeout
```

---

## 📝 Test Plan for CI/CD

Run these in order for complete verification:

```bash
# 1. Schema check
php check_missing_columns.php

# 2. Data source verification
php patient_cloud_first_test.php 1 status

# 3. Appointments cloud-first
php patient_cloud_first_test.php 1 appointments

# 4. Update and sync cycle
php patient_cloud_first_test.php 1 update
php sync_to_supabase_fixed.php

# 5. Full diagnostic
php patient_cloud_first_test.php 1 full_test

# 6. Verify final state
mysql -u root dental_clinic_local -e "
  SELECT sync_status, COUNT(*) 
  FROM patients 
  GROUP BY sync_status
"
```

---

## 💡 Using Alongside Browser

### Setup Multi-Terminal

1. **Terminal 1** (Main): Testing & Monitoring
   ```bash
   php patient_cloud_first_test.php 1 status
   ```

2. **Terminal 2** (Sync): Run Sync Operations
   ```bash
   php sync_to_supabase_fixed.php
   ```

3. **Terminal 3** (Monitor): Watch Changes
   ```bash
   watch -n 1 "mysql -u root dental_clinic_local -se 'SELECT COUNT(*) FROM patients WHERE sync_status=\"pending\"'"
   ```

4. **Browser**: Patient Portal
   ```
   http://localhost/dental_test/patient/
   ```

---

## 🔧 Common Test Scenarios

### Scenario 1: Verify Cloud-First Works
```bash
php patient_cloud_first_test.php 1 status
# Output: "✓ CLOUD (Supabase)"
```

### Scenario 2: Test Complete Sync Cycle
```bash
php patient_cloud_first_test.php 1 full_test
```

### Scenario 3: Manual Update → Sync → Verify
```bash
# 1. Update locally
mysql -u root dental_clinic_local -e "UPDATE patients SET notes='TEST' WHERE id=1; UPDATE patients SET sync_status='pending' WHERE id=1"

# 2. Check pending
mysql -u root dental_clinic_local -e "SELECT sync_status, COUNT(*) FROM patients GROUP BY sync_status"

# 3. Sync to cloud
php sync_to_supabase_fixed.php

# 4. Verify synced
mysql -u root dental_clinic_local -e "SELECT sync_status FROM patients WHERE id=1"
```

### Scenario 4: Test Failed Sync Recovery
```bash
# 1. Force a sync error
mysql -u root dental_clinic_local -e "UPDATE patients SET sync_status='failed', sync_error='Test error' WHERE id=1"

# 2. Check failed status
mysql -u root dental_clinic_local -e "SELECT sync_status, sync_error FROM patients WHERE id=1"

# 3. Reset to pending (retry)
mysql -u root dental_clinic_local -e "UPDATE patients SET sync_status='pending' WHERE id=1"

# 4. Run sync
php sync_to_supabase_fixed.php

# 5. Verify recovered
php patient_cloud_first_test.php 1 status
```

---

## 📊 Monitoring Dashboard

View key metrics with these queries:

```bash
# Quick status
mysql -u root dental_clinic_local -e "
  SELECT 
    'Patients' as metric,
    COUNT(*) as total
  FROM patients
  UNION ALL
  SELECT 
    'Pending Sync',
    COUNT(*)
  FROM patients
  WHERE sync_status='pending'
  UNION ALL
  SELECT 
    'Failed Sync',
    COUNT(*)
  FROM patients
  WHERE sync_status='failed'
"

# Recent syncs
mysql -u root dental_clinic_local -e "
  SELECT 
    table_name,
    COUNT(*) as sync_count,
    MAX(last_finished) as latest
  FROM sync_runtime_status
  WHERE status='synced'
  GROUP BY table_name
  ORDER BY latest DESC
"
```

---

## ✅ Verification Checklist

Use this to ensure everything is working:

- [ ] `php patient_cloud_first_test.php 1 status` shows "CLOUD"
- [ ] Patient portal loads without errors
- [ ] Can edit patient profile in browser
- [ ] Updated data marks as "pending" in local DB
- [ ] `php sync_to_supabase_fixed.php` completes successfully
- [ ] Data appears in Supabase Dashboard
- [ ] Local marked as "synced" after sync
- [ ] Portal reloads and shows updated data
- [ ] No "duplicate key" errors in logs
- [ ] Sync events recorded in `sync_runtime_status` table

---

## 🐛 Debugging

If a test fails, check these files:

1. **Check cloud connectivity**
   ```bash
   php -r "
   require_once 'includes/config.php';
   require_once 'supabase_client.php';
   \$s = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);
   \$result = \$s->select('patients', ['limit' => 1]);
   echo 'Cloud OK: ' . count(\$result) . ' patients';
   "
   ```

2. **Check local DB**
   ```bash
   mysql -u root dental_clinic_local -e "SELECT COUNT(*) FROM patients"
   ```

3. **Check sync function**
   ```bash
   php patient_cloud_first_test.php 1 status 2>&1 | head -20
   ```

4. **Check logs**
   ```bash
   tail -50 C:\xampp\apache\logs\error.log
   tail -50 C:\xampp\mysql\data\*.log
   ```

---

## 📚 File References

### Quick Start Files
- `PATIENT_TESTING_CHEATSHEET.md` - Copy-paste commands
- `PATIENT_PORTAL_TESTING_QUICK_REF.md` - One-pager

### Detailed Guides  
- `PATIENT_CLOUD_FIRST_TESTING.md` - 8 test scenarios with explanations
- `PATIENT_DATA_FLOW_VISUAL.md` - Visual diagrams and timelines

### Automated Tests
- `patient_cloud_first_test.php` - CLI test tool with 6 modes

### Related Files
- `sync_to_supabase_fixed.php` - Sync script (fixed version)
- `sync_from_supabase.php` - Reverse sync
- `includes/patient_cloud_repository.php` - Cloud-first functions

---

## 🎓 How to Learn the System

### 5-Minute Overview
1. Read: `PATIENT_PORTAL_TESTING_QUICK_REF.md`
2. Run: `php patient_cloud_first_test.php 1 status`

### 30-Minute Deep Dive
1. Read: `PATIENT_DATA_FLOW_VISUAL.md` (diagrams)
2. Run: Step-by-step cycle from "Step-by-Step Testing Walkthrough"
3. Verify: Success at each step

### Full Mastery (1+ hours)
1. Read: `PATIENT_CLOUD_FIRST_TESTING.md` (all 8 scenarios)
2. Run: Each test scenario
3. Create: Your own test scenarios
4. Monitor: Using the dashboard

---

## 🚀 Running All Tests

### Quick (1 minute)
```bash
php patient_cloud_first_test.php 1 status
```

### Complete (5 minutes)
```bash
php patient_cloud_first_test.php 1 full_test
```

### Full Cycle (10 minutes)
```bash
# Check source
php patient_cloud_first_test.php 1 status

# Sync to cloud
php sync_to_supabase_fixed.php

# Verify results
mysql -u root dental_clinic_local -e "SELECT sync_status, COUNT(*) FROM patients GROUP BY sync_status"

# Load from cloud
php patient_cloud_first_test.php 1 status
```

---

## 📞 Support Commands

Reset everything:
```bash
# Reset to clean state (backup first!)
mysql -u root dental_clinic_local -e "UPDATE patients SET sync_status='pending'"
php sync_to_supabase_fixed.php
php sync_from_supabase.php
```

Check health:
```bash
php patient_cloud_first_test.php 1 full_test
```

Debug:
```bash
php patient_cloud_first_test.php 1 status 2>&1 | grep -E "Error|Failed|CLOUD|LOCAL"
```

---

## 🎉 Success Condition

**You'll know it's working when:**

✓ `php patient_cloud_first_test.php 1 status` shows "CLOUD (Supabase)"  
✓ Patient portal loads data without errors  
✓ Updates appear in Supabase after sync  
✓ Local stays in sync with cloud  
✓ Portal reloads show updated cloud data  
✓ No duplicate key or constraint errors  

---

## Next Steps

1. **Immediate:** Run `php patient_cloud_first_test.php 1 full_test`
2. **Today:** Test the complete patient portal
3. **This week:** Set up automated syncs (Task Scheduler)
4. **Ongoing:** Monitor with the dashboard queries

---

**Ready? Let's go!** 🚀

```bash
php patient_cloud_first_test.php 1 full_test
```

Open `PATIENT_TESTING_CHEATSHEET.md` to copy-paste quick commands.
