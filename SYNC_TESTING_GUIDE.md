# Testing the Supabase Sync System from VS Code

## Quick Start: Test the Fixed Sync System

### Prerequisites
- XAMPP running (Apache + MySQL)
- Supabase project configured in `includes/config.php`
- Terminal integration in VS Code

---

## Test 1: Check for Missing Columns

### Step 1: Open Terminal
```
Ctrl+` or Terminal → New Terminal
```

### Step 2: Run the Schema Comparison Script
```bash
cd c:\xampp\htdocs\dental_test
php check_missing_columns.php
```

### Expected Output:
```
=== SCHEMA COMPARISON: Local MySQL vs Supabase ===

Table: clinic_arrivals
  Missing columns in Supabase:
    - appointment_time
    - arrived_at
    - checked_in_by

Table: audit_log
  Missing columns in Supabase:
    - ip_address
    - user_agent

...

=== SQL MIGRATION: ADD MISSING COLUMNS ===

-- Table: clinic_arrivals
ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS appointment_time TEXT;
ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS arrived_at TEXT;
...
```

### Step 3: Apply Supabase Migrations

#### Option A: Using SQL Editor (Manual, 2 minutes)
1. Go to https://app.supabase.com → Your Project → SQL Editor
2. Click "New Query"
3. Copy the SQL output from above (or open `supabase_migration_missing_columns.sql`)
4. Paste and execute

#### Option B: Generate Auto-Migration (Recommended, 1 minute)
```bash
php auto_add_missing_supabase_columns.php
```

Output shows SQL to copy-paste, or saves to `supabase_auto_migration.sql`

---

## Test 2: Verify Unique Constraints

After applying the migration, verify the constraints exist in Supabase:

### In Supabase SQL Editor:
```sql
SELECT 
    tablename,
    indexname
FROM pg_indexes
WHERE schemaname = 'public'
  AND indexname LIKE '%local_id%'
ORDER BY tablename;
```

### Expected Output:
```
table_name           | index_name
---------------------|---------------------------
audit_log            | idx_audit_log_local_id_unique
clinic_arrivals      | idx_clinic_arrivals_local_id_unique
appointments         | idx_appointments_local_id_unique
...
```

---

## Test 3: Test the Fixed Sync Script

### Step 1: Create Test Data Locally

Open terminal in VS Code:
```bash
cd c:\xampp\htdocs\dental_test
```

Create a test record in clinic_arrivals:
```bash
php -r "
require_once 'includes/config.php';
\$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);
\$pdo->exec(\"INSERT INTO clinic_arrivals (appointment_time, arrived_at, sync_status) VALUES (NOW(), NOW(), 'pending')\");
echo 'Test record created\n';
"
```

Or use the MySQL CLI directly:
```bash
mysql -u root dental_clinic_local -e "INSERT INTO clinic_arrivals (appointment_time, arrived_at, sync_status) VALUES (NOW(), NOW(), 'pending')"
```

### Step 2: Run the Fixed Sync Script

```bash
php sync_to_supabase_fixed.php
```

### Expected Output:
```
Starting local -> cloud sync (manual SELECT → UPDATE/INSERT)...
Found 5 syncable tables

Table clinic_arrivals: 1 pending/failed rows
  Syncing clinic_arrivals#123...
    → Attempting INSERT...
    ✓ INSERT succeeded
    ✓ Marked as synced in local
✓ clinic_arrivals sync completed

✓ Local → Cloud sync completed
```

### Step 3: Verify in Supabase

In Supabase Dashboard → Your Table → Data:
- Confirm the new row appears in `clinic_arrivals`
- Verify `local_id` is set to the MySQL ID (e.g., 123)
- Verify `sync_status` = "synced"
- Verify all columns were synced correctly

---

## Test 4: Test Error Recovery

### Scenario: Duplicate Key Error Recovery

#### Step 1: Intentionally Create a Duplicate
```bash
php -r "
require_once 'includes/config.php';
require_once 'supabase_client.php';

\$supabase = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);

// Try to insert a record with local_id that already exists
\$supabase->insert('clinic_arrivals', [
    'local_id' => 123,
    'appointment_time' => '2026-04-13 10:00:00',
    'sync_status' => 'pending',
]);
" 2>&1 | head -20
```

#### Step 2: Insert a new MySQL record with same data
```bash
mysql -u root dental_clinic_local -e "
  INSERT INTO clinic_arrivals (id, appointment_time, arrived_at, sync_status) 
  VALUES (123, NOW(), NOW(), 'pending')
"
```

#### Step 3: Run sync and watch error recovery
```bash
php sync_to_supabase_fixed.php
```

### Expected Output:
```
  Syncing clinic_arrivals#123...
    ✓ Found in cloud (cloud id: some-uuid, local_id: 123)
    → Attempting UPDATE...
    ✓ UPDATE succeeded
    ✓ Marked as synced in local
