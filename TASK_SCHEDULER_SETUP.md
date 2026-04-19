# Windows Task Scheduler Setup for Automatic Sync

This guide sets up automatic bidirectional sync between your local MySQL and Supabase.

## Prerequisites

- PHP installed and accessible from command line
- XAMPP running (MySQL service enabled)
- Sync scripts created: `sync_to_supabase_fixed.php` and `sync_from_supabase.php`

## Step 1: Verify PHP Command Line Access

Open PowerShell and test:
```powershell
php -v
```

If not found, add PHP to PATH or use full path:
```powershell
C:\xampp\php\php.exe -v
```

## Step 2: Create Batch Files for Easy Execution

### Create `sync_local_to_cloud.bat`

Open Notepad and create:
```batch
@echo off
REM Sync local MySQL to Supabase
CD C:\xampp\htdocs\dental_test
C:\xampp\php\php.exe sync_to_supabase_fixed.php >> C:\xampp\logs\sync_to_cloud.log 2>&1
exit /b
```

Save as: `C:\xampp\htdocs\dental_test\sync_local_to_cloud.bat`

### Create `sync_cloud_to_local.bat`

```batch
@echo off
REM Sync Supabase to local MySQL
CD C:\xampp\htdocs\dental_test
C:\xampp\php\php.exe sync_from_supabase.php >> C:\xampp\logs\sync_from_cloud.log 2>&1
exit /b
```

Save as: `C:\xampp\htdocs\dental_test\sync_cloud_to_local.bat`

### Create `run_full_sync.bat` (Both Directions)

```batch
@echo off
REM Run full bidirectional sync
CD C:\xampp\htdocs\dental_test
C:\xampp\php\php.exe sync_to_supabase_fixed.php >> C:\xampp\logs\full_sync.log 2>&1
C:\xampp\php\php.exe sync_from_supabase.php >> C:\xampp\logs\full_sync.log 2>&1
exit /b
```

Save as: `C:\xampp\htdocs\dental_test\run_full_sync.bat`

---

## Step 3: Create Task via Task Scheduler GUI

### Option A: Direct from GUI (Recommended for Windows Home)

1. **Open Task Scheduler**
   - Press `Win + R` and type: `taskschd.msc`
   - Or: Settings → System → About → Advanced system settings → Task Scheduler

2. **Create a New Task**
   - Right-click "Task Scheduler Library" → New Task
   - Name: `Dental Sync - Local to Cloud` (or your preferred name)

3. **General Tab**
   - ☑ Run whether user is logged in or not
   - ☑ Run with highest privileges
   - Configure for: Windows 10

4. **Triggers Tab**
   - Click "New..."
   - Begin the task: **On a schedule**
   - Settings: **Repeat every: 5 minutes**
   - Duration: **Indefinitely**
   - Click OK

5. **Actions Tab**
   - Click "New..."
   - Program/script: `C:\xampp\htdocs\dental_test\sync_local_to_cloud.bat`
   - Start in: `C:\xampp\htdocs\dental_test`
   - Click OK

6. **Conditions Tab**
   - ☑ Start the task only if the computer is on AC power (optional)
   - ☐ Stop if the computer switches to battery power (uncheck)

7. **Settings Tab**
   - ☑ Allow task to be run on demand
   - ☑ Run task as soon as possible after a scheduled start is missed
   - ☐ Do not start a new instance if an instance is already running (KEEP UNCHECKED for reliability)

8. **Click OK** and save

### Repeat for Cloud → Local Sync

Create another task: `Dental Sync - Cloud to Local`
- Use trigger: **5 minutes** (stagger by 2-3 minutes if desired: set start time to 02:30 instead of 00:00)
- Action: `C:\xampp\htdocs\dental_test\sync_cloud_to_local.bat`

---

## Step 4: Create Task via PowerShell (Alternative)

Open **PowerShell as Administrator** and paste:

