<?php
include("../includes/common.php");
if($islogin2==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$title='订单记录';
include './head.php';
?>
<?php

$type_select = '<option value="0">所有支付方式</option>';
$rs = $DB->getAll("SELECT * FROM pre_type WHERE status=1 ORDER BY id ASC");
foreach($rs as $row){
	$type_select .= '<option value="'.$row['id'].'">'.$row['showname'].'</option>';
}
unset($rs);

?>
<style>
.dates{max-width: 120px;}
.fixed-table-toolbar,.fixed-table-pagination{padding: 15px;}
</style>
<link href="../assets/css/datepicker.css" rel="stylesheet">
 <div id="content" class="app-content" role="main">
    <div class="app-content-body ">

<div class="bg-light lter b-b wrapper-md hidden-print">
  <h1 class="m-n font-thin h3">订单记录</h1>
</div>
<div class="wrapper-md control">
<?php if(isset($msg)){?>
<div class="alert alert-info">
	<?php echo $msg?>
</div>
<?php }?>
	<div class="panel panel-default">
		<div class="panel-heading font-bold">
			<h3 class="panel-title">订单记录<a href="javascript:searchClear()" class="btn btn-default btn-xs pull-right" title="刷新订单列表"><i class="fa fa-refresh"></i></a></h3>
		</div>

	    <form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
	      <div class="form-group">
			<select class="form-control" name="type">
			  <option value="1">系统订单号</option>
			  <option value="2">商户订单号</option>
			  <option value="3">商品名称</option>
			  <option value="4">商品金额</option>
			  <option value="5">实付金额</option>
			  <option value="6">网站域名</option>
			</select>
		  </div>
			<div class="form-group" id="searchword">
			  <input type="text" class="form-control" name="kw" placeholder="搜索内容" style="min-width: 300px;">
			</div>
			<div class="input-group input-daterange">
				<input type="text" id="starttime" name="starttime" class="form-control dates" placeholder="开始日期" autocomplete="off" title="留空则不限时间范围">
				<span class="input-group-addon" onclick="$('#starttime').val('');$('#endtime').val('');" title="清除"><i class="fa fa-chevron-right"></i></span>
				<input type="text" id="endtime" name="endtime" class="form-control dates" placeholder="结束日期" autocomplete="off" title="留空则不限时间范围">
			</div>
			<div class="form-group">
			  <select name="paytype" class="form-control"><?php echo $type_select?></select>
		    </div>
			<div class="form-group">
				<select name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="0">状态未支付</option><option value="1">状态已支付</option><option value="2">状态已退款</option><option value="3">状态已冻结</option></select>
			</div>
			<button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> 搜索</button>
		</form>
      <table id="listTable">
	  </table>
	</div>
</div>
    </div>
  </div>
  <a style="display: none;" href="" id="vurl" rel="noreferrer" target="_blank"></a>
<?php include 'foot.php';?>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.zh-CN.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-table/1.20.2/bootstrap-table.min.js"></script>
<script src="<?php echo $cdnpublic?>bootstrap-table/1.20.2/extensions/page-jump-to/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
$(document).ready(function(){
	updateToolbar();
	const defaultPageSize = 20;
	const pageNumber = typeof window.$_GET['pageNumber'] != 'undefined' ? parseInt(window.$_GET['pageNumber']) : 1;
	const pageSize = typeof window.$_GET['pageSize'] != 'undefined' ? parseInt(window.$_GET['pageSize']) : defaultPageSize;

	$("#listTable").bootstrapTable({
		url: 'ajax2.php?act=orderList',
		pageNumber: pageNumber,
		pageSize: pageSize,
		classes: 'table table-striped table-hover table-bordered',
		columns: [
			{
				field: 'trade_no',
				title: '系统订单号/商户订单号',
				formatter: function(value, row, index) {
					return value+'<br/>'+row.out_trade_no;
				}
			},
			{
				field: 'name',
				title: '商品名称'
			},
			{
				field: 'money',
				title: '商品金额',
				formatter: function(value, row, index) {
					return '￥<b>'+value+'</b>';
				}
			},
			{
				field: 'typename',
				title: '支付方式',
				formatter: function(value, row, index) {
					return value ? '<b><img src="/assets/icon/'+value+'.ico" width="16" onerror="this.style.display=\'none\'">'+row.typeshowname+'</b>' : '';
				}
			},
			{
				field: 'addtime',
				title: '创建时间/完成时间',
				formatter: function(value, row, index) {
					return value+'<br/>'+(row.endtime??'&nbsp;');
				}
			},
			{
				field: 'status',
				title: '支付状态',
				formatter: function(value, row, index) {
					switch(value){
						case '1': return '<font color=green>已支付</font>';break;
						case '2': return '<font color=red>已退款</font>';break;
						case '3': return '<font color=red>已冻结</font>';break;
						default: return '<font color=blue>未支付</font>';break;
					}
				}
			},
			{
				field: '',
				title: '操作',
				formatter: function(value, row, index) {
					return '<a href="./record.php?type=3&kw='+row.trade_no+'" class="btn btn-info btn-xs">明细</a>&nbsp;<a href="javascript:callnotify(\''+row.trade_no+'\')" class="btn btn-success btn-xs">补单</a>';
				}
			},
		],
	});

	$('.input-datepicker, .input-daterange').datepicker({
        format: 'yyyy-mm-dd',
		autoclose: true,
        clearBtn: true,
        language: 'zh-CN'
    });
})

function callnotify(trade_no){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax2.php?act=notify',
		data : {trade_no:trade_no},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#vurl").attr("href",data.url);
				document.getElementById("vurl").click();
				listTable();
			}else{
				layer.alert(data.msg);
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
	return false;
}
function callreturn(trade_no){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax2.php?act=notify',
		data : {trade_no:trade_no,isreturn:1},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#vurl").attr("href",data.url);
				document.getElementById("vurl").click();
				listTable();
			}else{
				layer.alert(data.msg);
			}
		},
		error:function(data){
			layer.msg('服务器错误');
		}
	});
	return false;
}

</script>