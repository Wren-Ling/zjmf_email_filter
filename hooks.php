<?php
/**
 * 插件钩子文件
 *
 * 魔方会在每次请求(app_init 阶段)自动加载「已安装并启用」的插件的 hooks.php。
 * 这里用于注册那些不在系统钩子列表(HooksController / shd_hook)中、无法通过
 * shd_hook_plugin 表自动登记的钩子:
 *
 *   before_client_register  前台注册前验证(邮箱注册 / 手机注册)
 *   app_dispatch           分发前拦截,用于在发送注册验证码之前就挡住被禁邮箱
 *
 * 系统钩子(例如 client_details_validate)不需要在这里注册:安装插件时魔方会
 * 自动扫描插件类的公开方法并写入 shd_hook_plugin 表。
 *
 * 这里用闭包而不是 [类名, 方法名] 的形式,是因为内核执行数组形式的回调时会
 * 实例化插件类,而 app_dispatch 在每个请求都会触发,没必要每个请求都加载插件。
 * 闭包调用的是静态方法,不会产生实例。
 *
 * 说明:官方文档中的文件名是 hook.php,魔方 3.x 内核实际加载的是 hooks.php,
 * 两个文件都提供一份内容,兼容不同版本。
 */

if (!class_exists('addons\\email_filter\\EmailFilterPlugin')) {
    return;
}

// 前台注册前验证
\think\facade\Hook::add('before_client_register', function ($param = null) {
    return \addons\email_filter\EmailFilterPlugin::checkBeforeRegister($param);
});

// 分发前拦截注册验证码发送
\think\facade\Hook::add('app_dispatch', function ($param = null) {
    return \addons\email_filter\EmailFilterPlugin::checkBeforeDispatch();
});
