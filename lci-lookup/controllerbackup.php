<?php

namespace lci\forms;

use \sa\application\controller;
use \sa\application\Responses\View;
use \sa\application\Responses\Redirect;
use \sa\application\Responses\Json;
use \sa\utilities\url;
use \sa\utilities\DataValidator;
use \sa\utilities\notification;
use \lci\forms\CPDRDatabaseProvider;
use \sa\application\modRequest;

class CPDRController extends controller
{
	private static $longListTableName = '[Corepoint].[dbo].[Provider Legacy ID and NPI - Long List]';
	private static $shortListTableName = '[Corepoint].[dbo].[Provider Legacy ID and NPI - Short List]';

	public function form()
	{
		$member = modRequest::request('auth.member');

		if(!static::canAccessForm($member)) {
			return $this->error404(true);
		}

		$view = new View('master', 'cpdrlist_form', static::viewLocation());

		return $view;
	}

	public function submitCPDRForm()
	{
		$member = modRequest::request('auth.member');

		if(!static::canAccessForm($member)) {
			return $this->error404(true);
		}

		$legacyId = str_replace('_', '', $_POST['legacy_id']);
		$npi = str_replace('_', '', $_POST['npi']);
		$lineDescription = $_POST['line_description'];

		$dv = new DataValidator();

		$dv->isNotEmpty($legacyId, 'Please specify the Legacy ID');
		$dv->isNotEmpty($npi, 'Please specify the NPI');
		$dv->isNotEmpty($lineDescription, 'Please specify the Line Description');

		if(strlen($legacyId) < 3) {
			$dv->addError('Please provide 3 characters for Legacy ID');
		}

		if(strlen($npi) < 10) {
			$dv->addError('Please provide a 10 digit NPI.');
		}

		if($dv->hasErrors()) {
			$notify = new notification();
			$notify->addNotification('danger', 'Error', 'There were one or more errors with your submission: <br /><br />' . implode('<br />', $dv->getErrors()));

			$view = new View('master', 'cpdrlist_form', static::viewLocation());
			$view->data['legacy_id'] = $legacyId;
			$view->data['npi'] = $npi;
			$view->data['line_description'] = $lineDescription;

			return $view;
		}

		$longListMatches = static::checkForExistingRecords($legacyId, $npi, static::$longListTableName);
		$shortListMatches = static::checkForExistingRecords($legacyId, $npi, static::$shortListTableName);

		if((count($longListMatches) || count($shortListMatches)) && !$_POST['delete-existing-records']) {
			$view = new View('master', 'cpdrlist_form', static::viewLocation());
			$view->data['legacy_id'] = $legacyId;
			$view->data['npi'] = $npi;
			$view->data['line_description'] = $lineDescription;
			$view->data['has_duplicates'] = true;
			$view->data['longListMatches'] = $longListMatches;
			$view->data['shortListMatches'] = $shortListMatches;

			return $view;
		}

		if($_POST['delete-existing-records']) {
			static::delete([
				'legacy_id' => $legacyId,
				'npi' => $npi,
				'line_description' => $lineDescription
			]);
		}

		/**
		 * RULES FOR TABLE INSERTION
		 * --------------------------
	     *
	     * - If $legacyId is only alpha characters, the data goes into the LongList table
	     * - Numeric $legacyId is added to both ShortList and LongList tables
	     * 
		 */

		if(preg_match('/[a-zA-Z]+/', $legacyId)) {
			$this->insertIntoTable(static::$longListTableName, $legacyId, $npi, $lineDescription);
		} else if(preg_match('/[0-9]+/', $legacyId)) {
			$this->insertIntoTable(static::$longListTableName, $legacyId, $npi, $lineDescription);
			$this->insertIntoTable(static::$shortListTableName, $legacyId, $npi, $lineDescription);
		}

		$this->logInsert($legacyId, $npi, $lineDescription);

		$notify = new notification();
		$notify->addNotification('success', 'Success', 'Your form submission was successful!');

		return new Redirect(url::make('cpdr_list_form'));
	}

	private function insertIntoTable($table, $legacyId, $npi, $lineDescription)
	{
		$connection = CPDRDatabaseProvider::Instance();

		$legacyId = str_replace('_', '', $legacyId);
		$legacyId = strtoupper($legacyId);
		$npi = str_replace('_', '', $npi);

		$qb = $connection->createQueryBuilder();
		$qb->insert($table);
		$qb->values([
			'[Provider Legacy ID]' => ':legacyId',
			'[Provider NPI]' => ':npi',
			'[Line Description]' => ':lineDescription'
		]);

		$qb->setParameters([
			':legacyId' => $legacyId,
			':npi' => $npi,
			':lineDescription' => $lineDescription
		]);

		$stmt = $qb->execute();
	}

