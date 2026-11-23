<?php

namespace addons\email_filter;

use addons\email_filter\logic\EmailFilterLogic;
use app\admin\lib\Plugin;

/**
 * 邮箱注册过滤插件
 *
 * 支持两种模式:
 *   1. 阻止指定邮箱注册(黑名单)
 *   2. 仅允许指定邮箱注册(白名单)
 *
 * 生效范围:
 *   - 前台邮箱 / 手机注册(before_client_register)
 *   - 后台添加客户(client_details_validate)
 *   - 前台注册验证码发送(app_dispatch,可在配置里关闭)
 *
 * 说明:前台两个钩子不是系统钩子,内核不会自动登记,需要在 hooks.php 里用
 * 闭包注册(闭包调用静态方法,不会实例化插件,避免每个请求都加载插件类)。
 * 后台的 client_details_validate 是系统钩子,安装插件时内核会自动登记,
 * 内核会用实例方式调用本类的 clientDetailsValidate 方法。
 *
 * @author  mofang
 * @version 1.0
 */
class EmailFilterPlugin extends Plugin
{
    /**
     * 插件基本信息
     */
    public $info = [
        'name'        => 'EmailFilter',
        'title'       => '邮箱注册过滤',
        'description' => '阻止指定邮箱注册 / 仅允许指定邮箱注册',
        'status'      => 1,
        'author'      => 'mofang',
        'version'     => '1.0',
        'module'      => 'addons',
        'lang'        => [
            'chinese'    => '邮箱注册过滤',
            'chinese_tw' => '信箱註冊過濾',
            'english'    => 'Email Registration Filter',
        ],
    ];

    /**
     * 后台权限规则名
     * PluginAdminBaseController 校验权限时使用的小写规则名
     */
    const AUTH_RULE = 'addons/email_filter/adminindex/setting';

    /**
     * 本次请求是否已经处理过前台注册拦截,避免同一请求重复处理
     *
     * @var bool
     */
    private static $registerHandled = false;

    /**
     * 配置缓存(单次请求内有效)
     *
     * @var array|null
     */
    private static $settingsCache = null;

    /**
     * 插件安装
     *
     * 这里刻意不写入后台权限规则:魔方对「不存在规则」的插件路由默认放行,
     * 写入规则反而会让非超管管理员直接 401。设置页的访问权限沿用后台其它插件页的规则。
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载
     */
    public function uninstall()
    {
        $this->removeAuthRule();
        return true;
    }

