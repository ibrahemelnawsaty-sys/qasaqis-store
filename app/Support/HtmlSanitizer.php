<?php

declare(strict_types=1);

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * A real HTML sanitizer for admin-authored rich text (e.g. Book::long_description),
 * built on the bundled DOMDocument — no external packages.
 *
 * Policy (deliberately strict, whitelist-only):
 *  - Keep ONLY a small set of safe structural/inline tags.
 *  - Remove EVERY attribute from every kept element (on*, style, href, src, class…),
 *    so no event handlers, inline styles, or URLs can ride along.
 *  - Drop <script>/<style> including their contents; drop comments.
 *  - Unwrap any other element but keep its (already sanitized) text, so prose survives.
 *
 * Output is safe to echo through Blade's {!! !!}. Text nodes are re-encoded by
 * saveHTML(), so any stray "<", ">" or "&" in the text become entities.
 */
final class HtmlSanitizer
{
    /**
     * The only tags allowed to remain in the output.
     *
     * @var array<int, string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'h3', 'h4', 'span',
    ];

    public static function clean(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        // Preserve UTF-8 Arabic: the "<?xml encoding>" hint forces libxml to read
        // the bytes as UTF-8, while NOIMPLIED/NODEFDTD stop it from wrapping the
        // fragment in <html>/<body> or adding a doctype. libxml warns on HTML5
        // tags it doesn't know; those warnings are irrelevant here, so mute them.
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return '';
        }

        // Drop the leftover XML processing-instruction / doctype node(s), if any.
        foreach (iterator_to_array($dom->childNodes) as $child) {
            if ($child->nodeType === XML_PI_NODE || $child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                $dom->removeChild($child);
            }
        }

        self::sanitizeChildren($dom);

        $out = '';
        foreach (iterator_to_array($dom->childNodes) as $child) {
            $out .= (string) $dom->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Recursively sanitize the children of $node in place.
     */
    private static function sanitizeChildren(DOMNode $node): void
    {
        // Snapshot the list first: the tree is mutated while we walk it.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                // script/style carry executable/style content — remove entirely.
                if ($tag === 'script' || $tag === 'style') {
                    $node->removeChild($child);

                    continue;
                }

                // Clean the subtree first so anything we promote is already safe.
                self::sanitizeChildren($child);

                if (in_array($tag, self::ALLOWED_TAGS, true)) {
                    self::stripAllAttributes($child);
                } else {
                    self::unwrap($child);
                }
            } elseif ($child instanceof DOMComment) {
                $node->removeChild($child);
            }
            // Plain text (and CDATA) nodes are kept verbatim.
        }
    }

    private static function stripAllAttributes(DOMElement $element): void
    {
        // Collect names first — the live attribute map shrinks as we remove.
        $names = [];
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $names[] = $attribute->nodeName;
        }

        foreach ($names as $name) {
            $element->removeAttribute($name);
        }
    }

    /**
     * Replace a disallowed element with its (already sanitized) children.
     */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
