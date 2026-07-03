<?php
$navItems = [
    ['dashboard', 'dashboard.php', 'bi-speedometer2', 'Apžvalga'],
    ['leads',     'leads.php',     'bi-people',       'Kontaktai'],
    ['texts',     'texts.php',     'bi-fonts',        'Tekstai'],
    ['questions', 'questions.php', 'bi-patch-question','Klausimai'],
    ['values',    'values.php',    'bi-heart',        'Vertybės'],
    ['sessions',  'sessions.php',  'bi-clipboard-data','Testo sesijos'],
];
$adminItems = [
    ['settings',  'settings.php',  'bi-gear',         'Nustatymai'],
    ['users',     'users.php',     'bi-person-badge', 'Vartotojai'],
];
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <span class="brand-mark">V</span>
    <span class="brand-name">Vertybių testas</span>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($navItems as [$key, $href, $icon, $label]): ?>
    <a href="<?= $href ?>" class="nav-item<?= $activeNav === $key ? ' active' : '' ?>">
      <i class="bi <?= $icon ?>"></i><span><?= $label ?></span>
    </a>
    <?php endforeach; ?>
    <?php if (isAdmin()): ?>
    <div class="nav-sep"></div>
    <?php foreach ($adminItems as [$key, $href, $icon, $label]): ?>
    <a href="<?= $href ?>" class="nav-item<?= $activeNav === $key ? ' active' : '' ?>">
      <i class="bi <?= $icon ?>"></i><span><?= $label ?></span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
    <div class="nav-sep"></div>
    <a href="index.php" class="nav-item" target="_blank">
      <i class="bi bi-box-arrow-up-right"></i><span>Peržiūrėti svetainę</span>
    </a>
  </nav>
</aside>
