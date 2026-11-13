<?php

/**
 * @file classes/services/DoajPaCrawlService.inc.php
 *
 * @class DoajPaCrawlService
 * @brief Bounded, resumable crawl of ONE journal's public website for a running assessment run
 *  (Stage 01 PROD-07, F9; Stage 02.06; ADR-005). Infrastructure only: it records what was read and how,
 *  never a DOAJ decision.
 *
 *  - Starting point: the journal homepage URL as OJS routes it (navigation only); every fact comes from an
 *    unauthenticated HTTP fetch, stored with URL, final URL, time (UTC), HTTP status, media type and SHA-256.
 *  - Scope: same scheme/host/port and journal path; backend and dynamic paths excluded; robots.txt honoured.
 *  - Safety: DoajPaHttpFetcher (address guard, pinning, redirect re-checks, size and time limits).
 *  - Limits: pages, issues, articles, files, depth, bytes, total time; one request at a time with a pause.
 *  - Results go through DoajPaAssessmentRunService::addCrawlResource()/addEvidence() only. Evidence is
 *    stored only for successfully retrieved and extracted documents.
 *  - Working state (queue, seen set) is a private JSON file under files_dir, written atomically under an
 *    exclusive lock; a step that finds a URL already stored skips it, so an interrupted step can be resumed.
 *  - The state is bound to its run's identity (context, ID, number, creation time). Restoring a database backup can
 *    give a new run the ID of a discarded one whose private files remain: start() moves such a stale directory
 *    aside, and step() refuses a state that belongs to another run or is ahead of the stored records.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAssessmentRunService.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');
require_once(dirname(__FILE__) . '/../crawler/DoajPaCrawlScope.inc.php');
require_once(dirname(__FILE__) . '/../crawler/DoajPaRobots.inc.php');
require_once(dirname(__FILE__) . '/../crawler/DoajPaHttpFetcher.inc.php');
require_once(dirname(__FILE__) . '/../crawler/DoajPaPreProductionPolicy.inc.php');
require_once(dirname(__FILE__) . '/../crawler/DoajPaBackendSource.inc.php');
require_once(dirname(__FILE__) . '/../crawler/DoajPaPageClassifier.inc.php');
require_once(dirname(__FILE__) . '/../extract/DoajPaHtmlExtractor.inc.php');
require_once(dirname(__FILE__) . '/../extract/DoajPaPdfExtractor.inc.php');
require_once(dirname(__FILE__) . '/../extract/DoajPaLanguageHeuristic.inc.php');

class DoajPaCrawlService {

	const STATE_VERSION = 1;
	/** Stored on a retrieved resource whose language the heuristic could not establish (never verified English). */
	const LANGUAGE_UNDETERMINED = 'language_undetermined (advisory heuristic; not verified English)';
	const DEFAULT_LIMITS = array(
		'maxPages' => 150, 'maxIssues' => 4, 'maxArticles' => 40, 'maxFiles' => 20, 'maxDepth' => 6,
		'maxHtmlBytes' => 3145728, 'maxPdfBytes' => 20971520, 'maxSeconds' => 900, 'pauseMs' => 250,
		'connectTimeout' => 5, 'requestTimeout' => 20, 'maxSkippedSamples' => 300,
	);

	/**
	 * Journal homepage URL as OJS routes it. Used only to start navigation.
	 * @return string
	 */
	public static function homepageUrl($request, $context) {
		if (Config::getVar('general', 'disable_path_info')) {
			throw new DoajPaStateException('The crawler needs path-info URLs; disable_path_info = On is not supported in this build.');
		}
		// CLI requests (tests, scheduled tasks) have no dispatcher attached; use the application's.
		$dispatcher = $request->getDispatcher() ?: Application::get()->getDispatcher();
		return $dispatcher->url($request, ROUTE_PAGE, $context->getPath());
	}

	/**
	 * Start a crawl for a running run.
	 * @param $homepageUrl string
	 * @param $options array limits overrides; 'pluginPath' (for the dev allowance), 'resolver' (tests)
	 * @return array state summary
	 */
	public static function start($contextId, $runId, $homepageUrl, $actor, $options = array()) {
		$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
		if ($run->status !== 'running') throw new DoajPaStateException('A crawl can start only for a running run.');
		if (Config::getVar('proxy', 'http_proxy') || Config::getVar('proxy', 'https_proxy')) {
			throw new DoajPaStateException('An HTTP proxy is configured for OJS. The crawler refuses to run, because the destination address cannot be verified through a proxy.');
		}
		$scope = new DoajPaCrawlScope($homepageUrl);
		$limits = self::DEFAULT_LIMITS;
		foreach ($limits as $k => $v) if (isset($options[$k]) && is_int($options[$k]) && $options[$k] > 0) $limits[$k] = min($options[$k], $v * 4);
		$dir = self::workDir($contextId, $runId);
		$retired = null;
		if (is_file($dir . '/state.json')) {
			$old = json_decode((string) @file_get_contents($dir . '/state.json'), true);
			if (is_array($old) && self::belongsTo($old, $run)) throw new DoajPaStateException('A crawl was already started for this run.');
			$retired = self::retire($dir);
			$dir = self::workDir($contextId, $runId);
		}
		$state = array('version' => self::STATE_VERSION, 'contextId' => $contextId, 'runId' => $runId, 'runIdentity' => self::runIdentity($run),
			'homepage' => $scope->target()['homepage'],
			'limits' => $limits, 'startedAtUtc' => gmdate('Y-m-d H:i:s'), 'elapsed' => 0.0,
			'queue' => array(), 'seq' => 0, 'seen' => array(), 'counts' => array('fetched' => 0, 'issues' => 0, 'articles' => 0, 'files' => 0),
			'statusCounts' => array(), 'typeCounts' => array(), 'skipped' => array(), 'skippedCounts' => array(), 'limitsHit' => array(),
			'robots' => array('status' => null, 'rules' => array()), 'devException' => false, 'skippedKeys' => array(), 'canonicals' => array(), 'contentHashes' => array(),
			'notes' => array(), 'resources' => array(), 'done' => false, 'pdfTool' => DoajPaPdfExtractor::toolVersion(), 'extractor' => DoajPaHtmlExtractor::VERSION);
		$fetcher = self::fetcher($scope, $limits, $options);
		$robots = $fetcher->fetch($scope->robotsUrl(), 524288);
		if ($robots['error'] === null && $robots['status'] === 200) {
			$rules = new DoajPaRobots($robots['body']);
			$state['robots'] = array('status' => 'applied', 'rules' => $rules->rules());
		} else {
			$state['robots'] = array('status' => $robots['error'] !== null ? 'unavailable:' . $robots['error'] : 'none_http_' . $robots['status'], 'rules' => array());
		}
		if ($robots['devException']) self::recordDevException($contextId, $runId, $actor, $robots['devHosts'], $state, $robots['preProduction']);
		self::enqueue($state, $scope, $state['homepage'], 0, 'start', 'nav', 'Journal homepage');
		if ($retired !== null) $state['notes'][] = 'stale_private_state_retired: ' . $retired;
		self::saveState($dir, $state);
		DoajPaAuditService::record($contextId, 'crawl_started', $actor, $runId, 'run', $runId,
			array('homepage' => $state['homepage'], 'limits' => $limits, 'robots' => $state['robots']['status'], 'retiredStaleState' => $retired));
		return self::summary($state);
	}

	/**
	 * Fetch and store up to $budgetSeconds worth of pages. Returns quickly if another step holds the lock.
	 * @return array summary (done, fetched, queued, busy)
	 */
	public static function step($contextId, $runId, $actor, $budgetSeconds = 20, $options = array()) {
		$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
		if ($run->status !== 'running') throw new DoajPaStateException('A crawl can continue only while the run is running.');
		$dir = self::workDir($contextId, $runId);
		$lock = @fopen($dir . '/state.lock', 'c');
		if (!$lock) throw new DoajPaStateException('Cannot open the crawl lock.');
		if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return array('busy' => true); }
		try {
			$state = self::loadState($dir);
			if (!self::belongsTo($state, $run)) {
				throw new DoajPaStateException('The private crawl state does not belong to this run (for example after a database restore). Cancel this run and start a new one.');
			}
			if (!isset($state['runIdentity'])) $state['runIdentity'] = self::runIdentity($run); // state written before 0.6.0.0
			if (!self::recordsMatch($contextId, $runId, $state)) {
				throw new DoajPaStateException('The private crawl state is ahead of the stored records (for example after a database restore). Cancel this run and start a new one.');
			}
			if ($state['done']) return self::summary($state);
			$scope = new DoajPaCrawlScope($state['homepage']);
			$robotsRules = $state['robots']['rules'];
			$fetcher = self::fetcher($scope, $state['limits'], $options);
			// maxSeconds counts crawling time only, so a run resumed after an interruption keeps its remaining budget.
			$began = microtime(true);
			$elapsedBefore = isset($state['elapsed']) ? (float) $state['elapsed'] : 0.0;
			$until = $began + max(1, (int) $budgetSeconds);
			while (microtime(true) < $until) {
				$state['elapsed'] = $elapsedBefore + (microtime(true) - $began);
				if ($state['elapsed'] > $state['limits']['maxSeconds']) { $state['limitsHit']['maxSeconds'] = true; break; }
				if ($state['counts']['fetched'] >= $state['limits']['maxPages']) { if ($state['queue']) $state['limitsHit']['maxPages'] = true; break; }
				$item = self::dequeue($state);
				if ($item === null) break;
				if (!self::robotsAllow($robotsRules, $item['u'])) { self::skip($state, $item['u'], 'robots_disallow', $item['from']); continue; }
				if (DoajPaAssessmentRunService::hasCrawlResource($contextId, $runId, $item['u'])) {
					// Stored by a step that stopped before saving its state: not fetched again (no duplicate record);
					// its links and language were not recorded, so it is reported as a coverage gap.
					self::skip($state, $item['u'], 'interrupted_not_reprocessed', $item['from']);
					$state['notes'][] = 'interrupted_not_reprocessed: ' . $item['u'];
					continue;
				}
				self::process($contextId, $runId, $actor, $state, $scope, $fetcher, $item);
				$state['elapsed'] = $elapsedBefore + (microtime(true) - $began);
				self::saveState($dir, $state);
				usleep($state['limits']['pauseMs'] * 1000);
			}
			$state['elapsed'] = $elapsedBefore + (microtime(true) - $began);
			if (!$state['queue'] || isset($state['limitsHit']['maxPages']) || isset($state['limitsHit']['maxSeconds'])) {
				if ($state['queue']) foreach ($state['queue'] as $left) self::skip($state, $left['u'], 'not_fetched_limit_reached', $left['from']);
				$state['queue'] = array();
				if (empty($state['backendDone'])) {
					self::ingestBackend($contextId, $runId, $actor, $state, $scope);
					$state['backendDone'] = true;
				}
				self::finish($contextId, $runId, $actor, $state, $dir);
			}
			self::saveState($dir, $state);
			return self::summary($state);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/** Run steps until done (CLI and tests). */
	public static function runToCompletion($contextId, $runId, $actor, $options = array()) {
		do { $s = self::step($contextId, $runId, $actor, 60, $options); } while (empty($s['done']) && empty($s['busy']));
		return $s;
	}

	/** @return array Full manifest (private file) */
	public static function manifest($contextId, $runId) {
		$file = self::workDir($contextId, $runId) . '/manifest.json';
		return is_file($file) ? json_decode(file_get_contents($file), true) : null;
	}

	//
	// Internals
	//

	/**
	 * Fetch one page and store it; or, with $given, store a page read from the OJS database (1.3.0.0) through the same
	 * extraction and language check, without fetching and without following its links.
	 */
	private static function process($contextId, $runId, $actor, &$state, $scope, $fetcher, $item, $given = null) {
		$type = DoajPaPageClassifier::classify($scope->relativePath($item['u']));
		$isFile = in_array($type, array('file', 'issue_file', 'galley'), true);
		if ($given !== null) {
			$res = $given;
			$state['backendCount'] = (isset($state['backendCount']) ? $state['backendCount'] : 0) + 1;
		} else {
			$res = $fetcher->fetch($item['u'], $isFile ? $state['limits']['maxPdfBytes'] : $state['limits']['maxHtmlBytes']);
			$state['counts']['fetched']++;
			if ($type === 'issue') $state['counts']['issues']++;
			if ($type === 'article') $state['counts']['articles']++;
			if ($isFile) $state['counts']['files']++;
			if ($res['devException']) self::recordDevException($contextId, $runId, $actor, $res['devHosts'], $state, $res['preProduction']);
		}

		$acq = 'inaccessible'; $reason = null; $kind = null; $text = ''; $title = null; $extractNote = null; $language = null; $links = array();
		$mainText = ''; $mainRegion = null; $structured = array();
		if ($res['error'] !== null) {
			$reason = $res['error'] . ($res['errorDetail'] ? ': ' . $res['errorDetail'] : '');
		} elseif ($res['status'] === 404 || $res['status'] === 410) {
			$acq = 'not_found'; $reason = 'http_' . $res['status'];
		} elseif ($res['status'] === 401 || $res['status'] === 403) {
			$reason = 'access_restricted_http_' . $res['status'];
		} elseif ($res['status'] < 200 || $res['status'] >= 300) {
			$reason = 'http_' . $res['status'];
		} else {
			$kind = substr($res['body'], 0, 5) === '%PDF-' ? 'pdf' : (in_array($res['mediaType'], array('text/html', 'application/xhtml+xml'), true) ? 'html' : null);
			if ($kind === null) {
				$acq = 'parse_failed';
				$reason = $res['mediaType'] === 'application/pdf' ? 'not_a_pdf: declared application/pdf but the content has no PDF signature'
					: 'unsupported_media_type: ' . ($res['mediaType'] ?: 'none');
			} elseif ($kind === 'html') {
				$x = DoajPaHtmlExtractor::extract($res['body'], $res['charset'], $res['finalUrl']);
				if (!$x['ok']) { $acq = 'parse_failed'; $reason = $x['reason']; }
				else {
					$text = $x['text']; $title = $x['title']; $links = $x['links'];
					$mainText = $x['mainText']; $mainRegion = $x['mainRegion']; $structured = $x['structured'];
					// ADR-011: the language is identified from the main content region only; too little text stays undetermined.
					$language = DoajPaLanguageHeuristic::assess($mainText, $x['lang']);
					self::noteHtml($state, $res['finalUrl'], $x);
				}
			} else {
				$p = DoajPaPdfExtractor::extract($res['body'], self::workDir($contextId, $runId) . '/tmp');
				$extractNote = $p['version'];
				if ($p['status'] !== 'ok') { $acq = 'parse_failed'; $reason = $p['status'] . ': ' . $p['reason']; }
				else { $text = $p['text']; $language = DoajPaLanguageHeuristic::assess($text, null); if ($p['reason']) $state['notes'][] = $item['u'] . ' ' . $p['reason']; }
			}
			if ($language !== null) {
				$acq = $language['verdict'] === 'non_english' ? 'unsupported_language' : ($language['verdict'] === 'mixed' ? 'ambiguous' : 'retrieved');
				if ($acq !== 'retrieved') $reason = 'language_' . $language['verdict'] . ($language['detected'] ? ':' . $language['detected'] : '') . ' (advisory heuristic)';
				elseif ($language['verdict'] !== 'english') $reason = self::LANGUAGE_UNDETERMINED;
			}
		}

		$resourceId = DoajPaAssessmentRunService::addCrawlResource($contextId, $runId, array(
			'url' => $item['u'], 'finalUrl' => $res['finalUrl'], 'httpStatus' => $res['status'], 'mediaType' => $res['mediaType'],
			'contentSha256' => $res['sha256'], 'byteLength' => $res['error'] === null ? $res['bytes'] : null, 'acquisitionStatus' => $acq,
			'skipReason' => $reason === null ? null : substr($reason, 0, 255), 'pageType' => $type,
			'extractionVersion' => $kind === 'pdf' ? ($extractNote ?: DoajPaPdfExtractor::VERSION) : DoajPaHtmlExtractor::VERSION,
		));
		$duplicateOf = null;
		if ($res['sha256'] !== null && $res['error'] === null) {
			if (isset($state['contentHashes'][$res['sha256']])) $duplicateOf = $state['contentHashes'][$res['sha256']];
			else $state['contentHashes'][$res['sha256']] = $resourceId;
		}
		$evidenceId = null;
		if (trim($mainText) !== '' && $kind === 'html' && $duplicateOf === null && in_array($acq, array('retrieved', 'unsupported_language', 'ambiguous'), true)) {
			$evidenceId = DoajPaAssessmentRunService::addEvidence($contextId, $runId, array(
				'resourceId' => $resourceId, 'sourceUrl' => $res['finalUrl'], 'sourceTitle' => $title, 'sourceType' => 'html_page', 'quote' => $mainText,
				'location' => 'main content region (' . $mainRegion . ')', 'detectionMethod' => 'html_main_content', 'extractionVersion' => DoajPaHtmlExtractor::VERSION,
			));
		}
		if ($structured && $kind === 'html' && $duplicateOf === null && in_array($acq, array('retrieved', 'unsupported_language', 'ambiguous'), true)) {
			DoajPaAssessmentRunService::addEvidence($contextId, $runId, array(
				'resourceId' => $resourceId, 'sourceUrl' => $res['finalUrl'], 'sourceTitle' => $title, 'sourceType' => 'html_page',
				'quote' => implode("\n", $structured), 'location' => 'page metadata and structure (meta tags, table of contents, licence and language links, image text)',
				'detectionMethod' => 'html_structured', 'extractionVersion' => DoajPaHtmlExtractor::VERSION,
			));
		}
		if (trim($text) !== '' && $duplicateOf === null && in_array($acq, array('retrieved', 'unsupported_language', 'ambiguous'), true)) {
			$pageEvidence = DoajPaAssessmentRunService::addEvidence($contextId, $runId, array(
				'resourceId' => $resourceId, 'sourceUrl' => $res['finalUrl'], 'sourceTitle' => $title,
				'sourceType' => $kind === 'pdf' ? 'pdf' : 'html_page', 'quote' => $text,
				'location' => $kind === 'pdf' ? 'pdf text, all pages read (page markers included)' : 'page text (scripts, styles and forms removed)',
				'detectionMethod' => $kind === 'pdf' ? 'pdftotext_extraction' : 'html_text_extraction',
				'extractionVersion' => $kind === 'pdf' ? ($extractNote ?: DoajPaPdfExtractor::VERSION) : DoajPaHtmlExtractor::VERSION,
			));
			if ($evidenceId === null) $evidenceId = $pageEvidence;
		}
		self::increment($state['statusCounts'], $acq);
		self::increment($state['typeCounts'], $type);
		$state['resources'][] = array('id' => $resourceId, 'url' => $item['u'], 'finalUrl' => $res['finalUrl'], 'type' => $type,
			'status' => $res['status'], 'acquisition' => $acq, 'reason' => $reason, 'mediaType' => $res['mediaType'], 'bytes' => $res['bytes'],
			'sha256' => $res['sha256'], 'fetchedAtUtc' => $res['fetchedAtUtc'], 'hops' => count($res['hops']), 'from' => $item['from'],
			'linkText' => $item['t'], 'depth' => $item['d'], 'evidenceId' => $evidenceId, 'duplicateOf' => $duplicateOf,
			'mainRegion' => $mainRegion, 'mainWords' => $mainText === '' ? 0 : preg_match_all('/\p{L}+/u', $mainText),
			'language' => $language === null ? null : array('verdict' => $language['verdict'], 'detected' => $language['detected'], 'words' => $language['words'], 'attr' => $language['langAttribute']));

		if ($given === null && $kind === 'html' && $links && $item['d'] < $state['limits']['maxDepth']) {
			foreach ($links as $link) self::enqueue($state, $scope, $link['url'], $item['d'] + 1, $res['finalUrl'], $link['location'], $link['text'], $link['href']);
		}
	}

	/**
	 * Add the published content of the OJS database as pages (1.3.0.0). A failure here never stops the run: the
	 * assessment then rests on the website only, and the reason is noted.
	 */
	private static function ingestBackend($contextId, $runId, $actor, &$state, $scope) {
		$state['backendCount'] = 0;
		try {
			$pages = DoajPaBackendSource::pages($contextId, $state['homepage']);
		} catch (\Throwable $e) {
			$state['notes'][] = 'ojs_backend_unavailable: ' . get_class($e);
			return;
		}
		$crawled = array();
		foreach ($state['resources'] as $r) {
			if ($r['acquisition'] !== 'retrieved' || DoajPaBackendSource::sourceOf($r['url']) !== null) continue;
			try { $crawled[DoajPaFindingKey::normalizeUrl(preg_replace('/[?#].*$/', '', $r['finalUrl'] ?: $r['url']))] = (int) $r['depth']; } catch (\Throwable $e) {}
		}
		foreach ($pages as $p) {
			$public = rtrim($state['homepage'], '/') . '/' . $p['path'];
			try { $key = DoajPaFindingKey::normalizeUrl($public); } catch (\Throwable $e) { continue; }
			$linked = isset($crawled[$key]);
			$url = DoajPaBackendSource::markedUrl($state['homepage'], $p['path'], $linked);
			if ($scope->reason($url) !== null || DoajPaAssessmentRunService::hasCrawlResource($contextId, $runId, $url)) continue;
			$body = $p['html'];
			$res = array('requestedUrl' => $url, 'finalUrl' => $url, 'status' => 200, 'mediaType' => 'text/html', 'charset' => 'utf-8', 'body' => $body,
				'bytes' => strlen($body), 'sha256' => hash('sha256', $body), 'error' => null, 'errorDetail' => null, 'hops' => array(),
				'devException' => false, 'preProduction' => false, 'devHosts' => array(), 'primaryIp' => null, 'fetchedAtUtc' => gmdate('Y-m-d H:i:s'));
			$item = array('u' => $url, 'from' => 'ojs-backend', 't' => $p['title'], 'd' => $linked ? $crawled[$key] : 99);
			self::process($contextId, $runId, $actor, $state, $scope, null, $item, $res);
		}
	}

	private static function enqueue(&$state, $scope, $url, $depth, $from, $location, $text, $href = null) {
		if ($url !== null && preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $sm) && !in_array(strtolower($sm[1]), array('http', 'https'), true)) {
			self::skip($state, substr($url, 0, 300), 'not_http_link:' . strtolower($sm[1]), $from);
			return;
		}
		if ($url === null) {
			$scheme = $href !== null && preg_match('/^([a-z][a-z0-9+.-]*):/i', $href, $m) ? strtolower($m[1]) : 'unresolvable';
			self::skip($state, $href !== null ? substr($href, 0, 300) : '', 'not_http_link:' . $scheme, $from);
			return;
		}
		$p = DoajPaUrlTools::parse($url);
		if (!$p['ok']) { self::skip($state, $url, 'invalid_url:' . $p['reason'], $from); return; }
		$reason = $scope->reason($p['url']);
		if ($reason !== null) { self::skip($state, $p['url'], $reason, $from); return; }
		$key = DoajPaUrlTools::key($p['url']);
		if (isset($state['seen'][$key])) return;
		$type = DoajPaPageClassifier::classify($scope->relativePath($p['url']));
		$caps = array('issue' => array('issues', 'maxIssues'), 'article' => array('articles', 'maxArticles'),
			'file' => array('files', 'maxFiles'), 'galley' => array('files', 'maxFiles'), 'issue_file' => array('files', 'maxFiles'));
		if (isset($caps[$type])) {
			$group = $caps[$type][0];
			$queuedOfType = count(array_filter($state['queue'], function ($q) use ($caps, $group) { return isset($caps[$q['k']]) && $caps[$q['k']][0] === $group; }));
			if ($state['counts'][$caps[$type][0]] + $queuedOfType >= $state['limits'][$caps[$type][1]]) {
				$state['limitsHit'][$caps[$type][1]] = true;
				self::skip($state, $p['url'], 'cap_' . $caps[$type][1], $from);
				return;
			}
		}
		$state['seen'][$key] = true;
		$state['queue'][] = array('u' => $p['url'], 'd' => $depth, 'p' => DoajPaPageClassifier::priority($type, $location), 's' => $state['seq']++,
			'from' => $from, 'k' => $type, 'l' => $location, 't' => mb_substr((string) $text, 0, 120, 'UTF-8'));
	}

	private static function dequeue(&$state) {
		if (!$state['queue']) return null;
		usort($state['queue'], function ($a, $b) { return $a['p'] === $b['p'] ? $a['s'] - $b['s'] : $a['p'] - $b['p']; });
		return array_shift($state['queue']);
	}

	/** Records each distinct (URL, reason) once: counts and samples are per distinct skipped URL. */
	private static function skip(&$state, $url, $reason, $from) {
		$key = sha1($reason . '|' . $url);
		if (isset($state['skippedKeys'][$key])) return;
		$state['skippedKeys'][$key] = true;
		self::increment($state['skippedCounts'], explode(':', $reason)[0]);
		if (count($state['skipped']) < $state['limits']['maxSkippedSamples']) $state['skipped'][] = array('url' => substr((string) $url, 0, 500), 'reason' => $reason, 'from' => $from);
	}

	private static function noteHtml(&$state, $url, $x) {
		if ($x['canonical'] !== null) {
			$ck = DoajPaUrlTools::key($x['canonical']);
			if (isset($state['canonicals'][$ck]) && $state['canonicals'][$ck] !== $url) $state['notes'][] = 'duplicate_canonical: ' . $url . ' and ' . $state['canonicals'][$ck] . ' -> ' . $x['canonical'];
			elseif (!isset($state['canonicals'][$ck])) $state['canonicals'][$ck] = $url;
		}
		if ($x['metaRefresh'] !== null) $state['notes'][] = 'meta_refresh_not_followed: ' . $url . ' -> ' . $x['metaRefresh'];
		if ($x['textTruncated']) $state['notes'][] = 'text_truncated: ' . $url;
	}

	private static function robotsAllow($rules, $url) {
		$p = DoajPaUrlTools::parse($url);
		return DoajPaRobots::fromRules($rules)->allows($p['path'] . ($p['query'] !== null ? '?' . $p['query'] : ''));
	}

	private static function increment(&$counts, $key) {
		$counts[$key] = isset($counts[$key]) ? $counts[$key] + 1 : 1;
	}

	private static function recordDevException($contextId, $runId, $actor, $hosts, &$state, $preProduction = false) {
		if (!$state['devException']) {
			DoajPaAssessmentRunService::markDevExceptionUsed($contextId, $runId);
			$state['devException'] = true;
		}
		if ($preProduction) $state['preProduction'] = true;
		$event = $preProduction ? 'preproduction_private_destination_allowed' : 'dev_private_destination_allowed';
		foreach (array_unique($hosts) as $h) {
			$key = $event . '|' . $h;
			if ($preProduction && isset($state['auditedDestinations'][$key])) continue; // once per destination and run
			$state['auditedDestinations'][$key] = true;
			DoajPaAuditService::record($contextId, $event, $actor, $runId, 'run', $runId, array('destination' => $h));
		}
	}

	private static function finish($contextId, $runId, $actor, &$state, $dir) {
		$state['done'] = true;
		$state['finishedAtUtc'] = gmdate('Y-m-d H:i:s');
		$manifest = array('version' => 1, 'contextId' => $contextId, 'runId' => $runId, 'homepage' => $state['homepage'],
			'startedAtUtc' => $state['startedAtUtc'], 'finishedAtUtc' => $state['finishedAtUtc'], 'limits' => $state['limits'],
			'limitsHit' => array_keys($state['limitsHit']), 'robots' => $state['robots'], 'pdfTool' => $state['pdfTool'], 'extractor' => $state['extractor'],
			'devExceptionUsed' => $state['devException'], 'counts' => $state['counts'], 'statusCounts' => $state['statusCounts'],
			'typeCounts' => $state['typeCounts'], 'skippedCounts' => $state['skippedCounts'], 'skipped' => $state['skipped'],
			'notes' => array_slice($state['notes'], 0, 300), 'resources' => $state['resources']);
		$json = DoajPaDb::json($manifest);
		self::atomicWrite($dir . '/manifest.json', $json);
		$coverage = array('manifestSha256' => hash('sha256', $json), 'homepage' => $state['homepage'], 'limits' => $state['limits'],
			'limitsHit' => $manifest['limitsHit'], 'robots' => $state['robots']['status'], 'pdfTool' => $state['pdfTool'], 'extractor' => $state['extractor'],
			'devExceptionUsed' => $state['devException'], 'preProductionMode' => !empty($state['preProduction']),
			'backendPages' => isset($state['backendCount']) ? (int) $state['backendCount'] : 0, 'backendSource' => DoajPaBackendSource::VERSION,
			'counts' => $state['counts'], 'statusCounts' => $state['statusCounts'],
			'typeCounts' => $state['typeCounts'], 'skippedCounts' => $state['skippedCounts'], 'languageCounts' => self::languageCounts($state['resources']),
			'crawlSeconds' => isset($state['elapsed']) ? round($state['elapsed'], 1) : null, 'startedAtUtc' => $state['startedAtUtc'],
			'finishedAtUtc' => $state['finishedAtUtc']);
		DoajPaAssessmentRunService::recordCoverage($contextId, $runId, $coverage, $actor);
		DoajPaAuditService::record($contextId, 'crawl_finished', $actor, $runId, 'run', $runId,
			array('fetched' => $state['counts']['fetched'], 'statusCounts' => $state['statusCounts'], 'limitsHit' => $manifest['limitsHit'], 'manifestSha256' => $coverage['manifestSha256']));
	}

	/** @return array Language verdict => number of resources ("none" = no text extracted) */
	private static function languageCounts($resources) {
		$counts = array();
		foreach ($resources as $r) self::increment($counts, $r['language'] === null ? 'none' : $r['language']['verdict']);
		ksort($counts);
		return $counts;
	}

	/** @return boolean Whether a crawl was started for this run (a state file that belongs to it exists) */
	public static function isStarted($contextId, $runId) {
		$file = self::workDir($contextId, $runId) . '/state.json';
		if (!is_file($file)) return false;
		$state = json_decode((string) @file_get_contents($file), true);
		return is_array($state) && self::belongsTo($state, DoajPaAssessmentRunService::getRun($contextId, $runId));
	}

	/** @return string Identity of a run for its private files (IDs can repeat after a database restore) */
	private static function runIdentity($run) {
		return hash('sha256', implode('|', array((int) $run->context_id, (int) $run->run_id, (int) $run->run_number, (string) $run->created_at_utc)));
	}

	/**
	 * Whether a stored state belongs to this run. States written before 0.6.0.0 carry no identity; for them the crawl
	 * must not have started before the run was created.
	 * @return boolean
	 */
	private static function belongsTo($state, $run) {
		if ((int) ($state['contextId'] ?? 0) !== (int) $run->context_id || (int) ($state['runId'] ?? 0) !== (int) $run->run_id) return false;
		if (isset($state['runIdentity'])) return is_string($state['runIdentity']) && hash_equals($state['runIdentity'], self::runIdentity($run));
		return isset($state['startedAtUtc']) && strcmp((string) $state['startedAtUtc'], (string) $run->created_at_utc) >= 0;
	}

	/** @return boolean Every resource the state recorded is stored for the run (false: the database is older than the state) */
	private static function recordsMatch($contextId, $runId, $state) {
		$ids = array();
		foreach ($state['resources'] as $r) if (isset($r['id'])) $ids[] = (int) $r['id'];
		if (!$ids) return true;
		return DoajPaDb::table(DoajPaSchema::T_RESOURCE)->where('context_id', '=', $contextId)->where('run_id', '=', $runId)
			->whereIn('resource_id', $ids)->count() === count(array_unique($ids));
	}

	/** Move the private directory of an earlier run with the same ID aside (never deleted). @return string New name */
	private static function retire($dir) {
		$target = $dir . '.stale-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
		if (!@rename($dir, $target)) throw new DoajPaStateException('Cannot move aside the private crawl state of an earlier run.');
		return basename($target);
	}

	/**
	 * Read-only progress of a started crawl, for display. Null when no crawl was started.
	 * @return array|null done, fetched, queued, statusCounts, limitsHit, devException
	 */
	public static function progress($contextId, $runId) {
		$file = self::workDir($contextId, $runId) . '/state.json';
		if (!is_file($file)) return null;
		$state = json_decode((string) @file_get_contents($file), true);
		if (!is_array($state) || !isset($state['counts']) || !self::belongsTo($state, DoajPaAssessmentRunService::getRun($contextId, $runId))) return null;
		return self::summary($state);
	}

	private static function fetcher($scope, $limits, $options) {
		$resolver = isset($options['resolver']) ? $options['resolver'] : new DoajPaDnsResolver();
		$pluginPath = isset($options['pluginPath']) ? $options['pluginPath'] : dirname(__FILE__, 3);
		$preProduction = array_key_exists('preProduction', $options) ? $options['preProduction'] : DoajPaPreProductionPolicy::fromConfig($scope->target());
		$guard = new DoajPaAddressGuard($resolver, DoajPaAddressGuard::loadDevAllowance($pluginPath), $preProduction);
		return new DoajPaHttpFetcher($guard, array($scope, 'reason'), $limits['connectTimeout'], $limits['requestTimeout']);
	}

	/** @return string Private working directory under files_dir (created 0700) */
	public static function workDir($contextId, $runId) {
		$filesDir = Config::getVar('files', 'files_dir');
		if (!is_string($filesDir) || $filesDir === '') throw new DoajPaStateException('files_dir is not configured.');
		$dir = rtrim($filesDir, '/') . '/doajPreAssessment/' . DoajPaDb::id($contextId, 'context') . '/crawl/' . DoajPaDb::id($runId, 'run');
		if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) throw new DoajPaStateException('Cannot create the private crawl directory.');
		return $dir;
	}

	private static function loadState($dir) {
		$file = $dir . '/state.json';
		if (!is_file($file)) throw new DoajPaStateException('No crawl was started for this run.');
		$state = json_decode(file_get_contents($file), true);
		if (!is_array($state) || ($state['version'] ?? null) !== self::STATE_VERSION) throw new DoajPaStateException('Crawl state is unreadable.');
		return $state;
	}

	private static function saveState($dir, $state) {
		self::atomicWrite($dir . '/state.json', DoajPaDb::json($state));
	}

	private static function atomicWrite($file, $content) {
		$tmp = $file . '.tmp-' . bin2hex(random_bytes(6));
		// Failures are reported by the exception below, not as PHP warnings.
		if (@file_put_contents($tmp, $content, LOCK_EX) === false || !@rename($tmp, $file)) {
			@unlink($tmp);
			throw new DoajPaStateException('Cannot write crawl state.');
		}
		@chmod($file, 0600);
	}

	private static function summary($state) {
		return array('done' => $state['done'], 'fetched' => $state['counts']['fetched'], 'queued' => count($state['queue']),
			'statusCounts' => $state['statusCounts'], 'limitsHit' => array_keys($state['limitsHit']), 'devException' => $state['devException']);
	}
}
