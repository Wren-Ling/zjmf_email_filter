<?php

namespace addons\email_filter\logic;

/**
 * 邮箱规则匹配逻辑
 *
 * 该类不依赖数据库与框架,方便单独测试。
 */
class EmailFilterLogic
{
    /**
     * 黑名单:命中规则的邮箱禁止注册
     */
    const MODE_BLACK = 1;

    /**
     * 白名单:只有命中规则的邮箱才允许注册
     */
    const MODE_WHITE = 2;

    /**
     * 把配置里的规则文本解析成规则数组
     *
     * 支持换行 / 逗号 / 分号 / 顿号分隔,以 # 或 // 开头的行视为注释。
     *
     * @param string $text
     * @return array
     */
    public static function parseRules($text)
    {
        $rules = [];
        if (empty($text) || !is_string($text)) {
            return $rules;
        }
        $items = preg_split('/[\r\n,;、]+/', $text);
        if (!is_array($items)) {
            return $rules;
        }
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (strpos($item, '#') === 0 || strpos($item, '//') === 0) {
                continue;
            }
            $rules[] = strtolower($item);
        }
        return array_values(array_unique($rules));
    }

    /**
     * 判断邮箱是否命中规则
     *
     * 规则支持以下写法:
     *   user@example.com  精确匹配该邮箱
     *   @example.com       匹配 example.com 域名下的所有邮箱
     *   example.com       同上
     *   *@example.com     同上
     *   *.example.com     匹配 example.com 及其任意子域名
     *
     * @param string $email
     * @param array  $rules parseRules() 的结果
     * @return string 命中的规则,未命中返回空字符串
     */
    public static function match($email, $rules)
    {
        $email = strtolower(trim((string)$email));
        if ($email === '' || empty($rules) || !is_array($rules)) {
            return '';
        }
        $pos = strrpos($email, '@');
        if ($pos === false) {
            return '';
        }
        $local = substr($email, 0, $pos);
        $domain = substr($email, $pos + 1);
        if ($local === '' || $domain === '') {
            return '';
        }
        foreach ($rules as $rule) {
            if (self::matchRule($rule, $local, $domain)) {
                return $rule;
            }
        }
        return '';
    }

    /**
     * 单条规则匹配
     *
     * @param string $rule
     * @param string $local
     * @param string $domain
     * @return bool
     */
    private static function matchRule($rule, $local, $domain)
    {
        $rule = strtolower(trim((string)$rule));
        if ($rule === '') {
            return false;
        }
        // *.example.com 匹配 example.com 及其子域名
        if (strpos($rule, '*.') === 0) {
            $base = substr($rule, 2);
            if ($base === '') {
                return false;
            }
            return $domain === $base || substr($domain, -strlen($base) - 1) === '.' . $base;
        }
        if (strpos($rule, '@') !== false) {
            $parts = explode('@', $rule, 2);
            $ruleLocal = $parts[0];
            $ruleDomain = $parts[1];
            if ($ruleDomain === '') {
                return false;
            }
            // @example.com / *@example.com 匹配整个域名
            if ($ruleLocal === '' || $ruleLocal === '*') {
                return $ruleDomain === $domain;
            }
            return $ruleLocal === $local && $ruleDomain === $domain;
        }
        // 纯域名
        return $rule === $domain;
    }

    /**
     * 综合判定某个邮箱是否允许注册
     *
     * @param string $email     待判定邮箱
     * @param string $ruleText  规则原文
     * @param int    $mode      MODE_BLACK / MODE_WHITE
     * @param string $message   自定义提示语,支持 {email} 与 {rule} 变量
     * @return array ['blocked' => bool, 'rule' => string, 'message' => string]
     */
    public static function judge($email, $ruleText, $mode = self::MODE_BLACK, $message = '')
    {
        $rules = self::parseRules($ruleText);
        $mode = (int)$mode === self::MODE_WHITE ? self::MODE_WHITE : self::MODE_BLACK;

        // 白名单模式下规则为空时不做拦截,避免误伤全部注册
        if ($mode === self::MODE_WHITE && empty($rules)) {
            return ['blocked' => false, 'rule' => '', 'message' => ''];
        }

        $matched = self::match($email, $rules);
        $blocked = $mode === self::MODE_WHITE ? ($matched === '') : ($matched !== '');

        if (!$blocked) {
            return ['blocked' => false, 'rule' => $matched, 'message' => ''];
        }

        $message = trim((string)$message);
        if ($message === '') {
            $message = $mode === self::MODE_WHITE
                ? '该邮箱不在允许注册的范围内,请更换邮箱后重试'
                : '该邮箱已被禁止注册,请更换邮箱后重试';
        }
        $message = str_replace(
            ['{email}', '{rule}'],
            [(string)$email, (string)$matched],
            $message
        );

        return ['blocked' => true, 'rule' => $matched, 'message' => $message];
    }
}
