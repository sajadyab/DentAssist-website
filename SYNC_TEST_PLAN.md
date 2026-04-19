# Realtime Sync Test Plan

## Preconditions
- Run local migration: `sql_local_realtime_sync_upgrade.sql`
- Run Supabase migration: `sql_supabase_realtime_sync_upgrade.sql`
- Confirm `SUPABASE_URL` and `SUPABASE_KEY` are configured

## Runtime Visibility
- API snapshot endpoint: `GET /api/sync_status.php`
- Per-table row status: `sync_status` (`pending`, `synced`, `failed`)
- Runtime operation status table: `sync_runtime_status`
  - `status` (`pending`, `in_progress`, `synced`, `failed`)
- Operation log table: `sync_operation_log`

## CRUD Validation Matrix
1. Patients: add, edit, delete
2. Appointments: add, edit, cancel, delete
3. Treatment plans: add, edit, delete
4. Treatment steps: add, edit, status update, delete
5. Inventory: add, edit, transaction, delete
6. Users: add, edit, toggle active/admin, reset password, delete
7. Subscription plans: edit
8. Clinic settings: edit

For every action above, verify:
1. Local change committed
2. `sync_runtime_status` shows `in_progress` then `synced` (or `failed`)
3. Cloud row is created/updated/deleted using `local_id` mapping

## Basic CRUD Procedure
1. Perform one create operation in UI.
2. Call `GET /api/sync_status.php`.
3. Confirm latest runtime row for that entity is `synced`.
4. Check Supabase table row by `local_id`.
5. Repeat with update and delete for same entity.

## Real-Time Behavior
1. Open UI and edit one field.
2. Immediately call `GET /api/sync_status.php`.
3. Confirm status transitions to `in_progress` then `synced`.
4. Confirm Supabase `updated_at` or row content changes without waiting for manual batch sync.

## Stress and Edge Cases
1. Rapid updates: update same row 10 times in <30s.
2. Create then immediately delete same row.
3. Update while prior sync failed, then retry.
4. Confirm no duplicate cloud records for same `local_id`.
5. Confirm `sync_delete_queue` drains to `synced`.

## Offline and Retry
1. Disconnect internet.
2. Perform several creates/updates/deletes.
3. Reconnect internet.
4. Run:
   - `php sync_to_supabase.php`
   - `php sync_from_supabase.php`
5. Confirm `sync_runtime_status` converges to `synced` and `sync_delete_queue` has no stuck `failed` rows.

## Expected Correct Behavior
- No silent failures: every failed operation has message in `sync_runtime_status.message`
- No permanent stuck rows: failed rows become retryable and can recover
- No data mismatch for tested entities between local and cloud after retry cycle