```

---

## Test 5: Monitor Sync State

### Check Sync Status in Local Database

In VS Code terminal or MySQL client:
```bash
mysql -u root dental_clinic_local -e "
  SELECT 
    id, 
    appointment_time, 
    sync_status, 
    sync_error, 
    last_sync_attempt
  FROM clinic_arrivals 
  WHERE sync_status IN ('synced', 'failed', 'pending')
  ORDER BY last_sync_attempt DESC
  LIMIT 10
"
```

### Check Sync Runtime Status

```bash
mysql -u root dental_clinic_local -e "
  SELECT 
    table_name, 
    local_id, 
    status, 
    message, 
    attempt_count,
    last_finished
  FROM sync_runtime_status
  ORDER BY last_finished DESC
  LIMIT 10
"
```

---

## Test 6: Test Missing Column Removal (Auto-Recovery)

### Setup: Create a scenario with unknown columns

```bash
php -r "
require_once 'includes/config.php';

\$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);

// Add a test column to clinic_arrivals that doesn't exist in Supabase
\$pdo->exec('ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS temp_test_col VARCHAR(255)');

// Insert record
\$stmt = \$pdo->prepare('INSERT INTO clinic_arrivals (appointment_time, temp_test_col, sync_status) VALUES (NOW(), ?, \"pending\")');
\$stmt->execute(['test_value']);
echo 'Created record with unknown column\n';
"
```

### Run sync and observe:
```bash
php sync_to_supabase_fixed.php
```

### Expected output:
```
  → Removing missing column: temp_test_col
  ✓ INSERT succeeded
```

The script automatically strips unknown columns and retries.

---

## Test 7: Run Full Bidirectional Sync

### Run both sync directions in sequence:

```bash
echo "Starting two-way sync..."
php sync_to_supabase_fixed.php && echo "" && php sync_from_supabase.php
```

Expected output:
```
Starting local -> cloud sync...
✓ Local → Cloud sync completed

Starting cloud -> local sync...
✓ Cloud → Local sync completed
```

---

## Testing Checklist

- [ ] Schema comparison runs without errors
- [ ] Missing columns detected correctly
- [ ] Supabase migrations applied successfully
- [ ] Unique constraints verify in Supabase
- [ ] Test data inserted and synced to cloud
- [ ] Duplicate key errors are caught and recovered
- [ ] Sync status updated to 'synced' in local DB
- [ ] Unknown columns are stripped automatically
- [ ] Two-way sync completes without errors
- [ ] Existing records are updated (not re-inserted)

---

## Troubleshooting

### Issue: "Table not found in Supabase"
**Solution:** Ensure Supabase tables exist or auto-create them first:

```bash
php sync_from_supabase.php 2>&1 | grep -i error
```

### Issue: "Duplicate key value violates unique constraint '_pkey'"
**Solution:** This is from upsert. Use the fixed script instead:

```bash
php sync_to_supabase_fixed.php  # Uses manual SELECT → UPDATE/INSERT
```

### Issue: "Could not find the 'column_name' column"
**Solution:** This is normal—the script will automatically strip it and retry.
Check VS Code terminal output for `→ Removing missing column: column_name`.

### Issue: Sync runs but no data appears in Supabase
**Solutions:**
1. Check sync_status in local DB: `SELECT * FROM clinic_arrivals WHERE sync_status != 'synced'`
2. Check sync_runtime_status: `SELECT * FROM sync_runtime_status WHERE status = 'failed'`
3. Run with verbose output: `php sync_to_supabase_fixed.php 2>&1 | less`

---

## Debug Tips

### Enable DB Logging
Add to top of sync script:
```php
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
ini_set('log_errors', 'On');
ini_set('error_log', '/tmp/sync_debug.log');
```

### Check Supabase Logs
1. Go to Supabase Dashboard → Logs → Recent Errors
2. Look for any API errors or constraint violations

### Trace a Single Row
```bash
php -r "
require_once 'includes/config.php';
\$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS);

// Find a test record
\$stmt = \$pdo->prepare('SELECT * FROM clinic_arrivals WHERE id = ?');
\$stmt->execute([123]);
\$row = \$stmt->fetch(PDO::FETCH_ASSOC);

echo 'Local record:' . PHP_EOL;
print_r(\$row);
"
```

---

## Performance Notes

- Batch size: 100 rows per sync (adjust `SYNC_BATCH_LIMIT`)
- For large tables: Run multiple times or increase batch size
- Recommended cron interval: Every 5 minutes
- Expected sync time: 1-3 rows per second (depends on payload size)

---

## Next Steps

1. ✓ Run `php check_missing_columns.php` to identify missing columns
2. ✓ Apply Supabase migrations via SQL Editor or auto-migration script
3. ✓ Run `php sync_to_supabase_fixed.php` to sync existing data
4. ✓ Verify data in Supabase Dashboard
5. → Set up Windows Task Scheduler to run `sync_to_supabase_fixed.php` every 5 minutes
6. → Set up Task Scheduler to run `sync_from_supabase.php` every 5 minutes
