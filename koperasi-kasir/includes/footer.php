</main>
</div>

<!-- Aset Suara Mesin Kasir -->
<audio id="suara-kasir" preload="auto">
    <source src="https://actions.google.com/sounds/v1/foley/cash_register_purchase.ogg" type="audio/ogg">
</audio>

<!-- Render Icon Feather untuk sapaan JS (Disembunyikan) -->
<template id="icon-pagi"><?= ic('sunrise', 'sapaan-icon') ?></template>
<template id="icon-siang"><?= ic('sun', 'sapaan-icon') ?></template>
<template id="icon-sore"><?= ic('sunset', 'sapaan-icon') ?></template>
<template id="icon-malam"><?= ic('moon', 'sapaan-icon') ?></template>

<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<?php if (!empty($inlineJs)): ?>
<script><?= $inlineJs ?></script>
<?php endif; ?>
<?php foreach ($extraLibs ?? [] as $lib): ?>
<script src="<?= $lib ?>"></script>
<?php endforeach; ?>


<script src="assets/js/app.js"></script>
</body>
</html>