<?php
declare(strict_types=1);
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/player/account.php')), '/');
$root = preg_replace('#/player$#', '', $base) ?? '';
$storeDir = dirname(__DIR__) . '/storage/data';
$storeFile = $storeDir . '/player-accounts.json';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly'=>true, 'secure'=>$secure, 'samesite'=>'Lax', 'path'=>'/']);
    session_start();
}
if (empty($_SESSION['core_csrf'])) $_SESSION['core_csrf'] = bin2hex(random_bytes(32));
function account_read(string $file): array { if (!is_file($file)) return []; $raw = file_get_contents($file); $data = is_string($raw) ? json_decode($raw, true) : null; return is_array($data) ? $data : []; }
function account_write(string $file, array $data): void { $tmp=$file.'.tmp'; $json=json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES); if ($json===false || file_put_contents($tmp,$json,LOCK_EX)===false || !rename($tmp,$file)) throw new RuntimeException('Could not persist account data. Check storage/data permissions.'); }
$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string)$_SESSION['core_csrf'], (string)($_POST['csrf'] ?? ''))) { $error='Your form session expired. Please try again.'; }
    else {
        $action=(string)($_POST['action'] ?? ''); $email=strtolower(trim((string)($_POST['email'] ?? ''))); $password=(string)($_POST['password'] ?? '');
        if ($action==='logout') { $_SESSION=[]; session_regenerate_id(true); $_SESSION['core_csrf']=bin2hex(random_bytes(32)); $success='You have signed out.'; }
        elseif (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254 || $password==='' || strlen($password)>4096 || ($action==='register' && strlen($password)<10)) { $error = $action==='register' ? 'Enter a valid email and a password between 10 and 4096 characters.' : 'Enter a valid email and password.'; }
        else {
            if (!is_dir($storeDir) && !mkdir($storeDir,0750,true) && !is_dir($storeDir)) $error='Account storage is unavailable.';
            else {
                $lock=fopen($storeFile.'.lock','c');
                if (!$lock || !flock($lock,LOCK_EX)) $error='Account storage is busy; please retry.';
                else try {
                    $accounts=account_read($storeFile); $found=null;
                    foreach ($accounts as $id=>$row) if (is_array($row) && strtolower((string)($row['email']??''))===$email) { $found=(string)$id; break; }
                    if ($action==='register') {
                        if ($found!==null) $error='An account with that email already exists.';
                        else { $id='usr_'.bin2hex(random_bytes(12)); $accounts[$id]=['id'=>$id,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'created_at'=>gmdate('c'),'status'=>'active']; account_write($storeFile,$accounts); session_regenerate_id(true); $_SESSION['core_user']=['id'=>$id,'email'=>$email]; $_SESSION['core_csrf']=bin2hex(random_bytes(32)); header('Location: '.$base.'/selection.php'); exit; }
                    } elseif ($action==='login') {
                        if ($found===null || !password_verify($password,(string)($accounts[$found]['password_hash']??'')) || ($accounts[$found]['status']??'active')!=='active') $error='Email or password was not recognized.';
                        else { session_regenerate_id(true); $_SESSION['core_user']=['id'=>$found,'email'=>$email]; $_SESSION['core_csrf']=bin2hex(random_bytes(32)); header('Location: '.$base.'/selection.php'); exit; }
                    } else $error='Unknown account action.';
                } catch (Throwable $e) { $error='Account operation failed. Check storage permissions and server logs.'; } finally { flock($lock,LOCK_UN); fclose($lock); }
            }
        }
    }
}
$user=$_SESSION['core_user']??null;
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account access · CORE</title><link rel="stylesheet" href="<?=htmlspecialchars($base,ENT_QUOTES)?>/player.css"></head><body><header class="topbar"><a class="brand" href="<?=$base?>/index.php"><span class="brand-mark">C</span><span>CORE <small>UNBOUND FRONTIERS</small></span></a><a class="workbench-link" href="<?=$base?>/index.php">Back to site</a></header><main><section class="welcome panel"><div class="eyebrow">PLAYER ACCOUNT</div><h1><?= $user ? 'Your account.' : 'Your frontier awaits.' ?></h1><p><?= $user ? 'You are signed in. Continue to character selection and universe access.' : 'Create an account or sign in to continue to the player portal.' ?></p><?php if($error):?><div class="notice error"><?=htmlspecialchars($error,ENT_QUOTES)?></div><?php endif;?><?php if($success):?><div class="notice success"><?=htmlspecialchars($success,ENT_QUOTES)?></div><?php endif;?><?php if($user):?><p>Signed in as <strong><?=htmlspecialchars((string)$user['email'],ENT_QUOTES)?></strong></p><div class="input-row"><a class="action-link" href="<?=$base?>/selection.php">Continue to character selection</a><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['core_csrf'],ENT_QUOTES)?>"><input type="hidden" name="action" value="logout"><button type="submit">Sign out</button></form></div><?php else:?><div class="account-columns"><form method="post" class="account-card"><h2>Sign in</h2><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['core_csrf'],ENT_QUOTES)?>"><input type="hidden" name="action" value="login"><label>Email</label><input name="email" type="email" required autocomplete="email"><label>Password</label><input name="password" type="password" required autocomplete="current-password"><button type="submit">Sign in</button></form><form method="post" class="account-card" id="register"><h2>Create account</h2><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['core_csrf'],ENT_QUOTES)?>"><input type="hidden" name="action" value="register"><label>Email</label><input name="email" type="email" required autocomplete="email"><label>Password <small>(10 characters minimum)</small></label><input name="password" type="password" minlength="10" required autocomplete="new-password"><button type="submit">Create account</button></form></div><?php endif;?><p class="fine-print">Prototype account service. Use a unique password. Before public launch, account ownership and authorization must be enforced across all gameplay APIs.</p></section></main></body></html>