```powershell
# Task 1: Sync Local to Cloud
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 365)
$action = New-ScheduledTaskAction -Execute "C:\xampp\htdocs\dental_test\sync_local_to_cloud.bat" -WorkingDirectory "C:\xampp\htdocs\dental_test"
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -RunLevel Highest
Register-ScheduledTask -TaskName "Dental-Sync-Local-to-Cloud" -Trigger $trigger -Action $action -Principal $principal -Description "Sync local MySQL to Supabase every 5 minutes" -Force

# Task 2: Sync Cloud to Local (staggered 2 minutes later)
$startTime = (Get-Date).AddMinutes(2)
$trigger2 = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -At $startTime -RepetitionDuration (New-TimeSpan -Days 365)
$action2 = New-ScheduledTaskAction -Execute "C:\xampp\htdocs\dental_test\sync_cloud_to_local.bat" -WorkingDirectory "C:\xampp\htdocs\dental_test"
$principal2 = New-ScheduledTaskPrincipal -UserId "SYSTEM" -RunLevel Highest
Register-ScheduledTask -TaskName "Dental-Sync-Cloud-to-Local" -Trigger $trigger2 -Action $action2 -Principal $principal2 -Description "Sync Supabase to local MySQL every 5 minutes (offset +2min)" -Force
```

---

## Step 5: Verify Tasks Are Running

### Check Task Status in GUI
1. Open Task Scheduler
2. Expand "Task Scheduler Library"
3. Look for "Dental-Sync-*" tasks
4. Check the "Last Run Time" and "Last Run Result"

### Check Log Files
```powershell
# View recent sync logs
Get-Content C:\xampp\logs\sync_to_cloud.log -Tail 50

Get-Content C:\xampp\logs\sync_from_cloud.log -Tail 50

# Monitor in real-time
Get-Content C:\xampp\logs\sync_to_cloud.log -Tail 50 -Wait
```

### Verify Task Manually
Run a single sync now:
```powershell
C:\xampp\htdocs\dental_test\sync_local_to_cloud.bat
```

Check the log file appeared:
```powershell
ls C:\xampp\logs\sync*.log
```

---

## Step 6: Troubleshooting

### Task is not running

**Check:**
1. Task Scheduler is running:
   ```powershell
   Get-Service Schedule | Start-Service  # Start if stopped
   ```

2. Log file permissions (must be writable):
   ```powershell
   ls C:\xampp\logs\
   icacls C:\xampp\logs /grant:r "SYSTEM:(F)"  # Grant SYSTEM full access
   ```

3. Verify task is enabled:
   - Task Scheduler → Right-click task → Properties → Check "Enabled"

### Task runs but produces no output

**Solutions:**
1. Check if MySQL is running:
   ```powershell
   Get-Service MySQL80   # or MySQL57, MySQL56 depending on version
   ```

2. Test PHP execution manually:
   ```cmd
   C:\xampp\php\php.exe -f C:\xampp\htdocs\dental_test\sync_to_supabase_fixed.php
   ```

3. Check file permissions on PHP files:
   ```powershell
   icacls C:\xampp\htdocs\dental_test\*.php /grant:r "SYSTEM:(F)"
   ```

### "Access Denied" errors

**Solution:** Run as SYSTEM with elevated privileges:
1. Task Scheduler → Right-click task → Properties
2. General tab → ☑ Run with highest privileges
3. Click OK

### Database connection fails

**Solutions:**
1. Verify MySQL is started: `net start MySQL80`
2. Test connection:
   ```powershell
   C:\xampp\php\php.exe -r "
     \$pdo = new PDO('mysql:host=localhost;dbname=dental_clinic_local', 'root', '');
     echo 'Connected OK';
   "
   ```
3. Ensure `.bat` file changes to correct directory before running

### Supabase connection fails

