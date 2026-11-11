<?php

/**
 * Sanitizer HTML allowlist cho nội dung bài viết.
 * Chỉ giữ markup cần cho bài báo; không dùng để xử lý SVG hoặc HTML tùy ý.
 */
function sanitizeArticleHtml(?string $html): string
{
    $html = trim((string)$html);
    if ($html === '' || !class_exists('DOMDocument')) return '';

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<!DOCTYPE html><html><body><div id="article-root">' . $html . '</div></body></html>';
    $dom->loadHTML('<?xml encoding="UTF-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    $root = $dom->getElementById('article-root');
    if (!$root) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return '';
    }

    $allowedTags = ['p', 'br', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'blockquote', 'figure', 'figcaption', 'img', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'oembed'];
    $allowedAttrs = [
        'a' => ['href', 'target', 'rel', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
        'oembed' => ['url'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];

    $walk = function (DOMNode $node) use (&$walk, $allowedTags, $allowedAttrs): void {
        for ($child = $node->firstChild; $child; ) {
            $next = $child->nextSibling;
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowedTags, true)) {
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }
                $allowed = $allowedAttrs[$tag] ?? [];
                for ($i = $child->attributes->length - 1; $i >= 0; $i--) {
                    $attr = $child->attributes->item($i);
                    if (!$attr || !in_array(strtolower($attr->name), $allowed, true)) {
                        $child->removeAttributeNode($attr);
                    }
                }
                if ($tag === 'a') {
                    $href = trim($child->getAttribute('href'));
                    if (!isSafeArticleUrl($href)) {
                        $child->removeAttribute('href');
                    } else {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                }
                if ($tag === 'img' && !isSafeArticleUrl(trim($child->getAttribute('src')))) {
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }
                if ($tag === 'oembed' && !isSafeEmbedUrl(trim($child->getAttribute('url')))) {
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }
                $walk($child);
            }
            $child = $next;
        }
    };
    $walk($root);
    $result = '';
    foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $result;
}

function isSafeArticleUrl(string $url): bool
{
    if ($url === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $url)) return false;
    $parts = parse_url($url);
    if (!$parts) return false;
    if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return false;
    return true;
}

function isSafeEmbedUrl(string $url): bool
{
    return (bool)preg_match('~^https://(?:www\.)?(?:youtube\.com|youtu\.be|vimeo\.com)/~i', $url);
}
