<?php
/**
 * 数据备份
 * @copyright (c) Emlog All Rights Reserved
 */

require_once 'globals.php';

if ($action == '') {
	$retval = glob('../content/backup/*.sql');
	$bakfiles = $retval ? $retval : array();
	$timezone = Option::get('timezone');
	$tables = array('attachment', 'blog', 'comment', 'options', 'navi', 'reply', 'sort','sortlink', 'link','tag','twitter','user');
	doAction('data_prebakup');

	include View::getView('header');
	require_once(View::getView('data'));
	include View::getView('footer');
	View::output();
}

if ($action == 'bakstart') {
    LoginAuth::checkToken();
	$table_box = isset($_POST['table_box']) ? array_map('addslashes', $_POST['table_box']) : array();
	$bakplace = isset($_POST['bakplace']) ? $_POST['bakplace'] : 'local';
	$zipbak = isset($_POST['zipbak']) ? $_POST['zipbak'] : 'n';

    $bakfname = 'emlog_'. gmdate('Ymd', time() + $timezone * 3600) . '_' . substr(md5(AUTH_KEY . uniqid()), 0, 18);
	$timezone = Option::get('timezone');
	$filename = '';
	$sqldump = '';
	foreach ($table_box as $table) {
		$sqldump .= dataBak($table);
	}
	if (trim($sqldump)) {
		$dumpfile = '#version:emlog '. Option::EMLOG_VERSION . "\n";
		$dumpfile .= '#date:' . gmdate('Y-m-d H:i', time() + $timezone * 3600) . "\n";
		$dumpfile .= '#tableprefix:' . DB_PREFIX . "\n";
		$dumpfile .= $sqldump;
		$dumpfile .= "\n#the end of backup";
		if ($bakplace == 'local') {
			$filename = 'emlog_'. gmdate('Ymd_His', time() + $timezone * 3600);
			if ($zipbak == 'y') {
				if (($dumpfile = emZip($filename . '.sql', $dumpfile)) === false ) {
					emDirect('./data.php?error_f=1');
				}
				header('Content-Type: application/zip');
				header('Content-Disposition: attachment; filename=' . $filename . '.zip');
			} else {
				header('Content-Type: text/x-sql');
				header('Content-Disposition: attachment; filename=' . $filename . '.sql');
			}
			if (preg_match("/MSIE ([0-9].[0-9]{1,2})/", $_SERVER['HTTP_USER_AGENT'])) {
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
			} else {
				header('Pragma: no-cache');
				header('Last-Modified: '. gmdate('D, d M Y H:i:s', time() + $timezone * 3600) . ' GMT');
			}
			header('Expires: '. gmdate('D, d M Y H:i:s', time() + $timezone * 3600) . ' GMT');
			echo $dumpfile;
		} else {
			if (!preg_match("/^[a-zA-Z0-9_]+$/", $bakfname)) {
				emDirect('./data.php?error_b=1');
			}
			$filename = '../content/backup/'.$bakfname.'.sql';
			@$fp = fopen($filename, 'w+');
			if ($fp){
				@flock($fp, 3);
				if (@!fwrite($fp, $dumpfile)){
					@fclose($fp);
	emMsg(langs('backup_directory_not_writable'));
				} else{
					emDirect('./data.php?active_backup=1');
				}
			} else{
			emMsg(langs('backup_create_file_error'));
			}
		}
	} else{
		emMsg(langs('backup_empty'));
	}
}

//导入服务器备份文件
if ($action == 'renewdata') {
    LoginAuth::checkToken();
	$sqlfile = isset($_GET['sqlfile']) ? $_GET['sqlfile'] : '';
	if (!file_exists($sqlfile)) {
		emMsg(langs('file_not_exists'));
	}

	if (getFileSuffix($sqlfile) !== 'sql') {
		emMsg(langs('import_only_emlog'));
	}
	checkSqlFileInfo($sqlfile);
	bakindata($sqlfile);
	$CACHE->updateCache();
	emDirect('./data.php?active_import=1');
}

