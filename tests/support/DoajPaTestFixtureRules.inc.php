<?php

/**
 * @file tests/support/DoajPaTestFixtureRules.inc.php
 *
 * @brief TEST ONLY - NEVER PACKAGED (tools/build-package.sh excludes tests/). Fake criterion rules that exercise
 *  the Stage 02.07 framework. They are NOT DOAJ criteria: their dataset is TEST-FIXTURE-NOT-DOAJ-0207, their keys
 *  start with "test.fixture.", and DoajPaAssessmentOrchestrator accepts them only through its command-line test
 *  entry points, for runs created with TEST_RULEBOOK. Live runs use DoajPaRuleRegistry::liveRules() (empty).
 */

abstract class DoajPaTestFixtureRule implements DoajPaCriterionRule {
	/** @var array criterion key => number of evaluate() calls in this process */
	public static $calls = array();
	public function version() { return 1; }
	public function lineage() { return 1; }
	public function level() { return 'M'; }
	public function datasetVersion() { return 'TEST-FIXTURE-NOT-DOAJ-0207'; }
	public function sourceVersion() { return 'test-fixture'; }
	public function doajReferenceUrl() { return 'https://doaj.org/apply/guide/'; }
	public function humanReviewBoundary() { return 'Test fixture rule: not a DOAJ criterion; no judgement is made.'; }
	public function recommendationReference() { return 'test-fixture'; }
	public function evaluate($context) {
		self::$calls[$this->key()] = isset(self::$calls[$this->key()]) ? self::$calls[$this->key()] + 1 : 1;
		return $this->outcome($context);
	}
	abstract protected function outcome($context);
}

/** Looks for a fixed phrase on the author guidelines page; pass only with a verbatim quote. */
class DoajPaTestRuleQuote extends DoajPaTestFixtureRule {
	const PHRASE = 'peer reviewed research articles under an open access policy';
	public function key() { return 'test.fixture.quote'; }
	public function title() { return 'TEST FIXTURE: guidelines page contains the fixture phrase'; }
	public function requiredEvidence() { return array('author_guidelines'); }
	protected function outcome($context) {
		foreach ($context->evidence(array('author_guidelines')) as $e) {
			if (strpos($e['quote'], self::PHRASE) !== false) {
				return array('status' => 'pass', 'rationale' => 'The fixture phrase is present.', 'evidenceIds' => array($e['id']),
					'quotes' => array(array('evidenceId' => $e['id'], 'text' => self::PHRASE)));
			}
		}
		return array('status' => 'unverifiable', 'rationale' => 'The author guidelines page was not read in this run.');
	}
}

/** Reports one deficiency on the About page (a finding), with evidence. */
class DoajPaTestRuleDetect extends DoajPaTestFixtureRule {
	public function key() { return 'test.fixture.detect'; }
	public function title() { return 'TEST FIXTURE: About page lacks the word "complaints"'; }
	public function requiredEvidence() { return array('about'); }
	protected function outcome($context) {
		foreach ($context->evidence(array('about')) as $e) {
			if (stripos($e['quote'], 'complaints') === false) {
				return array('status' => 'fail', 'rationale' => 'Fixture deficiency.', 'evidenceIds' => array($e['id']),
					'detections' => array(array('subtype' => 'missing-word', 'targetType' => 'page', 'target' => $e['url'],
						'criterionStatus' => 'fail', 'evidenceId' => $e['id'])));
			}
		}
		return array('status' => 'unverifiable', 'rationale' => 'The About page was not read in this run.');
	}
}

/** Claims "pass" from a page whose language is NOT verified English: must be downgraded to unverifiable. */
class DoajPaTestRuleLanguage extends DoajPaTestFixtureRule {
	public function key() { return 'test.fixture.language'; }
	public function title() { return 'TEST FIXTURE: pass claimed from undetermined-language text'; }
	public function requiredEvidence() { return array('custom'); }
	protected function outcome($context) {
		foreach ($context->evidence(array('custom')) as $e) {
			if ($e['language'] === 'undetermined') {
				$quote = mb_substr($e['quote'], 0, 30, 'UTF-8');
				return array('status' => 'pass', 'rationale' => 'Claimed pass from undetermined-language page.', 'evidenceIds' => array($e['id']),
					'quotes' => array(array('evidenceId' => $e['id'], 'text' => $quote)),
					'detections' => array(array('subtype' => 'language-check', 'targetType' => 'page', 'target' => $e['url'],
						'criterionStatus' => 'warning', 'evidenceId' => $e['id'])));
			}
		}
		return array('status' => 'not_applicable', 'rationale' => 'No undetermined-language page in this run.');
	}
}

/** Claims "pass" with a quote that is not in the evidence: must be downgraded to unverifiable. */
class DoajPaTestRuleFabricated extends DoajPaTestFixtureRule {
	public function key() { return 'test.fixture.fabricated'; }
	public function title() { return 'TEST FIXTURE: fabricated quote'; }
	public function requiredEvidence() { return array('about'); }
	protected function outcome($context) {
		$about = $context->evidence(array('about'));
		$id = $about ? $about[0]['id'] : 999999999;
		return array('status' => 'pass', 'rationale' => 'Fabricated.', 'evidenceIds' => array($id),
			'quotes' => array(array('evidenceId' => $id, 'text' => 'This journal is indexed in every major database.')));
	}
}

/** Throws: the result must be unverifiable, not a crash and not a pass. */
class DoajPaTestRuleThrows extends DoajPaTestFixtureRule {
	public function key() { return 'test.fixture.throws'; }
	public function title() { return 'TEST FIXTURE: rule that fails'; }
	public function requiredEvidence() { return array('home'); }
	protected function outcome($context) {
		throw new RuntimeException('fixture failure');
	}
}

/** A rule that claims to be live data: used to prove test runs refuse non-fixture datasets. */
class DoajPaTestRuleWrongDataset extends DoajPaTestRuleQuote {
	public function key() { return 'test.fixture.wrongdataset'; }
	public function datasetVersion() { return 'DOAJ-2026'; }
}

/** @return DoajPaCriterionRule[] by name */
function doajpa_test_fixture_rules($names) {
	$map = array('quote' => 'DoajPaTestRuleQuote', 'detect' => 'DoajPaTestRuleDetect', 'language' => 'DoajPaTestRuleLanguage',
		'fabricated' => 'DoajPaTestRuleFabricated', 'throws' => 'DoajPaTestRuleThrows', 'wrongdataset' => 'DoajPaTestRuleWrongDataset');
	$rules = array();
	foreach ($names as $n) $rules[] = new $map[$n]();
	return $rules;
}
