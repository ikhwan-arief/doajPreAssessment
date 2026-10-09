<?php
require_once __DIR__ . '/../../classes/auth/DoajPaAccessDecision.inc.php';

function doajpa_facts($overrides) {
	return array_merge(array('userId' => 7, 'userDisabled' => false, 'impersonated' => false, 'contextId' => 1, 'isSiteAdmin' => false, 'isJournalManager' => false), $overrides);
}

// [name, facts, expected permit, expected reason]
$cases = array(
	array('root user 1 + verified site admin is permitted', doajpa_facts(array('userId' => 1, 'isSiteAdmin' => true)), true, DoajPaAccessDecision::PERMIT_ROOT_SITE_ADMIN),
	array('user 1 without site-admin role is denied', doajpa_facts(array('userId' => 1, 'isSiteAdmin' => false)), false, DoajPaAccessDecision::DENY_NOT_AUTHORIZED),
	array('site admin other than user 1 is denied', doajpa_facts(array('userId' => 2, 'isSiteAdmin' => true)), false, DoajPaAccessDecision::DENY_NOT_AUTHORIZED),
	array('default Journal manager of this journal is permitted', doajpa_facts(array('isJournalManager' => true)), true, DoajPaAccessDecision::PERMIT_JOURNAL_MANAGER),
	array('user with no qualifying role is denied', doajpa_facts(array()), false, DoajPaAccessDecision::DENY_NOT_AUTHORIZED),
	array('anonymous (null user) is denied', doajpa_facts(array('userId' => null)), false, DoajPaAccessDecision::DENY_ANONYMOUS),
	array('missing userId key is denied', array('userDisabled' => false, 'impersonated' => false, 'contextId' => 1, 'isJournalManager' => true), false, DoajPaAccessDecision::DENY_ANONYMOUS),
	array('string user id "1" is denied (strict types)', doajpa_facts(array('userId' => '1', 'isSiteAdmin' => true)), false, DoajPaAccessDecision::DENY_ANONYMOUS),
	array('user id 0 is denied', doajpa_facts(array('userId' => 0, 'isJournalManager' => true)), false, DoajPaAccessDecision::DENY_ANONYMOUS),
	array('disabled root admin is denied', doajpa_facts(array('userId' => 1, 'isSiteAdmin' => true, 'userDisabled' => true)), false, DoajPaAccessDecision::DENY_USER_STATE),
	array('disabled journal manager is denied', doajpa_facts(array('isJournalManager' => true, 'userDisabled' => true)), false, DoajPaAccessDecision::DENY_USER_STATE),
	array('unknown disabled state is denied', doajpa_facts(array('isJournalManager' => true, 'userDisabled' => null)), false, DoajPaAccessDecision::DENY_USER_STATE),
	array('no journal context is denied even for root', doajpa_facts(array('userId' => 1, 'isSiteAdmin' => true, 'contextId' => null)), false, DoajPaAccessDecision::DENY_NO_CONTEXT),
	array('site context 0 is denied', doajpa_facts(array('isJournalManager' => true, 'contextId' => 0)), false, DoajPaAccessDecision::DENY_NO_CONTEXT),
	array('truthy non-boolean manager flag is denied', doajpa_facts(array('isJournalManager' => 1)), false, DoajPaAccessDecision::DENY_NOT_AUTHORIZED),
	array('truthy non-boolean site-admin flag is denied', doajpa_facts(array('userId' => 1, 'isSiteAdmin' => 'yes')), false, DoajPaAccessDecision::DENY_NOT_AUTHORIZED),
	array('empty facts are denied', array(), false, DoajPaAccessDecision::DENY_ANONYMOUS),
	array('impersonated session of a journal manager is denied (Login as)', doajpa_facts(array('isJournalManager' => true, 'impersonated' => true)), false, DoajPaAccessDecision::DENY_IMPERSONATED),
	array('impersonated session is denied even for root user 1', doajpa_facts(array('userId' => 1, 'isSiteAdmin' => true, 'impersonated' => true)), false, DoajPaAccessDecision::DENY_IMPERSONATED),
	array('unknown impersonation state is denied', doajpa_facts(array('isJournalManager' => true, 'impersonated' => null)), false, DoajPaAccessDecision::DENY_IMPERSONATED),
	array('truthy non-boolean impersonation flag is denied', doajpa_facts(array('isJournalManager' => true, 'impersonated' => 0)), false, DoajPaAccessDecision::DENY_IMPERSONATED),
	array('missing impersonation fact is denied', array('userId' => 1, 'userDisabled' => false, 'contextId' => 1, 'isSiteAdmin' => true), false, DoajPaAccessDecision::DENY_IMPERSONATED),
);
foreach ($cases as $case) {
	list($name, $facts, $permit, $reason) = $case;
	doajpa_test('AccessDecision: ' . $name, function () use ($facts, $permit, $reason) {
		$result = DoajPaAccessDecision::decide($facts);
		doajpa_same($permit, $result['permit'], 'permit');
		doajpa_same($reason, $result['reason'], 'reason');
	});
}
