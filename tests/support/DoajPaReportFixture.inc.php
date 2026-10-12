<?php
/**
 * TEST ONLY (never packaged). A stored-run detail (the shape of DoajPaReadService::run()) for a Stage 03 fixture journal,
 * evaluated with the candidate criteria exactly as in the unit tests, so the Stage 04 action plan, report model and
 * renderers can be tested without a database. Finding IDs are assigned in order of the detections.
 */
require_once(dirname(__FILE__) . '/DoajPaRuleFixture.inc.php');
require_once(dirname(__FILE__) . '/../../classes/reports/DoajPaReportModel.inc.php');
require_once(dirname(__FILE__) . '/../../classes/reports/DoajPaReportHtml.inc.php');
require_once(dirname(__FILE__) . '/../../classes/reports/DoajPaReportPdf.inc.php');
require_once(dirname(__FILE__) . '/../../classes/reports/DoajPaReportDocx.inc.php');
require_once(dirname(__FILE__) . '/../../classes/framework/DoajPaAssessmentOrchestrator.inc.php');

class DoajPaReportFixture {

	/** @return array run detail of one fixture journal */
	public static function run($site, $pages = null) {
		$c = DoajPaRuleFixture::context($pages !== null ? $pages : DoajPaRuleFixture::pages($site), array('site' => $site));
		$o = DoajPaRuleFixture::evaluate($c);
		$spec = array();
		foreach (DoajPaCandidateDataset::data()['criteria'] as $x) $spec[$x['key']] = $x;
		$evidence = array();
		foreach ($c->evidence() as $e) $evidence[$e['id']] = $e;
		$results = array(); $observations = array(); $finding = 0;
		foreach ($o as $key => $out) {
			$first = $out['evidenceIds'] ? $out['evidenceIds'][0] : null;
			$results[] = array('criterion_key' => $key, 'level' => $spec[$key]['level'], 'title' => $spec[$key]['title'], 'machine_status' => $out['status'],
				'rationale' => $out['rationale'], 'interpretation_limit' => $out['interpretationLimit'], 'human_status' => null, 'doaj_reference_url' => $spec[$key]['doajReferenceUrl'],
				'dataset_version' => DoajPaCandidateDataset::VERSION, 'evidence_id' => $first, 'source_url' => $first !== null ? $evidence[$first]['url'] : null);
			foreach ($out['detections'] as $d) {
				$finding++;
				$observations[] = array('finding_id' => $finding, 'observation' => 'detected', 'lifecycle_status' => 'open', 'criterion_status' => $d['criterionStatus'],
					'criterion_version' => 1, 'evidence_id' => $d['evidenceId'], 'criterion_key' => $key, 'subtype' => $d['subtype'], 'target_type' => $d['targetType'], 'target' => $d['target']);
			}
		}
		$resources = array();
		foreach ($c->resources() as $r) $resources[] = array('url' => $r['url'], 'acquisition_status' => $r['acquisition'], 'skip_reason' => $r['reason']);
		$ev = array();
		foreach ($evidence as $e) $ev[] = array('evidence_id' => $e['id'], 'source_url' => $e['url'], 'quote' => $e['quote']);
		$form = array();
		foreach (DoajPaRuleFixture::formGuide($c, $o) as $a) $form[] = array('question_key' => $a['questionKey'], 'answer_status' => $a['answerStatus'], 'answer_text' => $a['answerText'], 'uncertainty' => $a['uncertainty']);
		$counts = array();
		foreach ($o as $out) $counts[$out['status']] = isset($counts[$out['status']]) ? $counts[$out['status']] + 1 : 1;
		return array('run_id' => 7, 'run_number' => 3, 'status' => 'completed', 'rulebook_version' => DoajPaSchema::CANDIDATE_RULEBOOK, 'rulebookApproved' => false,
			'extraction_version' => 'fixture', 'executor_username' => 'jm_test', 'started_at_utc' => '2026-10-01 11:50:00', 'completed_at_utc' => '2026-10-01 12:00:00',
			'snapshot_digest' => str_repeat('a', 64), 'snapshotVerified' => true, 'previousRunNumber' => null, 'resources' => $resources, 'evidence' => $ev,
			'results' => $results, 'observations' => $observations, 'formAnswers' => $form, 'languageNotVerifiedCount' => 0,
			'coverageData' => array('homepage' => 'http://fixtures:8080/s3/index.php/' . $site, 'limitsHit' => array(), 'pdfTool' => 'pdftotext 25.03.0',
				'assessment' => array('candidateDataset' => array('version' => DoajPaCandidateDataset::VERSION, 'status' => 'proposed', 'approval' => 'PENDING APPROVAL (D-03-1)', 'engine' => DoajPaCandidateRule::ENGINE_VERSION),
					'report' => array('candidate' => true, 'disclaimer' => DoajPaAssessmentOrchestrator::CANDIDATE_DISCLAIMER))));
	}

