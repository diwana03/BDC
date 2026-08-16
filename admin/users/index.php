<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

Auth::requireSuperAdmin();
$pdo=Database::connection();


$permissions=[
 'competitors.view'=>'View competitors',
 'competitors.edit'=>'Edit competitor profiles and photos',
 'judges.view'=>'View judge database',
 'judges.edit'=>'Edit judge profiles and photos',
 'transactions.edit'=>'Add and edit competition entries',
 'points.adjust.request'=>'Request missing event points for approval',
 'registrations.manage'=>'Review registrations and profile updates',
 'results.manage'=>'Manage result repository',
 'imports.manage'=>'Run and roll back imports',
 'audit.view'=>'View audit logs',
 'leaderboard.view'=>'View leaderboard administration',
];
$message=''; $error='';
$managedRoles=['admin'=>'Admin','scorer'=>'Scorer','master_scorer'=>'Master Scorer','super_admin'=>'Super Admin'];

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
    $role=(string)($_POST['role']??'admin');if(!isset($managedRoles[$role]))$role='admin';
    if($role==='super_admin'&&(int)$pdo->query("SELECT COUNT(*) FROM bdc_users WHERE role='super_admin' AND status='active'")->fetchColumn()>=3)throw new RuntimeException('Maximum 3 active Super Admin accounts are allowed.');
    $pdo->beginTransaction();
    $s=$pdo->prepare("INSERT INTO bdc_users(email,password_hash,full_name,role,status,created_at,updated_at) VALUES(:e,:p,:n,:r,'active',NOW(),NOW())");
    $s->execute(['e'=>$email,'p'=>password_hash($password,PASSWORD_DEFAULT),'n'=>$name,'r'=>$role]);
    $uid=(int)$pdo->lastInsertId();
    $ip=$pdo->prepare('INSERT INTO bdc_user_permissions(user_id,permission_key,allowed) VALUES(:u,:p,1) ON DUPLICATE KEY UPDATE allowed=1');
    foreach((array)($_POST['permissions']??[]) as $key) if(isset($permissions[$key])) $ip->execute(['u'=>$uid,'p'=>$key]);
    $pdo->commit();
    Auth::audit((int)Auth::user()['id'],'user_role_created',['new_user_id'=>$uid,'email'=>$email,'role'=>$role],'admin_user',$uid);
    $message=$managedRoles[$role].' user created successfully.';
   } elseif($action==='update'){
    $uid=(int)($_POST['user_id']??0);
    $s=$pdo->prepare("SELECT id,email,role,status FROM bdc_users WHERE id=:id AND role IN('admin','scorer','master_scorer','super_admin')");$s->execute(['id'=>$uid]);$target=$s->fetch();
    if(!$target) throw new RuntimeException('User not found.');
    $name=trim((string)($_POST['full_name']??''));
    $email=strtolower(trim((string)($_POST['email']??'')));
    $status=in_array($_POST['status']??'active',['active','suspended'],true)?$_POST['status']:'active';
    $role=(string)($_POST['role']??$target['role']);if(!isset($managedRoles[$role]))throw new RuntimeException('Invalid role.');
    $activeSuper=(int)$pdo->query("SELECT COUNT(*) FROM bdc_users WHERE role='super_admin' AND status='active'")->fetchColumn();
    if($role==='super_admin'&&$target['role']!=='super_admin'&&$status==='active'&&$activeSuper>=3)throw new RuntimeException('Maximum 3 active Super Admin accounts are allowed.');
    if($target['role']==='super_admin'&&$target['status']==='active'&&($role!=='super_admin'||$status!=='active')&&$activeSuper<=1)throw new RuntimeException('The last active Super Admin cannot be demoted or suspended.');
    if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid name and email address.');
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE bdc_users SET full_name=:n,email=:e,role=:r,status=:s,updated_at=NOW() WHERE id=:id')->execute(['n'=>$name,'e'=>$email,'r'=>$role,'s'=>$status,'id'=>$uid]);
    $pdo->prepare('DELETE FROM bdc_user_permissions WHERE user_id=:id')->execute(['id'=>$uid]);
    $ip=$pdo->prepare('INSERT INTO bdc_user_permissions(user_id,permission_key,allowed) VALUES(:u,:p,1)');
    foreach((array)($_POST['permissions']??[]) as $key) if(isset($permissions[$key])) $ip->execute(['u'=>$uid,'p'=>$key]);
    $newPassword=(string)($_POST['new_password']??'');
    if($newPassword!==''){
      if(strlen($newPassword)<10) throw new RuntimeException('New password must be at least 10 characters.');
      $pdo->prepare('UPDATE bdc_users SET password_hash=:p WHERE id=:id')->execute(['p'=>password_hash($newPassword,PASSWORD_DEFAULT),'id'=>$uid]);
    }
    $pdo->commit();
    Auth::audit((int)Auth::user()['id'],'user_role_updated',['target_email'=>$email,'old_role'=>$target['role'],'new_role'=>$role,'status'=>$status,'password_reset'=>$newPassword!==''],'admin_user',$uid);
    $message='User and role updated successfully.';
   }
  } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); $error=$e->getMessage(); }
 }
}

