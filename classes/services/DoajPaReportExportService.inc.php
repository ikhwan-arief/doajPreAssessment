<?php

/**
 * @file classes/services/DoajPaReportExportService.inc.php
 *
 * @class DoajPaReportExportService
 * @brief Report export metadata (Stage 01 F7, F10) and private storage paths (ADR-006). Exports belong
 *  to a completed run and record its snapshot digest. Files live only under OJS files_dir, never under
 *  the public files directory. Stage 04: generate() renders the report of one completed, verified run in one format
 *  (HTML, PDF or DOCX, all from the same DoajPaReportModel) into the private reports directory and records it;
 *  file() returns an export for a guarded download after checking journal, run and the file's SHA-256.
 *
 * Copyright (c) 2026 Ikhwan Arief <ikhwan@unand.ac.id>
 * Distributed under the GNU General Public License, version 3 or later. See the files LICENSE and NOTICE.md.
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAssessmentRunService.inc.php');
require_once(dirname(__FILE__) . '/DoajPaReadService.inc.php');
require_once(dirname(__FILE__) . '/../reports/DoajPaReportModel.inc.php');
require_once(dirname(__FILE__) . '/../reports/DoajPaReportHtml.inc.php');
require_once(dirname(__FILE__) . '/../reports/DoajPaReportPdf.inc.php');
require_once(dirname(__FILE__) . '/../reports/DoajPaReportDocx.inc.php');

class DoajPaReportExportService {

	const FILE_NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,100}\.(html|pdf|docx)$/';
	/** Stage 01 F9 (attachment quotas): at most this many generated reports are kept per run */
	const MAX_EXPORTS_PER_RUN = 30;
	const MEDIA_TYPES = array('html' => 'text/html; charset=utf-8', 'pdf' => 'application/pdf',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

	/**
	 * Input of DoajPaReportModel for one completed run, read from the stored record only (F7: the dashboard and every
	 * report format come from the same stored run).
	 * @param $journal array name, url
	 * @return array
	 */
	public static function modelInput($contextId, $runId, $journal, $actor, $pluginVersion) {
		$run = DoajPaReadService::run($contextId, $runId);
		if ($run['status'] !== 'completed') throw new DoajPaStateException('Reports can be generated only for completed runs.');
		if ($run['snapshotVerified'] !== true) throw new DoajPaStateException('The sealed snapshot of this run does not verify; no report is generated.');
		$quotes = array();
		foreach ($run['evidence'] as $e) $quotes[(int) $e['evidence_id']] = (string) $e['quote'];
		$titles = array();
		foreach (DoajPaReadService::fqTitles($run['rulebook_version']) as $k => $t) $titles[$k] = $t;
		$stored = array();
		foreach ($run['formAnswers'] as $a) $stored[$a['question_key']] = $a;
		$form = array();
		for ($i = 1; $i <= 20; $i++) {
			$k = sprintf('FQ-%02d', $i);
			$a = isset($stored[$k]) ? $stored[$k] : null;
			$form[] = array('question_key' => $k, 'title' => isset($titles[$k]) ? $titles[$k] : null, 'answer_status' => $a ? $a['answer_status'] : 'not_yet_assessed',
				'answer_text' => $a ? $a['answer_text'] : null, 'uncertainty' => $a ? $a['uncertainty'] : null);
		}
		$prev = DoajPaDb::table(DoajPaSchema::T_RUN)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))->where('run_id', '=', (int) $run['run_id'])->value('previous_run_id');
		return array('journal' => $journal, 'run' => $run, 'actions' => DoajPaReadService::actions($contextId), 'quotes' => $quotes,
			'comparison' => $prev ? DoajPaReadService::compare($contextId, (int) $prev, (int) $run['run_id']) : null, 'formGuide' => $form,
			'generatedAtUtc' => DoajPaDb::now(), 'generatedBy' => $actor['username'], 'pluginVersion' => (string) $pluginVersion);
	}

	/** @return string Report bytes of a model in one format */
	public static function render($model, $format, $createdUtc) {
		DoajPaDb::oneOf($format, DoajPaSchema::EXPORT_FORMATS, 'export format');
		if ($format === 'html') return DoajPaReportHtml::render($model);
		if ($format === 'pdf') return DoajPaReportPdf::render($model, $createdUtc);
		return DoajPaReportDocx::render($model);
	}

	/**
	 * Generate, store privately and record the report of a completed run (Stage 04, AC-18).
	 * @param $filesDir string OJS files_dir (absolute, outside the web root)
	 * @return int export_id
	 */
	public static function generate($contextId, $runId, $format, $journal, $actor, $pluginVersion, $filesDir) {
		DoajPaDb::oneOf($format, DoajPaSchema::EXPORT_FORMATS, 'export format');
		$existing = (int) DoajPaDb::table(DoajPaSchema::T_EXPORT)->where('context_id', '=', DoajPaDb::id($contextId, 'context'))->where('run_id', '=', DoajPaDb::id($runId, 'run'))->count();
		if ($existing >= self::MAX_EXPORTS_PER_RUN) throw new DoajPaQuotaException('The report limit of this run is reached (' . self::MAX_EXPORTS_PER_RUN . ' reports); download an existing report.');
		$in = self::modelInput($contextId, $runId, $journal, $actor, $pluginVersion);
		$model = DoajPaReportModel::build($in);
		$bytes = self::render($model, $format, $in['generatedAtUtc']);
		$fileName = $model['fileStem'] . '-' . preg_replace('/[^0-9]/', '', $in['generatedAtUtc']) . '-' . substr(hash('sha256', $bytes), 0, 8) . '.' . $format;
		$path = self::privatePath($filesDir, $contextId, (int) $in['run']['run_id'], $fileName);
		$dir = dirname($path);
		if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new DoajPaStateException('The private reports directory could not be created.');
		$tmp = $dir . '/.' . $fileName . '.' . bin2hex(random_bytes(6)) . '.tmp';
		if (file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes) || !chmod($tmp, 0600) || !rename($tmp, $path)) {
			if (is_file($tmp)) unlink($tmp);
			throw new DoajPaStateException('The report file could not be written.');
		}
		return self::record($contextId, (int) $in['run']['run_id'], $format, $fileName, hash('sha256', $bytes), strlen($bytes), $actor);
	}

	/**
	 * An export of this journal for download: the row, its private path and media type. The file must exist and still
	 * have the recorded SHA-256 (a changed file is never served).
	 * @return array exportId, runId, format, fileName, path, mediaType, sha256, bytes
	 */
	public static function file($contextId, $exportId, $filesDir) {
		$ctx = DoajPaDb::id($contextId, 'context');
		$row = DoajPaDb::table(DoajPaSchema::T_EXPORT)->where('context_id', '=', $ctx)->where('export_id', '=', DoajPaDb::id($exportId, 'export'))->first();
		if (!$row) throw new DoajPaNotFoundException('Report not found.');
		DoajPaAssessmentRunService::getRun($ctx, (int) $row->run_id); // the run belongs to this journal
		$path = self::privatePath($filesDir, $ctx, (int) $row->run_id, (string) $row->file_name);
		if (!is_file($path)) throw new DoajPaNotFoundException('Report file not found.');
		$bytes = file_get_contents($path);
		if ($bytes === false || !hash_equals((string) $row->file_sha256, hash('sha256', $bytes))) throw new DoajPaStateException('The report file does not match its recorded hash.');
		return array('exportId' => (int) $row->export_id, 'runId' => (int) $row->run_id, 'format' => (string) $row->format, 'fileName' => (string) $row->file_name,
			'path' => $path, 'mediaType' => self::MEDIA_TYPES[(string) $row->format], 'sha256' => (string) $row->file_sha256, 'bytes' => $bytes);
	}

	/** @return int export_id */
	public static function record($contextId, $runId, $format, $fileName, $fileSha256, $byteLength, $actor) {
		DoajPaDb::oneOf($format, DoajPaSchema::EXPORT_FORMATS, 'export format');
		self::requireFileName($fileName, $format);
		if (!is_string($fileSha256) || !preg_match('/^[0-9a-f]{64}$/', $fileSha256)) throw new DoajPaValidationException('Invalid file hash.');
		if (!is_int($byteLength) || $byteLength < 0) throw new DoajPaValidationException('Invalid file size.');
		return DoajPaDb::transaction(function () use ($contextId, $runId, $format, $fileName, $fileSha256, $byteLength, $actor) {
			$run = DoajPaAssessmentRunService::getRun($contextId, $runId);
			if ($run->status !== 'completed' || $run->snapshot_digest === null) {
				throw new DoajPaStateException('Reports can be recorded only for completed runs.');
			}
			$id = (int) DoajPaDb::table(DoajPaSchema::T_EXPORT)->insertGetId(array(
				'context_id' => $contextId, 'run_id' => $runId, 'format' => $format, 'rulebook_version' => $run->rulebook_version,
				'run_snapshot_digest' => $run->snapshot_digest, 'file_name' => $fileName, 'file_sha256' => $fileSha256,
				'byte_length' => $byteLength, 'created_by_user_id' => $actor['userId'], 'created_at_utc' => DoajPaDb::now(),
			));
			DoajPaAuditService::record($contextId, 'report_export_recorded', $actor, $runId, 'report_export', $id, array('format' => $format));
			return $id;
		});
	}

	/**
	 * Absolute private path of an export file: {files_dir}/doajPreAssessment/{context}/reports/{run}/{file}.
	 * @return string
	 */
	public static function privatePath($filesDir, $contextId, $runId, $fileName) {
		DoajPaDb::id($contextId, 'context');
		DoajPaDb::id($runId, 'run');
		self::requireFileName($fileName, null);
		if (!is_string($filesDir) || $filesDir === '' || $filesDir[0] !== '/') throw new DoajPaValidationException('files_dir must be an absolute path.');
		return rtrim($filesDir, '/') . '/doajPreAssessment/' . $contextId . '/reports/' . $runId . '/' . $fileName;
	}

	private static function requireFileName($fileName, $format) {
		if (!is_string($fileName) || !preg_match(self::FILE_NAME_PATTERN, $fileName, $m)) throw new DoajPaValidationException('Invalid export file name.');
		if ($format !== null && $m[1] !== $format) throw new DoajPaValidationException('File extension does not match the format.');
	}
}
