<?php
/**
 * 后台「插件设置」表单定义
 *
 * 表单支持的类型:text / select / radio / checkbox
 * text 类型在后台是一个可输入多行的文本框,用于填写邮箱规则列表。
 */
return [
    'module_name' => [
        'title' => '插件名称',
        'type'  => 'text',
        'value' => '邮箱注册过滤',
        'tip'   => '显示在后台插件列表中的名称',
    ],
    'is_open' => [
        'title'   => '是否启用',
        'type'    => 'select',
        'options' => [
            '0' => '关闭',
            '1' => '开启',
        ],
        'value' => '1',
        'tip'   => '关闭后插件不拦截任何注册请求',
    ],
    'mode' => [
        'title'   => '拦截模式',
        'type'    => 'select',
        'options' => [
            '1' => '阻止指定邮箱注册(黑名单)',
            '2' => '仅允许指定邮箱注册(白名单)',
        ],
        'value' => '1',
        'tip'   => '黑名单:命中规则的邮箱禁止注册;白名单:只有命中规则的邮箱可以注册',
    ],
    'email_list' => [
        'title' => '邮箱规则',
        'type'  => 'text',
        'value' => '',
        'tip'   => '每行一条规则,以 # 或 // 开头的行视为注释。'
            . '支持写法:user@example.com 精确匹配 / @example.com 整个域名 / example.com 整个域名 / '
            . '*@example.com 整个域名 / *.example.com 域名及其子域名',
    ],
    'block_message' => [
        'title' => '拦截提示语',
        'type'  => 'text',
        'value' => '',
        'tip'   => '留空使用默认提示语,支持变量 {email}(被拦截的邮箱)与 {rule}(命中的规则)',
    ],
    'affect_admin' => [
        'title'   => '后台添加客户',
        'type'    => 'select',
        'options' => [
            '0' => '不拦截',
            '1' => '同样拦截',
        ],
        'value' => '1',
        'tip'   => '是否对后台「添加客户」功能生效',
    ],
    'affect_code' => [
        'title'   => '注册验证码',
        'type'    => 'select',
        'options' => [
            '0' => '不拦截',
            '1' => '发送前拦截',
        ],
        'value' => '1',
        'tip'   => '是否在发送注册验证码之前就拦截,避免给被禁邮箱发送验证码',
    ],
    'block_empty_email' => [
        'title'   => '无邮箱注册',
        'type'    => 'select',
        'options' => [
            '0' => '不拦截',
            '1' => '拦截',
        ],
        'value' => '0',
        'tip'   => '白名单模式下,手机号注册等没有填写邮箱的注册请求是否拦截',
    ],
];