$editId=(int)($_GET['edit']??0);$editing=null;$editingPermissions=[];
if($editId){
 $s=$pdo->prepare("SELECT id,email,full_name,role,status,last_login_at,created_at FROM bdc_users WHERE id=:id AND role IN('admin','scorer','master_scorer','super_admin')");$s->execute(['id'=>$editId]);$editing=$s->fetch();
 if($editing){$p=$pdo->prepare('SELECT permission_key FROM bdc_user_permissions WHERE user_id=:id AND allowed=1');$p->execute(['id'=>$editId]);$editingPermissions=$p->fetchAll(PDO::FETCH_COLUMN);}
}
$users=$pdo->query("SELECT id,email,full_name,role,status,last_login_at,created_at FROM bdc_users WHERE role IN('admin','scorer','master_scorer','super_admin') ORDER BY FIELD(role,'super_admin','admin','master_scorer','scorer'),full_name")->fetchAll();
$csrf=Csrf::token();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Users | BDC Competitor Dashboard</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?=e(url('public/assets/css/app.css'))?>" rel="stylesheet"></head><body class="bg-light">
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><span class="text-white small">Super Admin only</span></div></nav>
<div class="container py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Users &amp; Roles</h1><p class="text-muted mb-0">Super Admin can appoint Admins, Scorers, Master Scorers and up to 3 Super Admins.</p></div><a class="btn btn-outline-dark" href="<?=e(url('admin/'))?>">Dashboard</a></div>
<?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5 mb-3"><?=$editing?'Edit Admin':'Add Admin User'?></h2>
<form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="<?=$editing?'update':'create'?>"><?php if($editing):?><input type="hidden" name="user_id" value="<?=(int)$editing['id']?>"><?php endif;?>
<div class="mb-3"><label class="form-label">Full name</label><input class="form-control" name="full_name" required value="<?=e($editing['full_name']??'')?>"></div>
<div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required value="<?=e($editing['email']??'')?>"></div>
<div class="mb-3"><label class="form-label">Role</label><select class="form-select" name="role"><?php foreach($managedRoles as $role=>$label):?><option value="<?=e($role)?>" <?=($editing['role']??'admin')===$role?'selected':''?>><?=e($label)?></option><?php endforeach;?></select><div class="form-text">Master Scorers can view past scores. Regular Scorers cannot. Maximum 3 active Super Admins.</div></div>
<?php if($editing):?><div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?=($editing['status']??'')==='active'?'selected':''?>>Active</option><option value="suspended" <?=($editing['status']??'')==='suspended'?'selected':''?>>Suspended</option></select></div><div class="mb-3"><label class="form-label">Reset password, optional</label><input class="form-control" type="password" name="new_password" minlength="10" autocomplete="new-password"><div class="form-text">Leave blank to keep the current password.</div></div><?php else:?><div class="mb-3"><label class="form-label">Temporary password</label><input class="form-control" type="password" name="password" minlength="10" required autocomplete="new-password"></div><?php endif;?>
<div class="mb-3"><label class="form-label fw-semibold">Permissions</label><?php foreach($permissions as $key=>$label):?><div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="<?=e($key)?>" id="p_<?=e(str_replace('.','_',$key))?>" <?=in_array($key,$editingPermissions,true)?'checked':''?>><label class="form-check-label" for="p_<?=e(str_replace('.','_',$key))?>"><?=e($label)?></label></div><?php endforeach;?></div>
<button class="btn btn-dark"><?=$editing?'Save Changes':'Create Admin'?></button><?php if($editing):?><a class="btn btn-outline-secondary" href="./">Cancel</a><?php endif;?></form></div></div></div>
<div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">Assigned Users</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody><?php if(!$users):?><tr><td colspan="6" class="text-muted">No assigned users yet.</td></tr><?php endif;?><?php foreach($users as $u):?><tr><td><?=e($u['full_name'])?></td><td><?=e($u['email'])?></td><td><span class="badge text-bg-primary"><?=e($managedRoles[$u['role']]??ucwords(str_replace('_',' ',$u['role'])))?></span></td><td><span class="badge <?=$u['status']==='active'?'text-bg-success':'text-bg-secondary'?>"><?=e(ucfirst($u['status']))?></span></td><td><?=e($u['last_login_at']?:'Never')?></td><td><a class="btn btn-sm btn-outline-dark" href="?edit=<?=(int)$u['id']?>">Edit</a></td></tr><?php endforeach;?></tbody></table></div></div></div></div></div>
<div class="alert alert-warning mt-4 mb-0"><strong>Security:</strong> Every role change is audited. The system blocks a fourth active Super Admin and protects the last active Super Admin.</div></div></body></html>
