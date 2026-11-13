<?php
require __DIR__ . '/includes/init.php';
if ($u = user()) redirect(home_for($u));

$tab = 'login'; $err = [];
if (is_post()) {
    $act = post('action');
    if ($act === 'login') {
        $row = one('SELECT * FROM users WHERE email=?', [norm_email(post('email'))]);
        if ($row && !empty($row['deleted_at']) && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
            $err[] = 'This account was deleted. Contact an admin if you think this is a mistake.';
        } elseif ($row && $row['role'] === 'tenant' && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
            login_user($row['id']);
            redirect('tenant/dashboard.php');
        }
        if (!$err) $err[] = 'The email or password is incorrect.';
    } elseif ($act === 'register') {
        $tab = 'register';
        [$err, $c] = reg_validate(true);
        if (!$err) {
            q("INSERT INTO users(name,email,phone,address,password_hash,role) VALUES(?,?,?,?,?, 'tenant')", [$c['name'], $c['email'], $c['phone'], $c['address'], $c['hash']]);
            login_user((int)db()->lastInsertId());
            flash('success', 'Welcome to BoardingHub! Your tenant account is ready.');
            redirect('tenant/dashboard.php');
        }
    }
}
page_start('Find your bed');
?>
<section class="hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <h1>Find a boarding house with a bed waiting for you.</h1>
        <p class="lead mt-3 mb-4">Compare prices and locations on the map, reserve a bed for the date you move in, and pay online. Every receipt and house rule stays in your account.</p>
        <div class="bed-legend" aria-label="Bed status legend">
          <span><?= bed_icon('available') ?> Available</span>
          <span><?= bed_icon('reserved') ?> Reserved</span>
          <span><?= bed_icon('occupied') ?> Occupied</span>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="auth-card">
          <ul class="nav nav-pills nav-fill mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link <?= $tab === 'login' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#t-login" type="button">Sign in</button></li>
            <li class="nav-item"><button class="nav-link <?= $tab === 'register' ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#t-register" type="button">Create tenant account</button></li>
          </ul>
          <?php foreach ($err as $m): ?><div class="alert alert-danger py-2"><?= e($m) ?></div><?php endforeach; ?>
          <div class="tab-content">
            <form id="t-login" method="post" class="tab-pane fade <?= $tab === 'login' ? 'show active' : '' ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="login">
              <div class="mb-3"><label class="form-label" for="le">Email</label><input class="form-control" id="le" type="email" name="email" required autocomplete="email" placeholder="yourname@gmail.com"></div>
              <div class="mb-3"><label class="form-label" for="lp">Password</label><input class="form-control" id="lp" type="password" name="password" required autocomplete="current-password"></div>
              <button class="btn btn-primary w-100">Sign in</button>
            </form>
            <form id="t-register" method="post" class="tab-pane fade <?= $tab === 'register' ? 'show active' : '' ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="register">
              <div class="mb-2"><label class="form-label" for="rn">Full name</label><input class="form-control" id="rn" name="name" required value="<?= e($_POST['name'] ?? '') ?>" autocomplete="name"></div>
              <div class="row g-2 mb-2">
                <div class="col-sm-7"><label class="form-label" for="re">Email</label><input class="form-control" id="re" type="email" name="email" required placeholder="yourname@gmail.com" value="<?= e($_POST['email'] ?? '') ?>"></div>
                <div class="col-sm-5"><label class="form-label" for="rph">Mobile number</label><input class="form-control" id="rph" name="phone" type="tel" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" data-mobile placeholder="09XXXXXXXXX" title="11 digits, starting with 09" value="<?= e($_POST['phone'] ?? '') ?>" autocomplete="tel"></div>
              </div>
              <div class="mb-2"><label class="form-label" for="ra">Address</label><input class="form-control" id="ra" name="address" required maxlength="255" placeholder="House no., street, barangay, city" value="<?= e($_POST['address'] ?? '') ?>" autocomplete="street-address"></div>
              <div class="row g-2 mb-3">
                <div class="col-sm-6"><label class="form-label" for="rp">Password</label><input class="form-control" id="rp" type="password" name="password" minlength="8" required autocomplete="new-password"></div>
                <div class="col-sm-6"><label class="form-label" for="rp2">Confirm password</label><input class="form-control" id="rp2" type="password" name="password2" minlength="8" required autocomplete="new-password"></div>
              </div>
              <button class="btn btn-primary w-100">Create account</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="container pb-4">
  <h2 class="h4 mb-3">How BoardingHub works</h2>
  <div class="step-row">
    <div class="bh-card"><div class="step-no">1</div><h3>Browse</h3><p class="mb-0 text-muted">Search boarding houses by price, category and location. See which rooms still have vacant beds.</p></div>
    <div class="bh-card"><div class="step-no">2</div><h3>Reserve</h3><p class="mb-0 text-muted">Pick a bed and a move-in date within the next 14 days. The owner accepts or declines.</p></div>
    <div class="bh-card"><div class="step-no">3</div><h3>Pay online</h3><p class="mb-0 text-muted">Pay through PayMaya, GCash or card, download your receipt and house rules, and see your next payment date.</p></div>
  </div>
  <p class="mt-4 text-muted">Own a boarding house? Create a tenant account first, then apply as an owner from your dashboard. An admin reviews your National ID and proof of ownership.</p>
</section>
<?php page_end(); ?>
