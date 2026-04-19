<?php
require_once __DIR__ . '/supabase_client.php';
require_once __DIR__ . '/includes/config.php';
$s = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);
try {
  $cols = $s->select('information_schema.columns', ['select'=>'column_name','table_schema'=>'eq.public','table_name'=>'eq.users','limit'=>300]);
  echo "users_cols=".count($cols)."\n";
  foreach($cols as $c){echo ($c['column_name']??'')."\n";}
} catch (Throwable $e) { echo 'err_cols: '.$e->getMessage()."\n"; }
try {
  $rows = $s->select('users', ['select'=>'id,local_id,username,email,role,is_active,is_admin','limit'=>5]);
  echo "users_rows=".count($rows)."\n";
  foreach($rows as $r){echo 'id='.$r['id'].' local_id='.($r['local_id']??'').' username='.($r['username']??'')."\n";}
} catch (Throwable $e) { echo 'err_rows: '.$e->getMessage()."\n"; }
?>
