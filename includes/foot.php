</div><!-- /.content -->
</main>
</div><!-- /.layout -->
<script src="js/utils.js?v=<?= assetVersion('js/utils.js') ?>"></script>
<?php if (!empty($pageScript)): ?>
<script src="<?= htmlspecialchars($pageScript) ?>?v=<?= assetVersion($pageScript) ?>"></script>
<?php endif; ?>
</body>
</html>