//导入本地备份文件
if ($action == 'import') {
    LoginAuth::checkToken();
	$sqlfile = isset($_FILES['sqlfile']) ? $_FILES['sqlfile'] : '';
	if (!$sqlfile) {
		emMsg(langs('info_illegal'));
	}
	if ($sqlfile['error'] == 1) {
emMsg(langs('attachment_exceed_system_limit').ini_get('upload_max_filesize').langs('limit'));
	} elseif ($sqlfile['error'] > 1) {
		emMsg(langs('upload_failed_code').$sqlfile['error']);
	}
	if (getFileSuffix($sqlfile['name']) == 'zip') {
		$ret = emUnZip($sqlfile['tmp_name'], dirname($sqlfile['tmp_name']), 'backup');
		switch ($ret) {
			case -3:
				emDirect('./data.php?error_e=1');
				break;
			case 1:
			case 2:
				emDirect('./data.php?error_d=1');
				break;
			case 3:
				emDirect('./data.php?error_c=1');
				break;
		}
		$sqlfile['tmp_name'] = dirname($sqlfile['tmp_name']) . '/' .str_replace('.zip', '.sql', $sqlfile['name']);
		if (!file_exists($sqlfile['tmp_name'])) {
	emMsg(langs('import_only_emlog_no_change'));
		}
	} elseif (getFileSuffix($sqlfile['name']) != 'sql') {
		emMsg(langs('import_only_emlog'));
	}
	checkSqlFileInfo($sqlfile['tmp_name']);
	bakindata($sqlfile['tmp_name']);
	$CACHE->updateCache();
	emDirect('./data.php?active_import=1');
}

if ($action == 'dell_all_bak') {
    LoginAuth::checkToken();
     if (!isset($_POST['bak'])) {
		emDirect('./data.php?error_a=1');
	} else{
		foreach ($_POST['bak'] as $val) {
			unlink($val);
		}
		emDirect('./data.php?active_del=1');
	}
}

/**
 * 检查备份文件头信息
 * 
 * @param file $sqlfile
 */
function checkSqlFileInfo($sqlfile) {
	$fp = @fopen($sqlfile, 'r');
	if (!$fp) {
		emMsg(langs('import_failed_not_read'));
	}
	$dumpinfo = array();
	$line = 0;
	while (!feof($fp)) {
		$dumpinfo[] = fgets($fp, 4096);
		$line++;
		if ($line == 3) break;
	}
	fclose($fp);
	if (empty($dumpinfo)) {
		emMsg(langs('import_failed_not_emlog'));
	}
	if (!preg_match('/#version:emlog '. Option::EMLOG_VERSION .'/', $dumpinfo[0])) {
emMsg(langs('import_failed_not_emlog_ver'));
	}
	if (preg_match('/#tableprefix:'. DB_PREFIX .'/', $dumpinfo[2]) === 0) {
emMsg(langs('import_failed_bad_prefix') . $dumpinfo[2]);
	}
}

/**
 * 执行备份文件的SQL语句
 *
 * @param string $filename
 */
function bakindata($filename) {
	$DB = Database::getInstance();
	$setchar = $DB->getMysqlVersion() > '4.1' ? "ALTER DATABASE `" . DB_NAME . "` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;" : '';
	$sql = file($filename);
	if (isset($sql[0]) && !empty($sql[0]) && checkBOM($sql[0])) {
		$sql[0] = substr($sql[0], 3);
	}
	array_unshift($sql,$setchar);
	$query = '';
	foreach ($sql as $value) {
		$value = trim($value);
		if (!$value || $value[0]=='#') {
			continue;
		}
		if (preg_match("/\;$/i", $value)) {
			$query .= $value;
			if (preg_match("/^CREATE/i", $query)) {
				$query = preg_replace("/\DEFAULT CHARSET=([a-z0-9]+)/is",'',$query);
			}
			$DB->query($query);
			$query = '';
		} else{
			$query .= $value;
		}
	}
}

/**
 * 备份数据库结构和所有数据
 *
 * @param string $table 数据库表名
 * @return string
 */
function dataBak($table) {
	$DB = Database::getInstance();
	$sql = "DROP TABLE IF EXISTS $table;\n";
	$createtable = $DB->query("SHOW CREATE TABLE $table");
	$create = $DB->fetch_row($createtable);
	$sql .= $create[1].";\n\n";

	$rows = $DB->query("SELECT * FROM $table");
	$numfields = $DB->num_fields($rows);
	$numrows = $DB->num_rows($rows);
	while ($row = $DB->fetch_row($rows)) {
		$comma = '';
		$sql .= "INSERT INTO $table VALUES(";
		for ($i = 0; $i < $numfields; $i++) {
			$sql .= $comma."'" . $DB->escape_string($row[$i]) . "'";
			$comma = ',';
		}
		$sql .= ");\n";
	}
	$sql .= "\n";
	return $sql;
}

/**
 * 检查文件是否包含BOM(byte-order mark)
 */
function checkBOM($contents) {
	$charset[1] = substr($contents, 0, 1);
	$charset[2] = substr($contents, 1, 1);
	$charset[3] = substr($contents, 2, 1);
	if (ord($charset[1]) == 239 && ord($charset[2]) == 187 && ord($charset[3]) == 191) {
		return true;
	} else {
		return false;
	}
}


