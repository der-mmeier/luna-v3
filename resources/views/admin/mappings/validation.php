<?php

use Luna\Mapping\MappingValidationResult;

/** @var MappingValidationResult $validation */
?>
<?php foreach ([['danger', 'Errors', $validation->errors()], ['warning', 'Warnings', $validation->warnings()], ['info', 'Infos', $validation->infos()]] as [$type, $label, $items]): ?>
    <?php if ($items !== []): ?>
        <div class="alert alert-<?= $type ?>">
            <strong><?= $label ?></strong>
            <ul class="mb-0">
                <?php foreach ($items as $message): ?>
                    <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
