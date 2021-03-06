<?php if(!defined('EMLOG_ROOT')) {exit('error!');} ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"  dir="<?= EMLOG_LANGUAGE_DIR ?>" lang="<?=EMLOG_LANGUAGES?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>upload</title>
<link href="./views/css/css-att.css?v=<?php echo Option::EMLOG_VERSION; ?>" type="text/css" rel="stylesheet">
<link href="./views/css/css-uploadify.css?v=<?php echo Option::EMLOG_VERSION; ?>" type="text/css" rel="stylesheet">
<script type="text/javascript" src="../include/lib/js/jquery/jquery-1.7.1.js"></script>
</head>
<body>
<script>
function showupload(multi){
	var as_logid = parent.document.getElementById('as_logid').value
	window.location.href="attachment.php?action=selectFile&logid="+as_logid+"&multi="+multi;	
}
function showattlib(){
	var as_logid = parent.document.getElementById('as_logid').value
	window.location.href="attachment.php?action=attlib&logid="+as_logid;	
}
</script>
<div id="media-upload-header">
	<span><a href="javascript:showupload(0);"><?php echo langs('attachment_upload')?></a></span>
	<span id="curtab"><a href="javascript:showupload(1);"><?php echo langs('bulk_upload')?></a></span>
	<span><a href="javascript:showattlib();"><?php echo langs('attachment_library')?>（<?php echo $attachnum; ?>）</a></span>
</div>
<?php 
if(true === isIE6Or7()): ?>
<div class="ie_notice"><?php echo langs('browser_upgrade')?></div>
<?php else:?>
<form enctype="multipart/form-data" method="post" name="upload" action="">
<div id="media-upload-body">
<div id="custom-bt"><input width="100%"  type="file" height="30" name="Filedata" id="custom_file_upload" style="display: none;"></div>
<div id="custom-queue" class="uploadifyQueue"></div>
</div>
</form>
<script type="text/javascript" src="../include/lib/js/uploadify/jquery.uploadify.min.js?v=<?php echo Option::EMLOG_VERSION; ?>"></script>
<script>
$(document).ready(function() {
	$("#custom_file_upload").uploadify({
		id              : jQuery(this).attr('id'),
		swf             : '../include/lib/js/uploadify/uploadify.swf',
		uploader        : 'attachment.php?action=upload_multi&logid='+parent.document.getElementById('as_logid').value,
		cancelImage     : './views/images/cancel.png',
		checkExisting   : false,
		buttonText      : '<?php echo langs('file_select')?>',
		auto            : true,
		multi           : true,
		buttonCursor    : 'pointer',
		fileTypeExts    : '<?php echo $att_type_for_muti;?>',
		queueID         : 'custom-queue',
		queueSizeLimit	: 100,
		removeCompleted : false,
		fileSizeLimit	: 20971520,
		fileObjName     : 'attach',
		postData		: {<?php echo AUTH_COOKIE_NAME;?>:'<?php echo $_COOKIE[AUTH_COOKIE_NAME];?>'},
		onQueueComplete : function() { showattlib();},
	});
});
</script>
<?php endif; ?>
</body>
</html>