**Check:**
1. `includes/config.php` has valid `SUPABASE_URL` and `SUPABASE_KEY`
2. Network connectivity (ping Supabase):
   ```powershell
   Test-NetConnection zfzrviojwinrascpdoyc.supabase.co -Port 443
   ```

---

## Step 7: Fine-Tune Schedule

### Change Sync Frequency

**Every 5 minutes:**
```powershell
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 365)
```

**Every 10 minutes:**
```powershell
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 10) -RepetitionDuration (New-TimeSpan -Days 365)
```

**Every hour:**
```powershell
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration (New-TimeSpan -Days 365)
```

**Once daily at 2 AM:**
```powershell
$trigger = New-ScheduledTaskTrigger -At 02:00am -Daily
```

### Modify Existing Task

```powershell
# Get the task
$task = Get-ScheduledTask -TaskName "Dental-Sync-Local-to-Cloud"

# Modify and save
$task | Set-ScheduledTask -Trigger $newTrigger -Force
```

---

## Step 8: Monitor Sync Health

### Create a Health Check Script

Create `sync_health_check.php`:
```php
<?php
// Check when syncs last ran
$logDir = 'C:\\xampp\\logs\\';
$files = ['sync_to_cloud.log', 'sync_from_cloud.log'];

foreach ($files as $file) {
    $path = $logDir . $file;
    if (file_exists($path)) {
        $mtime = filemtime($path);
        $lastRun = date('Y-m-d H:i:s', $mtime);
        $minutesAgo = floor((time() - $mtime) / 60);
        echo "{$file}: Last run {$minutesAgo} minutes ago ({$lastRun})\n";
    } else {
        echo "{$file}: NOT FOUND\n";
    }
}
?>
```

Run manually:
```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\dental_test\sync_health_check.php
```

### Get Task Statistics

```powershell
# Get all sync tasks with status
Get-ScheduledTask -TaskName "*Dental-Sync*" | Select TaskName, State, LastRunTime, LastTaskResult

# Results: 0 = Success, 1 = Error
```

---

## Step 9: Emergency Controls

### Stop All Sync Tasks

```powershell
Stop-ScheduledTask -TaskName "Dental-Sync-Local-to-Cloud"
Stop-ScheduledTask -TaskName "Dental-Sync-Cloud-to-Local"
```

### Disable Tasks Temporarily

```powershell
Disable-ScheduledTask -TaskName "Dental-Sync-Local-to-Cloud"
Disable-ScheduledTask -TaskName "Dental-Sync-Cloud-to-Local"
```

### Resume Tasks

```powershell
Enable-ScheduledTask -TaskName "Dental-Sync-Local-to-Cloud"
Enable-ScheduledTask -TaskName "Dental-Sync-Cloud-to-Local"
```

### Delete Tasks

```powershell
Unregister-ScheduledTask -TaskName "Dental-Sync-Local-to-Cloud" -Confirm:$false
Unregister-ScheduledTask -TaskName "Dental-Sync-Cloud-to-Local" -Confirm:$false
```

---

## Step 10: Alerts & Notifications

### Optional: Log to Event Viewer

Modify `.bat` script to log errors to Event Viewer:
```batch
@echo off
CD C:\xampp\htdocs\dental_test
C:\xampp\php\php.exe sync_to_supabase_fixed.php
if errorlevel 1 (
    eventcreate /id 1 /type error /source DentalSync /description "Local to Cloud sync failed"
)
exit /b
```

---

## Summary

| Task | Frequency | Min. Resources | Notes |
|------|-----------|----------------|-------|
| Local → Cloud | 5 min | Low | Push pending changes |
| Cloud → Local | 5 min | Low | Pull updates, skip pending |
| Health Check | Hourly | Minimal | Optional monitoring |

**Recommended Setup:**
- ✓ Both sync tasks every 5 minutes
- ✓ Stagger by 2-3 minutes to reduce load
- ✓ Check logs daily
- ✓ Restart tasks weekly or after server updates
