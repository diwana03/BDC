<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

Auth::requireSuperAdmin();
$pdo=Database::connection();
SchemaUpdater::run($pdo);

$permissions=[
 'competitors.view'=>'View competitors',
 'competitors.edit'=>'Edit competitor profiles and photos',
 'transactions.edit'=>'Add and edit competition entries',
 'registrations.manage'=>'Review registrations and profile updates',
 'results.manage'=>'Manage result repository',
 'imports.manage'=>'Run and roll back imports',
 'audit.view'=>'View audit logs',
 'leaderboard.view'=>'View leaderboard administration',
];
$message=''; $error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Csrf::verify($_POST['_csrf']??null)) $error='Invalid security token. Refresh and try again.';
 else {
  $action=(string)($_POST['action']??'');
  try {
   if($action==='create'){
    $name=trim((string)($_POST['full_name']??''));
    $email=strtolower(trim((string)($_POST['email']??'')));
    $password=(string)($_POST['password']??'');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid name and email address.');
    if(strlen($password)<10) throw new RuntimeException('Temporary password must be at least 10 characters.');
    $pdo->beginTransaction();
    $s=$pdo->prepare("INSERT INTO bdc_users(email,password_hash,full_name,role,status,created_at,updated_at) VALUES(:e,:p,:n,'admin','active',NOW(),NOW())");
    $s->execute(['e'=>$email,'p'=>password_hash($password,PASSWORD_DEFAULT),'n'=>$name]);
    $uid=(int)$pdo->lastInsertId();
    $ip=$pdo->prepare('INSERT INTO bdc_user_permissions(user_id,permission_key,allowed) VALUES(:u,:p,1) ON DUPLICATE KEY UPDATE allowed=1');
    foreach((array)($_POST['permissions']??[]) as $key) if(isset($permissions[$key])) $ip->execute(['u'=>$uid,'p'=>$key]);
    $pdo->commit();
    Auth::audit((int)Auth::user()['id'],'admin_user_created',['new_user_id'=>$uid,'email'=>$email],'admin_user',$uid);
    $message='Admin user created successfully.';
   } elseif($action==='update'){
    $uid=(int)($_POST['user_id']??0);
    $s=$pdo->prepare("SELECT id,email,role FROM bdc_users WHERE id=:id AND role='admin'");$s->execute(['id'=>$uid]);$target=$s->fetch();
    if(!$target) throw new RuntimeException('Admin user not found. Super Admin accounts cannot be edited here.');
    $name=trim((string)($_POST['full_name']??''));
    $email=strtolower(trim((string)($_POST['email']??'')));
    $status=in_array($_POST['status']??'active',['active','suspended'],true)?$_POST['status']:'active';
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid name and email address.');
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE bdc_users SET full_name=:n,email=:e,status=:s,updated_at=NOW() WHERE id=:id')->execute(['n'=>$name,'e'=>$email,'s'=>$status,'id'=>$uid]);
    $pdo->prepare('DELETE FROM bdc_user_permissions WHERE user_id=:id')->execute(['id'=>$uid]);
    $ip=$pdo->prepare('INSERT INTO bdc_user_permissions(user_id,permission_key,allowed) VALUES(:u,:p,1)');
    foreach((array)($_POST['permissions']??[]) as $key) if(isset($permissions[$key])) $ip->execute(['u'=>$uid,'p'=>$key]);
    $newPassword=(string)($_POST['new_password']??'');
    if($newPassword!==''){
      if(strlen($newPassword)<10) throw new RuntimeException('New password must be at least 10 characters.');
      $pdo->prepare('UPDATE bdc_users SET password_hash=:p WHERE id=:id')->execute(['p'=>password_hash($newPassword,PASSWORD_DEFAULT),'id'=>$uid]);
    }
    $pdo->commit();
    Auth::audit((int)Auth::user()['id'],'admin_user_updated',['target_email'=>$email,'status'=>$status,'password_reset'=>$newPassword!==''],'admin_user',$uid);
    $message='Admin user updated successfully.';
   }
  } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); $error=$e->getMessage(); }
 }
}

