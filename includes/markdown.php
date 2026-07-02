<?php
/*
 * markdown.php - Convert Markdown body text to safe HTML for article display.
 */
function render_markdown(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    $lines = explode("\n", $text);
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^### (.+)$/', $trimmed, $m)) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $html .= '<h3>' . htmlspecialchars($m[1]) . "</h3>\n";
            continue;
        }

        if (preg_match('/^## (.+)$/', $trimmed, $m)) {
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }
            $html .= '<h2>' . htmlspecialchars($m[1]) . "</h2>\n";
            continue;
        }

        if (preg_match('/^- (.+)$/', $trimmed, $m)) {
            if (!$inList) {
                $html .= "<ul>\n";
                $inList = true;
            }
            $html .= '<li>' . format_inline_markdown($m[1]) . "</li>\n";
            continue;
        }

        if ($inList) {
            $html .= "</ul>\n";
            $inList = false;
        }

        $html .= '<p>' . format_inline_markdown($trimmed) . "</p>\n";
    }

    if ($inList) {
        $html .= "</ul>\n";
    }

    return $html;
}

function format_inline_markdown(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text);

    return $text;
}

function markdown_to_plain_text(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    $lines = explode("\n", $text);
    $parts = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }

        if (preg_match('/^#{1,3} (.+)$/', $trimmed, $m)) {
            $parts[] = $m[1];
            continue;
        }

        if (preg_match('/^- (.+)$/', $trimmed, $m)) {
            $parts[] = $m[1];
            continue;
        }

        $parts[] = $trimmed;
    }

    $plain = implode(' ', $parts);
    $plain = preg_replace('/\[(.+?)\]\((.+?)\)/', '$1', $plain);
    $plain = preg_replace('/\*\*(.+?)\*\*/', '$1', $plain);
    $plain = preg_replace('/\*(.+?)\*/', '$1', $plain);
    $plain = preg_replace('/\s+/', ' ', $plain);

    return trim($plain);
}
