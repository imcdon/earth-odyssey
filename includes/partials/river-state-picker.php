<?php
/*
 * river-state-picker.php - "Pick your state" grid for the river tool.
 * Expects: $pickerAction (page URL the chosen state is sent to), $pickerQuery (extra query args, optional).
 */
$pickerQuery = $pickerQuery ?? [];
?>
<section class="container river-states" aria-labelledby="pick-state-title">
    <h2 class="rivers-section-title" id="pick-state-title">Pick your state</h2>
    <ul class="river-state-grid">
        <?php foreach (river_state_counts() as $code => $count): ?>
            <li>
                <?php if ($count): ?>
                    <a class="river-state" href="<?= htmlspecialchars($pickerAction . '?' . http_build_query(['state' => $code] + $pickerQuery)) ?>">
                        <span class="river-state-name"><?= htmlspecialchars(RIVER_STATE_NAMES[$code] ?? $code) ?></span>
                        <span class="river-state-count"><?= $count ?> gauges</span>
                    </a>
                <?php else: ?>
                    <span class="river-state is-pending">
                        <span class="river-state-name"><?= htmlspecialchars(RIVER_STATE_NAMES[$code] ?? $code) ?></span>
                        <span class="river-state-count">Gauges loading soon</span>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
