<?php

class tx_wecdiscussion_convert {
	function getinfo() {
		$ttnum = $this->conf['ttconvert'] ? $this->conf['ttconvert'] : 997;
		$formContent = '
		<div class="tx-wecdiscussion-form">
			<form name="convertform" method="GET" style="border:2px solid #777;" action="'.$this->pi_getPageLink($GLOBALS["TSFE"]->id).'">
				<input type="hidden" name="ttconvert" value="'.$ttnum.'">
				<div class="inputFormRow"><span class="textacross"><h3>TimTab->WEC Discussion Converter</h3></span></div>
				<div class="inputFormRow"><span class="label" style="width:70%;">Enter storage page ID to convert from (where Timtab data is):</span><span class="inputBox" style="width:28%;"><input type="text" size="5" name="pidfrom" style="width:60px;" value="'.t3lib_div::_GET('pidfrom').'"></span></div>
				<div class="inputFormRow"><span class="label" style="width:70%;">Enter storage page ID to convert to: (where new data will be):<br /><span style="font-size:75%">If blank, will be on same page</span></span><span class="inputBox" style="width:28%;"><input type="text" size="5" name="pidto"  style="width:60px;" value="'.t3lib_div::_GET('pidto').'"></span></div>
				<div class="inputFormRow"><span class="label" style="width:70%;">Do you want to delete all wec_discussion data on the new storage page?</span><span class="inputBox" style="width:28%;text-align:left;"><input type="checkbox" name="deldata" style="width:20px;"></span></div>
				<div class="inputFormRow"><span class="textacross"><input type="submit" value="Convert TimTab Data"></span></div>
				<div style="clear:both;height:0.1em;">&nbsp;</div>
			</form>
		</div>	';

		return $formContent;
	}
	function convertTimTab($fromPageID, $toPageID, $deleteOld) {
		if (!$fromPageID) {
			return tx_wecdiscussion_convert::getinfo();
		}
		if ($toPageID == 0) {
			$toPageID = $fromPageID;
		}

		if ($deleteOld == 'on') {
			$where = 'pid IN ('.$toPageID.')';
			$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_wecdiscussion_post', $where);
			$res = $GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_wecdiscussion_category', $where);
		}

		// ERROR CHECK: make sure both page IDs exist
		$where = 'uid='.$fromPageID.' AND deleted=0';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', $where, '', '');
		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res))
			return '<p><b>The FROM page ID '.$fromPageID.' does not exist. Please re-enter.</b></p>'. tx_wecdiscussion_convert::getinfo();

		if ($toPageID != $fromPageID) {
			$where = 'uid='.$toPageID.' AND deleted=0';
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'pages', $where, '', '');
			if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res))
				return '<p><b>The TO page ID '.$toPageID.' does not exist. Please re-enter.</b></p>' . tx_wecdiscussion_convert::getinfo();
		}

		//************************************************************
		// FIRST: Process Categories...then Records
		//************************************************************
		$ttnewscat_fields = array('pid','tstamp','crdate','title','image','description','sorting', 'hidden');
		$wecdevcat_fields = array('pid','tstamp','crdate','name','image','description','sort_order','hidden');

		// 1. read in all TIMTAB category records from a given page id
		//_________________________________________________________
		$where = 'pid IN('.$fromPageID.')';
		$where .= ' AND deleted=0';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_news_cat', $where, '', '');
		if (mysql_error()) t3lib_div::debug(array(mysql_error(), $res));
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$oldCategories[$row['uid']] = $row;
		}

		if (count($oldCategories)) {
			// 2. read in all category_mm records to match categories
			//_________________________________________________________
			$where = '1=1';
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_news_cat_mm', $where, '', '');
			if (mysql_error()) t3lib_div::debug(array(mysql_error(), $res));
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$oldCatLookup[$row['uid_local']] = $row['uid_foreign'];
			}

			// 3. Convert all records to wec_discussion_category format
			//_________________________________________________________
			$i = 0;
			foreach ($oldCategories as $key => $value) {
				for ($j  = 0; $j < count($ttnewscat_fields); $j++) {
					$newCategories[$i][$wecdevcat_fields[$j]] = $oldCategories[$key][$ttnewscat_fields[$j]];
				}
				$newCategories[$i]['uid'] = $oldCatLookup[$oldCategories[$key]['uid']];
				$newCategories[$i]['pid'] = $toPageID ? $toPageID : $oldData[$i]['pid'];
				$i++;
			}

			// 4. Insert New WEC_DISCUSSION_CATEGORY
			//_________________________________________________________
			$valStr = '';
			for ($i = 0; $i < count($wecdevcat_fields); $i++) {
				$valStr .= $wecdevcat_fields[$i];
				if ($i != (count($wecdevcat_fields)-1)) $valStr .= ',';
			}

			$dataStr = '';
			for ($i = 0; $i < count($newCategories); $i++) {
				$dataStr .= '(';
				for ($j = 0; $j < count($wecdevcat_fields); $j++) {
					$val = $newCategories[$i][$wecdevcat_fields[$j]];
					if (!is_numeric($val)) $val = '"'.htmlspecialchars($val).'"';
					$dataStr .=  $val;
					if ($j != (count($wecdevcat_fields)-1)) $dataStr .= ',';
				}
				$dataStr .= ')';
				if ($i != (count($newCategories)-1)) $dataStr .= ',';
			}
			$insertCatStr = 'INSERT INTO tx_wecdiscussion_category ('.$valStr.') VALUES '.$dataStr;
			$insert = $GLOBALS['TYPO3_DB']->sql(TYPO3_db, $insertCatStr);

			// 5. Grab all WEC_DISCUSSION_CATEGORY uid's so can update records with right category
			//________________________________________________________
			$where = 'pid IN('.$toPageID.') AND deleted=0';
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_wecdiscussion_category', $where, '', 'uid');
			if (mysql_error()) t3lib_div::debug(array(mysql_error(), $res));
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$newCat[$row['uid']] = $row;
			}

			// 6. Match old categories with new categories
			//________________________________________________________
			foreach ($newCat as $key => $value) {
				foreach ($oldCatLookup as $oldKey => $oldVal) {
					if (!strcmp($key,$oldKey)) {
						$oldToNewCat[$oldKey] = $key;
						break;
					}
				}
			}
		}

		//************************************************************
		// SECOND: Process Post/Entry Records
		//************************************************************

		// 1. read in all TIMTAB records from a given page id
		//_________________________________________________________
		$where = 'pid IN ('.$fromPageID.') AND type=3 AND deleted=0';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tt_news', $where, '','');
		if (mysql_error()) t3lib_div::debug(array(mysql_error(),$res));
		$oldData = array();
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))
				$oldData[] = $row;
		}

		// 2. convert all records to wec_discussion format
		//_________________________________________________________

		$ttnews_fields = array('pid','title','datetime','image','image_caption','bodytext','author','author_email','category');
		$wecdev_fields = array('pid','subject','post_datetime','image','image_caption','message','name','email','category');
		$newData = array();
		for ($i = 0; $i < count($oldData); $i++) {
			for ($j  = 0; $j < count($ttnews_fields); $j++) {
				$newData[$i][$wecdev_fields[$j]] = $oldData[$i][$ttnews_fields[$j]];
			}
			$newData[$i]['pid'] = $toPageID ? $toPageID : $oldData[$i]['pid'];
			// assign new category
			if ($oldData[$i]['category'] && count($oldCatLookup)) {
				$oldCatIndex = $oldCatLookup[$oldData[$i]['uid']];
				$oldCat = $oldCategories[$oldCatIndex];
				foreach ($newCat as $key => $newC) {
					if (!strcmp($oldCat['title'],$newC['name'])) {
						$newData[$i]['category'] = $newC['uid'];
						break;
					}
				}
			}
		}

		// 3. Insert new WEC_DISCUSSION_POST data
		//_________________________________________________________
		$valStr = '';
		for ($i = 0; $i < count($wecdev_fields); $i++) {
			$valStr .= $wecdev_fields[$i];
			if ($i != (count($wecdev_fields)-1))
				$valStr .= ',';
		}

		$dataStr = '';
		for ($i = 0; $i < count($newData); $i++) {
			$dataStr .= '(';
			for ($j = 0; $j < count($wecdev_fields); $j++) {
				$val = $newData[$i][$wecdev_fields[$j]];
				if (!is_numeric($val)) $val = '"'.htmlspecialchars($val).'"';
				$dataStr .=  $val;
				if ($j != (count($wecdev_fields)-1))
					$dataStr .= ',';
			}
			$dataStr .= ')';
			if ($i != (count($newData)-1)) $dataStr .= ',';
		}
		if (count($newData)) {
			$insertStr = 'INSERT INTO tx_wecdiscussion_post ('.$valStr.') VALUES '.$dataStr;
			$insert = $GLOBALS['TYPO3_DB']->sql(TYPO3_db,$insertStr);
		}

		//************************************************************
		// THIRD: Process all the comments
		//************************************************************

		// 1. Read in all VE_GUESTBOOK_ENTRY records from the given page id
		//_________________________________________________________
		$where = 'pid IN('.$fromPageID.') AND deleted=0';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_veguestbook_entries', $where, '','tstamp');
		if (mysql_error()) t3lib_div::debug(array(mysql_error(),$res));
		$commentData = array();
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))
				$commentData[] = $row;
		}
		$newPostData = array();
		if (count($commentData)) {
			// 2. Read in all new WECDISCUSSION_POST entries on page
			//_________________________________________________________
			$where = 'pid IN('.$toPageID.') AND deleted=0';
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'tx_wecdiscussion_post', $where, '','tstamp');
			if (mysql_error()) t3lib_div::debug(array(mysql_error(),$res));
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$newPostData[] = $row;
			}

			// 3. Match new data with old data
			//_________________________________________________________
			for ($i = 0; $i < count($newPostData); $i++) {
				for ($j = 0; $j < count($oldData); $j++) {
					if (!strcmp($newPostData[$i]['message'],htmlspecialchars($oldData[$j]['bodytext']))) {
						$newPostLookup[$oldData[$j]['uid']] = $newPostData[$i]['uid'];
						break;
					}
				}
			}
			// 4. Match all comments with POST records to assign top_uid.
			//_________________________________________________________
			$insertCommentData = '';
			for ($i = 0; $i < count($commentData); $i++) {
				if ($topuid = $newPostLookup[$commentData[$i]['uid_tt_news']]) {
					$insertCommentData .= "(".
						$toPageID . ',' .
						$topuid . ',' .
						$topuid . ',' .
						$commentData[$i]['tstamp'] . ',' .
						'"'.htmlspecialchars($commentData[$i]['entry']) . '",' .
						'"'.$commentData[$i]['firstname'] . '",' .
						'"'.$commentData[$i]['email'] . '"'.
						'),';

				}
			}
			if (strlen($insertCommentData)) // strip off ending ','
				$insertCommentData = substr($insertCommentData, 0, strlen($insertCommentData) - 1);

			// 5. Add comments to WECDISCUSSION_POST table
			//_________________________________________________________
			if (strlen($insertCommentData)) {
				$insertStr = 'INSERT INTO tx_wecdiscussion_post (pid,toplevel_uid,reply_uid,post_datetime,message,name,email) VALUES '.$insertCommentData;
				$insert = $GLOBALS['TYPO3_DB']->sql(TYPO3_db,$insertStr);
			}
		}

		$output = '<h3>Timtab->WEC Discussion Conversion Finished</h3>
			# messages converted = '.count($newData).'<br/>
			# comments converted = '.count($commentData).'<br/>
			# categories converted = '.count($newCategories).'<br/>
			';

		if (count($newData) == 0) {
			$output .= '<p><b>ALERT: No messages converted because the storage page to convert FROM (page ID ='.$fromPageID.') has no data. Please check that value and try again.</b></p>';
			$output .= tx_wecdiscussion_convert::getinfo();
		}
		else
			$output .= '<p><a href="'.$this->pi_getPageLink($GLOBALS["TSFE"]->id).'">Click here to return back</a>';

		return $output;

	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wec_discussion/pi1/class.tx_wecdiscussion_convert.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/wec_discussion/pi1/class.tx_wecdiscussion_convert.php']);
}

?>