$editId=(int)($_GET['edit']??0);$editing=null;$editingPermissions=[];
if($editId){
 $s=$pdo->prepare("SELECT id,email,full_name,status,last_login_at,created_at FROM bdc_users WHERE id=:id AND role='admin'");$s->execute(['id'=>$editId]);$editing=$s->fetch();
 if($editing){$p=$pdo->prepare('SELECT permission_key FROM bdc_user_permissions WHERE user_id=:id AND allowed=1');$p->execute(['id'=>$editId]);$editingPermissions=$p->fetchAll(PDO::FETCH_COLUMN);}
}
$users=$pdo->query("SELECT id,email,full_name,status,last_login_at,created_at FROM bdc_users WHERE role='admin' ORDER BY full_name")->fetchAll();
$csrf=Csrf::token();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Users | BDC Competitor Dashboard</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?=e(url('public/assets/css/app.css'))?>" rel="stylesheet"></head><body class="bg-light">
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><span class="text-white small">Super Admin only</span></div></nav>
<div class="container py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Admin Users</h1><p class="text-muted mb-0">Only the Super Admin can create or change admin accounts.</p></div><a class="btn btn-outline-dark" href="<?=e(url('admin/'))?>">Dashboard</a></div>
<?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5 mb-3"><?=$editing?'Edit Admin':'Add Admin User'?></h2>
<form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="<?=$editing?'update':'create'?>"><?php if($editing):?><input type="hidden" name="user_id" value="<?=(int)$editing['id']?>"><?php endif;?>
<div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="full_name" required value="<?=e($editing['full_name']??'')?>"></div>
<div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required value="<?=e($editing['email']??'')?>"></div>
<?php if($editing):?><div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?=($editing['status']??'')==='active'?'selected':''?>>Active</option><option value="suspended" <?=($editing['status']??'')==='suspended'?'selected':''?>>Suspended</option></select></div><div class="mb-3"><label class="form-label">Reset password, optional</label><input class="form-control" type="password" name="new_password" minlength="10" autocomplete="new-password"><div class="form-text">Leave blank to keep the current password.</div></div><?php else:?><div class="mb-3"><label class="form-label">Temporary password</label><input class="form-control" type="password" name="password" minlength="10" required autocomplete="new-password"></div><?php endif;?>
<div class="mb-3"><label class="form-label fw-semibold">Permissions</label><?php foreach($permissions as $key=>$label):?><div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="<?=e($key)?>" id="p_<?=e(str_replace('.','_',$key))?>" <?=in_array($key,$editingPermissions,true)?'checked':''?>><label class="form-check-label" for="p_<?=e(str_replace('.','_',$key))?>"><?=e($label)?></label></div><?php endforeach;?></div>
<button class="btn btn-dark"><?=$editing?'Save Changes':'Create Admin'?></button><?php if($editing):?><a class="btn btn-outline-secondary" href="./">Cancel</a><?php endif;?></form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">Assigned Admins</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody><?php if(!$users):?><tr><td colspan="5" class="text-muted">No assigned admins yet.</td></tr><?php endif;?><?php foreach($users as $u):?><tr><td><?=e($u['full_name'])?></td><td><?=e($u['email'])?></td><td><span class="badge <?=$u['status']==='active'?'text-bg-success':'text-bg-secondary'?>"><?=e(ucfirst($u['status']))?></span></td><td><?=e($u['last_login_at']?:'Never')?></td><td><a class="btn btn-sm btn-outline-dark" href="?edit=<?=(int)$u['id']?>">Edit</a></td></tr><?php endforeach;?></tbody></table></div></div></div></div></div>
<div class="alert alert-warning mt-4 mb-0"><strong>Security:</strong> Assigned admins cannot access this page, create other admins, or change their own permissions. Super Admin accounts are not listed or editable here.</div></div></body></html>
