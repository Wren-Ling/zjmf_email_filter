<?php
/**
 * EmailFilterLogic 单元测试(不依赖魔方框架,可直接用 php 运行)
 *   php tests/EmailFilterLogicTest.php
 */

require __DIR__ . '/../logic/EmailFilterLogic.php';

use addons\email_filter\logic\EmailFilterLogic;

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

$rules = "spam@bad.com\n# 下面是注释\n@evil.com\n*.phish.net\n";

echo "== 规则解析 ==\n";
$parsed = EmailFilterLogic::parseRules($rules);
check('解析出 3 条规则', count($parsed), 3);
check('忽略注释行', in_array('# 下面是注释', $parsed), false);
check('保留 @evil.com', in_array('@evil.com', $parsed), true);

echo "== 黑名单模式 ==\n";
$black = function ($email) use ($rules) {
    return EmailFilterLogic::judge($email, $rules, EmailFilterLogic::MODE_BLACK);
};
check('精确命中被拦截', $black('spam@bad.com')['blocked'], true);
check('大小写不敏感', $black('SPAM@BAD.COM')['blocked'], true);
check('@域名命中被拦截', $black('anyone@evil.com')['blocked'], true);
check('子域名不被 @域名 规则命中', $black('anyone@sub.evil.com')['blocked'], false);
check('*.域名 命中主域', $black('a@phish.net')['blocked'], true);
check('*.域名 命中子域', $black('a@mail.phish.net')['blocked'], true);
check('无关邮箱放行', $black('good@example.com')['blocked'], false);
check('空邮箱在黑名单下放行', $black('')['blocked'], false);

echo "== 白名单模式 ==\n";
$whiteRules = "@allowed.com\nboss@other.com\n";
$white = function ($email) use ($whiteRules) {
    return EmailFilterLogic::judge($email, $whiteRules, EmailFilterLogic::MODE_WHITE);
};
check('白名单内放行', $white('x@allowed.com')['blocked'], false);
check('白名单内精确邮箱放行', $white('boss@other.com')['blocked'], false);
check('白名单外拦截', $white('x@denied.com')['blocked'], true);
check('规则为空时白名单不拦截', EmailFilterLogic::judge('x@denied.com', '', EmailFilterLogic::MODE_WHITE)['blocked'], false);

echo "== 提示语 ==\n";
$msg = EmailFilterLogic::judge('spam@bad.com', $rules, EmailFilterLogic::MODE_BLACK, '邮箱 {email} 被禁,规则 {rule}');
check('变量替换', $msg['message'], '邮箱 spam@bad.com 被禁,规则 spam@bad.com');
$default = EmailFilterLogic::judge('spam@bad.com', $rules, EmailFilterLogic::MODE_BLACK, '');
check('默认提示语非空', $default['message'] !== '', true);
$whiteMsg = EmailFilterLogic::judge('x@denied.com', $whiteRules, EmailFilterLogic::MODE_WHITE, '');
check('白名单默认提示语与黑名单不同', $whiteMsg['message'] !== $default['message'], true);

echo "== 分隔符与边界 ==\n";
check('逗号分隔', EmailFilterLogic::judge('a@x.com', 'a@x.com,b@y.com', EmailFilterLogic::MODE_BLACK)['blocked'], true);
check('分号分隔', EmailFilterLogic::judge('b@y.com', 'a@x.com;b@y.com', EmailFilterLogic::MODE_BLACK)['blocked'], true);
check('顿号分隔', EmailFilterLogic::judge('b@y.com', 'a@x.com、b@y.com', EmailFilterLogic::MODE_BLACK)['blocked'], true);
check('带空格的规则', EmailFilterLogic::judge('a@x.com', '  a@x.com  ', EmailFilterLogic::MODE_BLACK)['blocked'], true);
check('规则为空黑名单不拦截', EmailFilterLogic::judge('a@x.com', '', EmailFilterLogic::MODE_BLACK)['blocked'], false);
check('非邮箱字符串不命中', EmailFilterLogic::judge('notanemail', 'notanemail', EmailFilterLogic::MODE_BLACK)['blocked'], false);
check('返回命中的规则', $black('anyone@evil.com')['rule'], '@evil.com');

echo "\n共 {$total} 项,失败 {$failures} 项\n";
exit($failures > 0 ? 1 : 0);