	private function logInsert($legacyId, $npi, $lineDescription)
	{
		$user = modRequest::request('auth.user');
		$connection = CPDRDatabaseProvider::Instance();
		$now = new \DateTime();

		$qb = $connection->createQueryBuilder();
		$qb->insert('cpdr_form_log');
		$qb->values([
			'user_name' => ':userName',
			'legacy_id' => ':legacyId',
			'npi' => ':npi',
			'line_description' => ':lineDescription',
			'submit_date' => ':submitDate',
			'action' => ':insert'
		]);

		$qb->setParameters([
			':userName' => $user->getUsername(),
			':legacyId' => $legacyId,
			':npi' => $npi,
			':lineDescription' => $lineDescription,
			':submitDate' => $now->format('Y-m-d H:i:s'),
			':insert' => 'CREATE_ACTION'
		]);

		$stmt = $qb->execute();
	}

	private function logDelete($legacyId, $npi, $lineDescription)
	{
		$user = modRequest::request('auth.user');
		$connection = CPDRDatabaseProvider::Instance();
		$now = new \DateTime();

		$qb = $connection->createQueryBuilder();
		$qb->insert('cpdr_form_log');
		$qb->values([
			'user_name' => ':userName',
			'legacy_id' => ':legacyId',
			'npi' => ':npi',
			'line_description' => ':lineDescription',
			'submit_date' => ':submitDate',
			'action' => ':remove'
		]);

		$qb->setParameters([
			':userName' => $user->getUsername(),
			':legacyId' => $legacyId,
			':npi' => $npi,
			':lineDescription' => $lineDescription,
			':submitDate' => $now->format('Y-m-d H:i:s'),
			':remove' => 'DELETE_ACTION'
		]);

		$stmt = $qb->execute();
	}

	private static function canAccessForm($member)
	{
		$allowedGroups = ['CPDRList'];
		$allowed = false;

		foreach($member->getGroups() as $group) {
			if(in_array($group->getName(), $allowedGroups)) {
				$allowed = true;
			}
		}

		$user = modRequest::request('auth.user');

		// Developer Access
		if($user->getUsername() == 'vend-elink') {
			$allowed = true;
		}

		return $allowed;
	}

	public function lookup($data)
	{
		$member = modRequest::request('auth.member');

		if(!static::canAccessForm($member)) {
			$json = new Json();
			$json->data['msg'] = 'Unauthorized';
			$json->data['success'] = false;

			return $json;
		}

		return static::performLookup($data['query'], $data['searchType']);
	}

	private static function performLookup($query, $col)
	{
		if($col != 'legacy_id' && $col != 'npi') {
			throw new \Exception('NOT ALLOWED');
		}

		if($col == 'legacy_id') {
			$col = '[Provider Legacy ID]';
		} else {
			$col = '[Provider NPI]';
		}

		$json = new Json();
		$json->data['match'] = static::lookupQuery(static::$longListTableName, $query, $col);

		return $json;
	}

	private static function lookupQuery($table, $query, $col)
	{
		$connection = CPDRDatabaseProvider::Instance();
		
		$qb = $connection->createQueryBuilder();
		$qb->select('*');
		$qb->from($table);
		
		$qb->where('LTRIM(RTRIM(' . $col . '))' . ' = ' . ':query');
		$qb->setParameter(':query', $query);

		$stmt = $qb->execute();

		return $stmt->fetch(\PDO::FETCH_ASSOC);
	}

	public static function delete($data)
	{
		$member = modRequest::request('auth.member');

		if(!static::canAccessForm($member)) {
			$json = new Json();
			$json->data['msg'] = 'Unauthorized';
			$json->data['success'] = false;
			
			return $json;
		}

		static::deleteQuery(static::$longListTableName, $data['legacy_id'], $data['npi']);
		static::deleteQuery(static::$shortListTableName, $data['legacy_id'], $data['npi']);

		static::logDelete($data['legacy_id'], $data['npi'], $data['line_description']);
	}

	private static function deleteQuery($table, $legacyId, $npi)
	{
		$connection = CPDRDatabaseProvider::Instance();

		$qb = $connection->createQueryBuilder();
		$qb->delete($table);

		$qb->where('[Provider Legacy ID] = :legacyId');
		$qb->orWhere('[Provider NPI] = :npi');
		
		$qb->setParameter(':legacyId', $legacyId);
		$qb->setParameter(':npi', $npi);

		$stmt = $qb->execute();
	}

	private static function checkForExistingRecords($legacyId, $npi, $tableName)
	{
		$matches = [];
		$connection = CPDRDatabaseProvider::Instance();

		$qb = $connection->createQueryBuilder();
		$qb->select('*');
		$qb->from($tableName);
		$qb->where('[Provider Legacy ID] = :legacyId');
		$qb->orWhere('[Provider NPI] = :npi');

		$qb->setParameters([
			':legacyId' => $legacyId,
			':npi' => $npi
		]);

		$stmt = $qb->execute();

		$matches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		return $matches;
	}
}