<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.1
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: EntExt
 * The Initial Developer of the Original Code is EntExt.
 * All Rights Reserved.
 * If you have any questions or comments, please email: devel@entext.com
 ************************************************************************************/

class Settings_EERainbowList_ListView_Model extends Settings_Vtiger_ListView_Model {

    /**
     * Function to get the list view entries
     * @param Vtiger_Paging_Model $pagingModel
     * @return array <Array> - Associative array of record id mapped to Vtiger_Record_Model instance.
     * @throws AppException
     * @throws Exception
     */
	public function getListViewEntries($pagingModel) {
		$db = PearDatabase::getInstance();

		$module = $this->getModule();
		$moduleName = $module->getName();
		$parentModuleName = $module->getParentName();
		$qualifiedModuleName = $moduleName;
		if(!empty($parentModuleName)) {
			$qualifiedModuleName = $parentModuleName.':'.$qualifiedModuleName;
		}
		$recordModelClass = Vtiger_Loader::getComponentClassName('Model', 'Record', $qualifiedModuleName);

		$listFields = $module->listFields;
		$listQuery = "SELECT ";
		foreach ($listFields as $fieldName => $fieldLabel) {
			$listQuery .= "$fieldName, ";
		}
		$listQuery .= $module->baseIndex . " FROM ". $module->baseTable;

		$params = array();
		$sourceModule = $this->get('sourceModule');
		if(!empty($sourceModule)) {
			$listQuery .= ' WHERE module_name = ?';
			$params[] = $sourceModule;
		}

		$startIndex = $pagingModel->getStartIndex();
		$pageLimit = $pagingModel->getPageLimit();

		$orderBy = $this->getForSql('orderby');
		if (!empty($orderBy) && $orderBy === 'smownerid') { 
			$fieldModel = Vtiger_Field_Model::getInstance('assigned_user_id', $module);
			if ($fieldModel->getFieldDataType() == 'owner') { 
				$orderBy = 'COALESCE(CONCAT(vtiger_users.first_name,vtiger_users.last_name),vtiger_groups.groupname)'; 
			} 
		}
		if(!empty($orderBy)) {
			$listQuery .= ' ORDER BY '. $orderBy . ' ' .$this->getForSql('sortorder');
		}
        $nextListQuery = $listQuery.' LIMIT '.($startIndex+$pageLimit).',1';
		$listQuery .= " LIMIT $startIndex,".($pageLimit+1);

		$listResult = $db->pquery($listQuery, $params);
		$noOfRecords = $db->num_rows($listResult);

		$listViewRecordModels = array();
		for($i=0; $i<$noOfRecords; ++$i) {
			$row = $db->query_result_rowdata($listResult, $i);
			$record = new $recordModelClass();
			$module_name = $row['module_name'];
			
			//To handle translation of calendar to To Do
			if($module_name == 'Calendar'){
				$module_name = vtranslate('LBL_TASK', $module_name);
			}else{
				$module_name = vtranslate($module_name, $module_name);
			}
			
			$row['module_name'] = $module_name;
			$record->setData($row);
			$listViewRecordModels[$record->getId()] = $record;
		}
		$pagingModel->calculatePageRange($listViewRecordModels);
		
		if($db->num_rows($listResult) > $pageLimit){
			array_pop($listViewRecordModels);
			$pagingModel->set('nextPageExists', true);
		}else{
			$pagingModel->set('nextPageExists', false);
		}
		
        $nextPageResult = $db->pquery($nextListQuery, $params);
        $nextPageNumRows = $db->num_rows($nextPageResult);
        if($nextPageNumRows <= 0) {
            $pagingModel->set('nextPageExists', false);
        }
		return $listViewRecordModels;
	}

    /**
     * Function which will get the list view count
     * @return mixed|string - number of records
     * @throws Exception
     */
	public function getListViewCount() {
		$db = PearDatabase::getInstance();

		$module = $this->getModule();
		$listQuery = 'SELECT count(*) AS count FROM ' . $module->baseTable;

		$sourceModule = $this->get('sourceModule');
		if($sourceModule) {
			$listQuery .= " WHERE module_name = '$sourceModule'";
		}

		$listResult = $db->pquery($listQuery, array());
		return $db->query_result($listResult, 0, 'count');
	}
}