<?php
require_once __DIR__ . '/supabase_client.php';
$s = new SupabaseAPI('https://zfzrviojwinrascpdoyc.supabase.co','sb_publishable_a0kU3h5n4ytw5N8hTY1PQg_1Cz7ZKoD');
foreach(['subscription_plans','users'] as $t){
  echo "=== $t ===\n";
  try{ $rows=$s->select($t,['select'=>'*','limit'=>1]); echo 'ok rows='.count($rows)."\n"; }
  catch(Throwable $e){ echo 'err: '.$e->getMessage()."\n"; }
}
?>
