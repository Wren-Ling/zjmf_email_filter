<?php
/**
 * 插件与魔方内核的对接自检脚本
 *
 *   php tests/PluginWiringTest.php /path/to/mofang/source
 *
 * 校验内容:
 *   1. 钩子名 -> 插件方法名的映射(think\Loader::parseName)
 *   2. 插件类名 / 命名空间 / 目录名 与内核的推导规则一致
 *   3. 插件的公开方法与内核系统钩子列表的交集(即安装时会自动登记的钩子)
 *   4. 非系统钩子确实需要由 hooks.php 手动注册
 */

$mfRoot = rtrim($argv[1] ?? '', '/');
if ($mfRoot === '' || !is_dir($mfRoot)) {
    fwrite(STDERR, "用法: php tests/PluginWiringTest.php /path/to/mofang/source\n");
    exit(2);
}
// 只加载 Loader 类本身,避免拉起魔方整个框架(需要数据库)
require $mfRoot . '/vendor/topthink/framework/library/think/Loader.php';

$failures = 0;
$total = 0;

function check($name, $actual, $expected)
{
    global $failures, $total;
    $total++;
    if ($actual === $expected) {
        printf("  [PASS] %s\n", $name);
        return;
    }
    $failures++;
    printf("  [FAIL] %s\n         期望: %s\n         实际: %s\n", $name, var_export($expected, true), var_export($actual, true));
}

$pluginRoot = realpath(__DIR__ . '/..');

echo "== 钩子名 -> 方法名映射 ==\n";
check('before_client_register', think\Loader::parseName('before_client_register', 1, false), 'beforeClientRegister');
check('client_details_validate', think\Loader::parseName('client_details_validate', 1, false), 'clientDetailsValidate');
check('app_dispatch', think\Loader::parseName('app_dispatch', 1, false), 'appDispatch');

echo "== 插件身份推导 ==\n";
// 内核: cmf_get_plugin_class_shd($name, 'addons') => "addons\\" . parseName($name) . "\\" . $name . "Plugin"
$pluginName = 'EmailFilter';
$class = 'addons\\' . think\Loader::parseName($pluginName) . '\\' . $pluginName . 'Plugin';
check('内核推导出的类名', $class, 'addons\\email_filter\\EmailFilterPlugin');
check('目录名', think\Loader::parseName($pluginName), 'email_filter');
check('插件根目录存在', is_dir($pluginRoot), true);
check('类文件存在', is_file($pluginRoot . '/EmailFilterPlugin.php'), true);
check('hook 文件存在', is_file($pluginRoot . '/hooks.php'), true);
check('配置文件存在', is_file($pluginRoot . '/config.php'), true);
check('菜单文件存在', is_file($pluginRoot . '/menu.php'), true);

echo "== 插件公开方法 ==\n";
require $pluginRoot . '/logic/EmailFilterLogic.php';
require __DIR__ . '/stubs/PluginStub.php';
require $pluginRoot . '/EmailFilterPlugin.php';
$methods = get_class_methods('addons\\email_filter\\EmailFilterPlugin');
$snake = array_map(function ($m) {
    return think\Loader::parseName($m);
}, $methods);
echo "  插件方法: " . implode(', ', $snake) . "\n";

echo "== 系统钩子交集(安装时自动登记到 shd_hook_plugin) ==\n";
$hooksController = file_get_contents($mfRoot . '/app/home/controller/HooksController.php');
preg_match_all('/public function ([A-Za-z0-9_]+)\s*\(/', $hooksController, $m);
$systemHooks = $m[1];
// app/app.php 里额外追加的模板钩子
$systemHooks = array_merge($systemHooks, [
    'template_after_servicedetail_suspended',
    'template_after_service_domainstatus_selected',
    'template_custom_clientarea_captcha_html',
]);
$intersect = array_values(array_intersect($systemHooks, $snake));
echo "  交集: " . (empty($intersect) ? '(无)' : implode(', ', $intersect)) . "\n";
check('client_details_validate 会被自动登记', in_array('client_details_validate', $intersect, true), true);
check('before_client_register 不是系统钩子', in_array('before_client_register', $systemHooks, true), false);
check('app_dispatch 不是系统钩子', in_array('app_dispatch', $systemHooks, true), false);

echo "== hooks.php 注册的钩子 ==\n";
$hooks = file_get_contents($pluginRoot . '/hooks.php');
check('注册了 before_client_register', strpos($hooks, "Hook::add('before_client_register'") !== false, true);
check('注册了 app_dispatch', strpos($hooks, "Hook::add('app_dispatch'") !== false, true);
check('调用的是静态方法', strpos($hooks, 'EmailFilterPlugin::checkBeforeRegister') !== false, true);

echo "== 后台配置表单字段 ==\n";
$fields = include $pluginRoot . '/config.php';
check('config.php 返回数组', is_array($fields), true);
$allowedTypes = ['text', 'select', 'radio', 'checkbox'];
foreach ($fields as $key => $field) {
    check("字段 {$key} 有 title", isset($field['title']), true);
    check("字段 {$key} 类型受支持", isset($field['type']) && in_array($field['type'], $allowedTypes, true), true);
    check("字段 {$key} 有默认值", array_key_exists('value', $field), true);
    if (isset($field['type']) && in_array($field['type'], ['select', 'radio', 'checkbox'], true)) {
        check("字段 {$key} 有 options", !empty($field['options']), true);
    }
}
// 设置页保存时提交的所有字段都必须在 config.php 里定义,否则会被后台配置接口丢弃
$tpl = file_get_contents($pluginRoot . '/template/admin/setting.tpl');
preg_match_all('/name="config\[([a-z_]+)\]"/', $tpl, $m);
$posted = array_unique($m[1]);
check('设置页提交了全部配置字段', count($posted) === count($fields), true);
foreach ($posted as $key) {
    check("提交的字段 {$key} 在 config.php 中有定义", isset($fields[$key]), true);
}

echo "\n共 {$total} 项,失败 {$failures} 项\n";
exit($failures > 0 ? 1 : 0);