	/** @return array DoajPaReportModel input for a run detail */
	public static function input($run, $actions = array()) {
		$quotes = array();
		foreach ($run['evidence'] as $e) $quotes[(int) $e['evidence_id']] = $e['quote'];
		$form = array();
		foreach ($run['formAnswers'] as $a) $form[] = $a + array('title' => null);
		return array('journal' => array('name' => 'Journal of Open Education Research', 'url' => 'http://fixtures:8080/s3/index.php/good'), 'run' => $run, 'actions' => $actions,
			'quotes' => $quotes, 'comparison' => null, 'formGuide' => $form, 'generatedAtUtc' => '2026-10-02 09:00:00', 'generatedBy' => 'jm_test', 'pluginVersion' => '0.8.0.0');
	}

	/** @return string Plain text of an HTML report */
	public static function htmlText($html) {
		$html = preg_replace('#<(style|title)[^>]*>.*?</\1>#s', ' ', $html);
		return html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/td|/th|/dt|/dd|/h[1-3])[^>]*>#', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	/** @return array name => bytes of a ZIP with stored entries (the DOCX writer's format) */
	public static function unzip($zip) {
		$out = array();
		$i = 0;
		while (substr($zip, $i, 4) === "PK\x03\x04") {
			$h = unpack('vver/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/vxlen', substr($zip, $i + 4, 26));
			$name = substr($zip, $i + 30, $h['nlen']);
			$data = substr($zip, $i + 30 + $h['nlen'] + $h['xlen'], $h['csize']);
			if ($h['method'] !== 0 || crc32($data) !== $h['crc']) throw new Exception('bad zip entry ' . $name);
			$out[$name] = $data;
			$i += 30 + $h['nlen'] + $h['xlen'] + $h['csize'];
		}
		return $out;
	}

	/** @return string Text of a DOCX: the w:t runs, one paragraph per line */
	public static function docxText($docx) {
		$files = self::unzip($docx);
		$doc = new DOMDocument();
		if (!$doc->loadXML($files['word/document.xml'])) throw new Exception('document.xml is not well-formed');
		$out = array();
		foreach ($doc->getElementsByTagNameNS(DoajPaReportDocx::W, 'p') as $p) {
			$t = '';
			foreach ($p->getElementsByTagNameNS(DoajPaReportDocx::W, 't') as $x) $t .= $x->textContent;
			$out[] = $t;
		}
		return implode("\n", $out);
	}

	/** @return string Text drawn in a PDF written by DoajPaReportPdf: hex glyph strings through each font's ToUnicode map, or Windows-1252 strings */
	public static function pdfText($pdf) {
		$maps = array();
		if (preg_match('#/Font << /F1 (\d+) 0 R /F2 (\d+) 0 R >>#', $pdf, $fm)) {
			foreach (array('F1' => $fm[1], 'F2' => $fm[2]) as $name => $obj) {
				if (!preg_match('#\n' . $obj . ' 0 obj\n<< /Type /Font /Subtype /Type0 [^\n]*?/ToUnicode (\d+) 0 R#', $pdf, $tm)) continue;
				if (!preg_match('#\n' . $tm[1] . ' 0 obj\n<< /Length (\d+) >>\nstream\n#', $pdf, $sm, PREG_OFFSET_CAPTURE)) continue;
				$cmap = substr($pdf, $sm[0][1] + strlen($sm[0][0]), (int) $sm[1][0]);
				preg_match_all('/<([0-9A-F]{4})> <([0-9A-F]+)>/', $cmap, $pairs, PREG_SET_ORDER);
				foreach ($pairs as $pr) $maps[$name][hexdec($pr[1])] = mb_convert_encoding(hex2bin($pr[2]), 'UTF-8', 'UTF-16BE');
			}
		}
		$cp1252 = array_flip(DoajPaReportPdf::CP1252);
		preg_match_all('#/(F[12]) [0-9.]+ Tf [0-9.\-]+ [0-9.\-]+ Td (<[0-9A-F]*>|\((?:[^\\\\()]|\\\\.)*\)) Tj#s', $pdf, $m, PREG_SET_ORDER);
		$out = array();
		foreach ($m as $x) {
			$u = '';
			if ($x[2][0] === '<') {
				foreach (str_split(substr($x[2], 1, -1), 4) as $g) if ($g !== '') $u .= isset($maps[$x[1]][hexdec($g)]) ? $maps[$x[1]][hexdec($g)] : "\u{FFFD}";
			} else {
				$bytes = preg_replace_callback('/\\\\([0-7]{3}|.)/s', function ($y) { return strlen($y[1]) === 3 ? chr(octdec($y[1])) : $y[1]; }, substr($x[2], 1, -1));
				for ($k = 0; $k < strlen($bytes); $k++) {
					$b = ord($bytes[$k]);
					$u .= mb_convert_encoding(pack('N', isset($cp1252[$b]) ? $cp1252[$b] : $b), 'UTF-8', 'UCS-4BE');
				}
			}
			$out[] = $u;
		}
		return implode("\n", $out);
	}

	/**
	 * Whether every word of $text appears in $haystack in order (whitespace removed from the haystack), which tolerates line
	 * wrapping, words broken across lines and table rows split across pages.
	 */
	public static function containsInOrder($haystack, $text) {
		$h = preg_replace('/\s+/u', '', $haystack);
		$pos = 0;
		foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
			$i = strpos($h, $word, $pos);
			if ($i === false) return false;
			$pos = $i + strlen($word);
		}
		return true;
	}
}
