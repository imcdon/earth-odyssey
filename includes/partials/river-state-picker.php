<?php
/*
 * river-state-picker.php - Clickable US map for the river tool.
 * Expects: $pickerAction (page URL the chosen state is sent to), $pickerQuery (extra query args, optional).
 * Only configured states that already have gauges are links; the rest stay visible but muted.
 */
$pickerQuery = $pickerQuery ?? [];
$counts = river_state_counts();
$mapFile = dirname(__DIR__, 2) . '/assets/img/rivers/us-states.svg';
$mapHtml = '';

if (is_file($mapFile)) {
    $raw = (string) file_get_contents($mapFile);
    $raw = preg_replace('/^<\?xml[^>]*>\s*/', '', $raw) ?? $raw;

    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($raw);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if ($ok) {
        $svgNs = 'http://www.w3.org/2000/svg';
        $xlinkNs = 'http://www.w3.org/1999/xlink';
        $svg = $dom->documentElement;
        $svg->setAttribute('class', trim(($svg->getAttribute('class') ?: '') . ' river-map-svg'));
        $svg->setAttribute('role', 'group');
        $svg->setAttribute('aria-label', 'Map of the United States. Highlighted states have gauges.');

        $nodes = [];
        foreach ($dom->getElementsByTagName('*') as $el) {
            if (!in_array($el->localName, ['path', 'circle'], true)) {
                continue;
            }
            $classes = preg_split('/\s+/', trim($el->getAttribute('class'))) ?: [];
            $code = '';
            foreach ($classes as $c) {
                if (preg_match('/^[a-z]{2}$/', $c) && isset(RIVER_STATE_NAMES[strtoupper($c)])) {
                    $code = strtoupper($c);
                    break;
                }
            }
            if ($code === '') {
                continue;
            }
            $nodes[] = [$el, $code];
        }

        foreach ($nodes as [$el, $code]) {
            $count = (int) ($counts[$code] ?? 0);
            $name = RIVER_STATE_NAMES[$code] ?? $code;
            $live = $count > 0 && isset($counts[$code]);

            $el->setAttribute('data-state', $code);
            $classes = preg_split('/\s+/', trim($el->getAttribute('class'))) ?: [];
            $classes = array_values(array_filter($classes, fn($c) => $c !== 'is-live' && $c !== 'is-off'));
            $classes[] = $live ? 'is-live' : 'is-off';
            $el->setAttribute('class', implode(' ', $classes));

            $titles = $el->getElementsByTagName('title');
            $label = $live
                ? $name . ' · ' . $count . ' gauge' . ($count === 1 ? '' : 's')
                : $name . (isset($counts[$code]) ? ' · gauges loading soon' : '');
            if ($titles->length) {
                $titles->item(0)->textContent = $label;
            } else {
                $title = $dom->createElementNS($svgNs, 'title');
                $title->textContent = $label;
                $el->insertBefore($title, $el->firstChild);
            }

            if (!$live) {
                continue;
            }

            $href = $pickerAction . '?' . http_build_query(['state' => $code] + $pickerQuery);
            $a = $dom->createElementNS($svgNs, 'a');
            $a->setAttribute('href', $href);
            $a->setAttributeNS($xlinkNs, 'xlink:href', $href);
            $a->setAttribute('class', 'river-map-link');
            $a->setAttribute('data-state', $code);
            $a->setAttribute('data-label', $label);
            $a->setAttribute('aria-label', $label);
            $el->parentNode->insertBefore($a, $el);
            $a->appendChild($el);
        }

        $mapHtml = $dom->saveXML($svg) ?: '';
    }
}
?>
<section class="container river-states" aria-labelledby="pick-state-title">
    <h2 class="rivers-section-title" id="pick-state-title">Pick your state</h2>
    <?php if ($mapHtml !== ''): ?>
        <div class="river-map" data-river-map>
            <?= $mapHtml ?>
            <p class="river-map-caption" aria-live="polite" data-map-caption>Highlighted states have gauges. Hover or tap one to open it.</p>
        </div>
    <?php else: ?>
        <ul class="river-state-grid">
            <?php foreach ($counts as $code => $count): ?>
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
    <?php endif; ?>
</section>
