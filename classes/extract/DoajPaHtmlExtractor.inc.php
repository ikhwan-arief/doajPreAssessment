<?php

/**
 * @file classes/extract/DoajPaHtmlExtractor.inc.php
 *
 * @class DoajPaHtmlExtractor
 * @brief Deterministic HTML extraction (Stage 01 F2). Website content is DATA: nothing is executed or
 *  followed automatically. libxml network access and entity loading are off. <script>, <style>, <noscript>,
 *  <template>, <iframe>, <object>, <embed> and <svg> are removed. Meta refresh and <base> are recorded;
 *  <base> is honoured only for resolving links (the result is still scope- and address-checked).
 */

require_once(dirname(__FILE__) . '/../crawler/DoajPaUrlTools.inc.php');

class DoajPaHtmlExtractor {

	const VERSION = 'doajpa-html/1';
	const MAX_TEXT_BYTES = 102400;
	const MAX_LINKS = 1000;
	const BLOCK = array('p', 'div', 'section', 'article', 'header', 'footer', 'nav', 'main', 'aside', 'li', 'ul', 'ol', 'table', 'tr',
		'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'br', 'hr', 'blockquote', 'pre', 'dl', 'dt', 'dd', 'figure', 'figcaption', 'address');
	const REMOVE = array('script', 'style', 'noscript', 'template', 'iframe', 'object', 'embed', 'svg', 'canvas', 'form');

	/**
	 * @param $bytes string Raw body
	 * @param $charset string|null From Content-Type
	 * @param $pageUrl string Final URL of the page (base for links)
	 * @return array ok, reason, title, lang, canonical, metaRefresh, baseHref, links[], text, textTruncated, wordCount, charset
	 */
	public static function extract($bytes, $charset, $pageUrl) {
		$out = array('ok' => false, 'reason' => null, 'title' => null, 'lang' => null, 'canonical' => null, 'metaRefresh' => null,
			'baseHref' => null, 'links' => array(), 'text' => '', 'textTruncated' => false, 'wordCount' => 0, 'charset' => null);
		if (!is_string($bytes) || trim($bytes) === '') { $out['reason'] = 'empty_document'; return $out; }
		$utf8 = self::toUtf8($bytes, $charset);
		$out['charset'] = $utf8['charset'];
		$previous = libxml_use_internal_errors(true);
		$disable = PHP_VERSION_ID < 80000 ? libxml_disable_entity_loader(true) : null;
		$doc = new DOMDocument();
		$loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $utf8['text'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (PHP_VERSION_ID < 80000) libxml_disable_entity_loader($disable);
		if (!$loaded || !$doc->documentElement) { $out['reason'] = 'unparseable_html'; return $out; }
		$xp = new DOMXPath($doc);

		$html = $xp->query('//html')->item(0);
		if ($html && $html->hasAttribute('lang')) $out['lang'] = strtolower(substr(trim($html->getAttribute('lang')), 0, 20));
		$title = $xp->query('//title')->item(0);
		if ($title) $out['title'] = self::clean($title->textContent, 255);
		$base = $xp->query('//base[@href]')->item(0);
		$baseUrl = $pageUrl;
		if ($base) {
			$out['baseHref'] = substr($base->getAttribute('href'), 0, 2048);
			$resolved = DoajPaUrlTools::resolve($pageUrl, $out['baseHref']);
			if ($resolved !== null) $baseUrl = $resolved;
		}
		foreach ($xp->query('//link[@href]') as $link) {
			if (in_array('canonical', preg_split('/\s+/', strtolower($link->getAttribute('rel'))), true)) {
				$out['canonical'] = DoajPaUrlTools::resolve($baseUrl, $link->getAttribute('href'));
				break;
			}
		}
		foreach ($xp->query('//meta[@http-equiv]') as $meta) {
			if (strtolower($meta->getAttribute('http-equiv')) === 'refresh') { $out['metaRefresh'] = substr($meta->getAttribute('content'), 0, 2048); break; }
		}
		foreach (self::REMOVE as $tag) {
			foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $node) $node->parentNode->removeChild($node);
		}
		foreach ($xp->query('//a[@href] | //area[@href]') as $a) {
			if (count($out['links']) >= self::MAX_LINKS) break;
			$href = trim($a->getAttribute('href'));
			$out['links'][] = array('href' => substr($href, 0, 4096), 'url' => DoajPaUrlTools::resolve($baseUrl, $href),
				'text' => self::clean($a->textContent, 200), 'location' => self::location($a),
				'rel' => strtolower(substr($a->getAttribute('rel'), 0, 50)));
		}
		$body = $xp->query('//body')->item(0);
		$buffer = '';
		self::collectText($body ?: $doc->documentElement, $buffer);
		$text = trim(preg_replace("/[ \t]*\n[ \t\n]*/", "\n", preg_replace('/[ \t\x{00A0}]+/u', ' ', $buffer)));
		if (strlen($text) > self::MAX_TEXT_BYTES) {
			$text = mb_strcut($text, 0, self::MAX_TEXT_BYTES, 'UTF-8');
			$out['textTruncated'] = true;
		}
		$out['text'] = $text;
		$out['wordCount'] = preg_match_all('/\p{L}+/u', $text);
		$out['ok'] = true;
		return $out;
	}

	/** nav, footer or body: where a link sits on the page (structure only; never proof of content). */
	private static function location($node) {
		for ($n = $node->parentNode; $n && $n->nodeType === XML_ELEMENT_NODE; $n = $n->parentNode) {
			$tag = strtolower($n->nodeName);
			$marks = strtolower($n->getAttribute('class') . ' ' . $n->getAttribute('id') . ' ' . $n->getAttribute('role'));
			if ($tag === 'footer' || strpos($marks, 'footer') !== false || strpos($marks, 'contentinfo') !== false) return 'footer';
			if ($tag === 'nav' || strpos($marks, 'navigation') !== false || strpos($marks, 'nav_') !== false || preg_match('/\b(nav|menu|navbar)\b/', $marks)) return 'nav';
		}
		return 'body';
	}

	private static function collectText($node, &$buffer) {
		foreach ($node->childNodes as $child) {
			if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
				$buffer .= $child->nodeValue;
			} elseif ($child->nodeType === XML_ELEMENT_NODE) {
				$block = in_array(strtolower($child->nodeName), self::BLOCK, true);
				if ($block) $buffer .= "\n";
				self::collectText($child, $buffer);
				if ($block) $buffer .= "\n";
			}
		}
	}

	private static function clean($text, $max) {
		$text = trim(preg_replace('/\s+/u', ' ', (string) $text));
		return mb_substr($text, 0, $max, 'UTF-8');
	}

	/** @return array ['text' => UTF-8 string, 'charset' => detected label] */
	private static function toUtf8($bytes, $charset) {
		$label = $charset;
		if (!$label && preg_match('/<meta[^>]+charset=["\']?([a-zA-Z0-9_-]+)/i', substr($bytes, 0, 4096), $m)) $label = strtolower($m[1]);
		if (!$label) $label = mb_check_encoding($bytes, 'UTF-8') ? 'utf-8' : 'windows-1252';
		$text = $bytes;
		if (!in_array($label, array('utf-8', 'utf8'), true)) {
			$converted = @mb_convert_encoding($bytes, 'UTF-8', $label);
			if ($converted !== false && $converted !== null) $text = $converted;
		}
		if (!mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
		return array('text' => $text, 'charset' => $label);
	}
}
