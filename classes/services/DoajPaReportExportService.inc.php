<?php

/**
 * @file classes/services/DoajPaReportExportService.inc.php
 *
 * @class DoajPaReportExportService
 * @brief Report export metadata (Stage 01 F7, F10) and private storage paths (ADR-006). Exports belong
 *  to a completed run and record its snapshot digest. Files live only under OJS files_dir, never under
 *  the public files directory. Generating files is Stage 04; this stage stores metadata only.
 */

require_once(dirname(__FILE__) . '/../persistence/DoajPaDb.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAuditService.inc.php');
require_once(dirname(__FILE__) . '/DoajPaAssessmentRunService.inc.php');

class DoajPaReportExportService {

	const FILE_NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,100}\.(html|pdf|docx)$/';

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
