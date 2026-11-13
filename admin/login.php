<?php
require __DIR__ . '/../includes/init.php';
if ($u = user()) redirect(home_for($u));

$firstAdmin = !val("SELECT 1 FROM users WHERE role='admin' LIMIT 1");
$tab = $firstAdmin ? 'register' : 'login'; $err = [];
if (is_post()) {
    if (post('action') === 'login') {
        $tab = 'login';
        $row = one('SELECT * FROM users WHERE email=?', [norm_email(post('email'))]);
        if ($row && $row['role'] === 'admin' && empty($row['deleted_at']) && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
            login_user($row['id']);
            redirect('admin/dashboard.php');
        }
        $err[] = 'The email or password is incorrect, or this is not an admin account.';
    } else {
        $tab = 'register';
        [$err, $c] = reg_validate();
        if (!$firstAdmin && !hash_equals(ADMIN_INVITE_CODE, post('code'))) $err[] = 'The admin invite code is not correct.';
        if (!$err) {
            q("INSERT INTO users(name,email,phone,password_hash,role) VALUES(?,?,?,?, 'admin')", [$c['name'], $c['email'], $c['phone'], $c['hash']]);
            login_user((int)db()->lastInsertId());
            flash('success', 'Admin account created. You are signed in.');
            redirect('admin/dashboard.php');
        }
    }
}
page_start('Admin sign in', 'admin-login');
?>
<div class="container py-5" style="max-width:520px">
  <div class="auth-card">
    <div class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-shield-lock fs-3 text-primary"></i><h1 class="h4 m-0">Admin access</h1></div>
    <?php if ($firstAdmin): ?><div class="alert alert-info py-2">No admin exists yet. Register the first admin to set up your password.</div><?php endif; ?>
    <ul class="nav nav-pills nav-fill mb-3">
      <li class="nav-item"><button class="nav-link <?= $tab === 'login' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#a-login" type="button">Sign in</button></li>
      <li class="nav-item"><button class="nav-link <?= $tab === 'register' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#a-reg" type="button">Register admin</button></li>
    </ul>
    <?php foreach ($err as $m): ?><div class="alert alert-danger py-2"><?= e($m) ?></div><?php endforeach; ?>
    <div class="tab-content">
      <form id="a-login" method="post" class="tab-pane fade <?= $tab === 'login' ? 'show active' : '' ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="login">
        <div class="mb-3"><label class="form-label" for="ae">Email</label><input class="form-control" id="ae" type="email" name="email" required placeholder="yourname@gmail.com"></div>
        <div class="mb-3"><label class="form-label" for="ap">Password</label><input class="form-control" id="ap" type="password" name="password" required></div>
        <button class="btn btn-primary w-100">Sign in as admin</button>
      </form>
      <form id="a-reg" method="post" class="tab-pane fade <?= $tab === 'register' ? 'show active' : '' ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="register">
        <div class="mb-2"><label class="form-label" for="an">Full name</label><input class="form-control" id="an" name="name" required value="<?= e($_POST['name'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label" for="are">Email</label><input class="form-control" id="are" type="email" name="email" required placeholder="yourname@gmail.com" value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label" for="arp">Password</label><input class="form-control" id="arp" type="password" name="password" minlength="8" required></div>
          <div class="col-6"><label class="form-label" for="arp2">Confirm password</label><input class="form-control" id="arp2" type="password" name="password2" minlength="8" required></div>
        </div>
        <?php if (!$firstAdmin): ?><div class="mb-2"><label class="form-label" for="ac">Admin invite code</label><input class="form-control" id="ac" name="code" required><div class="form-text">Set in config.php (ADMIN_INVITE_CODE).</div></div><?php endif; ?>
        <button class="btn btn-primary w-100 mt-2">Create admin account</button>
      </form>
    </div>
    <a class="d-inline-block mt-3 small" href="<?= url('index.php') ?>"><i class="bi bi-arrow-left"></i> Back to tenant sign in</a>
  </div>
</div>
<?php page_end(); ?>