    /**
     * 读取插件配置
     *
     * 数据库里的配置优先,缺省值来自 config.php。
     * 静态实现,便于在没有实例化插件的情况下使用。
     *
     * @return array
     */
    public static function settings()
    {
        if (is_array(self::$settingsCache)) {
            return self::$settingsCache;
        }
        $config = [];
        try {
            $raw = \think\Db::name('plugin')->where('name', 'EmailFilter')->value('config');
            if (!empty($raw) && $raw != 'null') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $config = $decoded;
                }
            }
        } catch (\Throwable $e) {
            $config = [];
        }
        self::$settingsCache = array_merge(self::defaultSettings(), $config);
        return self::$settingsCache;
    }

    /**
     * 清空配置缓存(保存配置后调用)
     */
    public static function clearSettingsCache()
    {
        self::$settingsCache = null;
    }

    /**
     * config.php 中定义的缺省配置
     *
     * @return array
     */
    public static function defaultSettings()
    {
        $defaults = [];
        $file = __DIR__ . '/config.php';
        if (is_file($file)) {
            $fields = include $file;
            if (is_array($fields)) {
                foreach ($fields as $key => $field) {
                    if (is_array($field) && array_key_exists('value', $field)) {
                        $defaults[$key] = $field['value'];
                    }
                }
            }
        }
        return $defaults;
    }

    /**
     * 前台注册前验证(静态实现)
     *
     * 魔方在创建账户之前触发该钩子,返回 ['status' => 400, 'msg' => '...'] 即可中断注册。
     *
     * @param array $param 前台提交的注册参数
     * @return array|null
     */
    public static function checkBeforeRegister($param = [])
    {
        if (self::$registerHandled) {
            return null;
        }
        self::$registerHandled = true;

        $result = self::judgeRegistration($param, true);
        return $result;
    }

    /**
     * 前台注册前验证(实例方法形式)
     *
     * 兼容后续版本把 before_client_register 登记为系统钩子的情况。
     *
     * @param array $param
     * @return array|null
     */
    public function beforeClientRegister($param = [])
    {
        return self::checkBeforeRegister($param);
    }

    /**
     * 后台添加客户前验证
     *
     * 该钩子返回错误信息数组,返回非空数组即阻止添加客户。
     *
     * @param array $param 客户资料
     * @return array
     */
    public function clientDetailsValidate($param = [])
    {
        $result = self::judgeRegistration($param, false);
        return $result === null ? [] : $result;
    }

    /**
     * 分发前拦截:在发送注册验证码之前就挡住被禁用的邮箱
     *
     * @return null
     * @throws \think\exception\HttpResponseException
     */
    public static function checkBeforeDispatch()
    {
        try {
            $request = request();
            if (!$request->isPost()) {
                return null;
            }
            $path = strtolower(trim((string)$request->path(), '/'));
            if ($path === '') {
                return null;
            }
            $segments = explode('/', $path);
            $action = end($segments);
            if (!in_array($action, ['register_email_send', 'register_phone_send'], true)) {
                return null;
            }

            // 只有确实要发注册验证码时才读配置,避免每个请求都查一次数据库
            $config = self::settings();
            if (empty($config['is_open']) || empty($config['affect_code'])) {
                return null;
            }

            $email = trim((string)$request->param('email', ''));
            if ($email === '' && empty($config['block_empty_email'])) {
                return null;
            }

            $result = EmailFilterLogic::judge(
                $email,
                $config['email_list'],
                $config['mode'],
                $config['block_message']
            );
            if (!$result['blocked']) {
                return null;
            }

            self::writeLog('发送注册验证码', $email, $result['rule']);
            throw new \think\exception\HttpResponseException(
                json(['status' => 400, 'msg' => $result['message']])
            );
        } catch (\think\exception\HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // 拦截异常时不影响正常业务
            return null;
        }
    }

    /**
     * 统一的注册判定
     *
     * @param array $param      注册参数
     * @param bool  $isFrontend true = 前台钩子,返回 status/msg 结构;false = 后台钩子,返回错误信息数组
     * @return array|null
     */
    private static function judgeRegistration($param, $isFrontend)
    {
        $config = self::settings();
        if (empty($config['is_open'])) {
            return $isFrontend ? null : [];
        }
        if (!$isFrontend && empty($config['affect_admin'])) {
            return [];
        }

        $email = isset($param['email']) ? trim((string)$param['email']) : '';
        if ($email === '' && empty($config['block_empty_email'])) {
            // 手机号注册等没有邮箱的场景,按配置决定是否处理
            return $isFrontend ? null : [];
        }

        $result = EmailFilterLogic::judge(
            $email,
            $config['email_list'],
            $config['mode'],
            $config['block_message']
        );
        if (!$result['blocked']) {
            return $isFrontend ? null : [];
        }

        self::writeLog($isFrontend ? '前台注册' : '后台添加客户', $email, $result['rule']);

        return $isFrontend
            ? ['status' => 400, 'msg' => $result['message']]
            : [$result['message']];
    }

    /**
     * 记录拦截日志
     *
     * @param string $scene 拦截场景
     * @param string $email 被拦截的邮箱
     * @param string $rule  命中的规则
     */
    private static function writeLog($scene, $email, $rule)
    {
        try {
            $ruleText = $rule === '' ? '未命中任何规则(白名单模式)' : $rule;
            active_log(sprintf('邮箱注册过滤 - %s - 邮箱:%s - 规则:%s - 已拦截', $scene, $email, $ruleText));
        } catch (\Throwable $e) {
            // 日志失败不影响拦截结果
        }
    }

    /**
     * 删除后台权限规则
     *
     * 仅用于清理早期版本可能写入的规则,安装时不再写入。
     */
    private function removeAuthRule()
    {
        try {
            \think\Db::name('auth_rule')->where('name', self::AUTH_RULE)->delete();
        } catch (\Throwable $e) {
            // 删除失败不影响插件卸载
        }
    }
}
