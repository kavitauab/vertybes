<?php
$navItems = [
    ['dashboard', '/dashboard', 'bi-speedometer2', 'Apžvalga'],
    ['leads',     '/leads',     'bi-people',       'Kontaktai'],
    ['texts',     '/texts',     'bi-fonts',        'Tekstai'],
    ['questions', '/questions', 'bi-patch-question','Klausimai'],
    ['values',    '/values',    'bi-heart',        'Vertybės'],
    ['sessions',  '/sessions',  'bi-clipboard-data','Testo sesijos'],
];
$adminItems = [
    ['settings',  '/settings',  'bi-gear',         'Nustatymai'],
    ['users',     '/users',     'bi-person-badge', 'Vartotojai'],
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
    <a href="/" class="nav-item" target="_blank">
      <i class="bi bi-box-arrow-up-right"></i><span>Peržiūrėti svetainę</span>
    </a>
  </nav>
</aside>
