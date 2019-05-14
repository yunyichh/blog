<?php
//获取主机名称的方法，正常返回ip，不正常返回实参
// echo gethostbyname('http://www.baidu.com');

//脚本时常死掉,而且并不总是那么好看. 我们可不想给用户显示一个致命错误,又或者一个空白页(在display_errors设为off的情况下) . PHP中有一个叫做  register_shutdown_function 的函数,可以让我们设置一个当执行关闭时可以被调用的另一个函数
//也就是说当我们的脚本执行完成或意外死掉导致PHP执行即将关闭时,我们的这个函数将会 被调用.
// 注意：register_shutdown_function　是指在执行完所有ＰＨＰ语句后再调用函数，不要理解成客户端关闭流浏览器页面时调用函数。
// 1、当页面被用户强制停止时
// 2、当程序代码运行超时时
// 3、当ＰＨＰ代码执行完成时，代码执行存在异常和错误、警告
// register_shutdown_function(array(&$this, 'close_session'));//类里面的方法

$clean = false;
function shutdown_func($pa1, $pa2){
global $clean;
echo 'over';
echo $pa1;
echo $pa2;
if (!$clean){
	die("not a clean shutdown");
}
return false;
}
register_shutdown_function("shutdown_func", '1', 2);
$a = 1;
$a = new FooClass(); // 将因为致命错误而失败,下面的代码也像正常情况一样将不在执行
echo 123123;
$clean = true;

//相似的方法还有
set_error_handler(array('Debug','appError')); // 设置一个用户定义的错误处理函数
set_exception_handler(array('Debug','appException')); //自定义异常处理。
?>