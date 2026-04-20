<?php
require_once __DIR__ . '/supabase_client.php';
$s = new SupabaseAPI('https://zfzrviojwinrascpdoyc.supabase.co','sb_publishable_a0kU3h5n4ytw5N8hTY1PQg_1Cz7ZKoD');
try{
  $rows=$s->select('treatment_steps',['select'=>'id,local_id,plan_id,procedure_name,status','local_id'=>'not.is.null','order'=>'id.desc','limit'=>100]);
  echo "steps rows=".count($rows)."\n";
  foreach($rows as $r){echo 'id='.$r['id'].' local_id='.$r['local_id'].' plan_id='.$r['plan_id'].' proc='.$r['procedure_name'].' status='.$r['status']."\n";}
}catch(Throwable $e){echo 'steps err: '.$e->getMessage()."\n";}
?>
