<?php
/**
 * 插件钩子行为的集成自检(用桩件替换框架依赖)
 *
 *   php tests/HookBehaviourTest.php
 *
 * 覆盖:
 *   - 前台注册钩子 before_client_register 的返回值结构
 *   - 后台添加客户钩子 client_details_validate 的返回值结构
 *   - 分发前拦截 check_before_dispatch 抛出的响应
 *   - 开关与各项配置是否生效
 */

namespace think {
    class Db
    {
        /** @var array 模拟 shd_plugin 表里的 config */
        public static $pluginConfig = [];

        public static function name($table)
        {
            return new QueryStub();
        }
    }

    class QueryStub
    {
        public function where($a, $b = null, $c = null)
        {
            return $this;
        }

        public function value($field)
        {
            return Db::$pluginConfig;
        }
    }
}

namespace think\exception {
    class HttpResponseException extends \Exception
    {
        private $response;

        public function __construct($response)
        {
            $this->response = $response;
            parent::__construct('http response');
        }

        public function getResponse()
        {
            return $this->response;
        }
    }
}

namespace {
    use addons\email_filter\EmailFilterPlugin;

    /**
     * 模拟 request() 对象
     */
    class FakeRequest
    {
        public $post = true;
        public $path = '';
        public $params = [];

        public function isPost()
        {
            return $this->post;
        }

        public function path()
        {
            return $this->path;
        }

        public function param($name = '', $default = null, $filter = '')
        {
            return isset($this->params[$name]) ? $this->params[$name] : $default;
        }
    }

    $GLOBALS['__request'] = new FakeRequest();
    $GLOBALS['__logs'] = [];

    function request()
    {
        return $GLOBALS['__request'];
    }

    function json($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    function active_log($description, $userid = 0, $type = '', $table = 1, $flag = 1, $admin = false)
    {
        $GLOBALS['__logs'][] = $description;
    }

    // ---- 加载被测代码 ----
    $pluginRoot = realpath(__DIR__ . '/..');
    require $pluginRoot . '/logic/EmailFilterLogic.php';
    require __DIR__ . '/stubs/PluginStub.php';
    require $pluginRoot . '/EmailFilterPlugin.php';

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

    /**
     * 设置插件配置并清空缓存
     */
    function setConfig(array $config)
    {
        think\Db::$pluginConfig = json_encode($config, JSON_UNESCAPED_UNICODE);
        EmailFilterPlugin::clearSettingsCache();
        // 重置“本次请求已处理”标记
        $ref = new \ReflectionProperty(EmailFilterPlugin::class, 'registerHandled');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $ref->setValue(null, false);
    }

    function defaults(array $override = [])
    {
        return array_merge([
            'is_open' => 1,
            'mode' => 1,
            'email_list' => "spam@bad.com\n@evil.com",
            'block_message' => '',
            'affect_admin' => 1,
            'affect_code' => 1,
            'block_empty_email' => 0,
        ], $override);
    }

    echo "== 前台注册钩子 before_client_register ==\n";
    setConfig(defaults());
    $blocked = EmailFilterPlugin::checkBeforeRegister(['email' => 'spam@bad.com', 'password' => 'secret']);
    check('被拦截时返回 status=400', isset($blocked['status']) ? $blocked['status'] : null, 400);
    check('被拦截时带提示语', isset($blocked['msg']) && $blocked['msg'] !== '', true);
    check('提示语不含密码', isset($blocked['msg']) && strpos($blocked['msg'], 'secret') === false, true);

    setConfig(defaults());
    $allowed = EmailFilterPlugin::checkBeforeRegister(['email' => 'good@example.com']);
    check('放行时返回 null', $allowed, null);

    setConfig(defaults(['is_open' => 0]));
    check('插件关闭时放行', EmailFilterPlugin::checkBeforeRegister(['email' => 'spam@bad.com']), null);

    setConfig(defaults(['mode' => 2, 'email_list' => '@ok.com']));
    check('白名单:名单内放行', EmailFilterPlugin::checkBeforeRegister(['email' => 'a@ok.com']), null);
    setConfig(defaults(['mode' => 2, 'email_list' => '@ok.com']));
    $w = EmailFilterPlugin::checkBeforeRegister(['email' => 'a@no.com']);
    check('白名单:名单外拦截', isset($w['status']) ? $w['status'] : null, 400);

    setConfig(defaults(['block_empty_email' => 1, 'mode' => 2, 'email_list' => '@ok.com']));
    $empty = EmailFilterPlugin::checkBeforeRegister(['phone' => '13800138000']);
    check('白名单+拦截空邮箱:手机注册被拦截', isset($empty['status']) ? $empty['status'] : null, 400);
    setConfig(defaults(['block_empty_email' => 0, 'mode' => 2, 'email_list' => '@ok.com']));
    check('白名单+不拦截空邮箱:手机注册放行', EmailFilterPlugin::checkBeforeRegister(['phone' => '13800138000']), null);

    setConfig(defaults());
    EmailFilterPlugin::checkBeforeRegister(['email' => 'spam@bad.com']);
    check('同一请求重复调用只处理一次', EmailFilterPlugin::checkBeforeRegister(['email' => 'spam@bad.com']), null);

    echo "== 后台添加客户钩子 client_details_validate ==\n";
    setConfig(defaults());
    $plugin = new EmailFilterPlugin();
    $adminBlocked = $plugin->clientDetailsValidate(['email' => 'spam@bad.com']);
    check('后台被拦截时返回错误信息数组', is_array($adminBlocked) && !empty($adminBlocked[0]), true);
    check('后台错误信息是字符串', is_string($adminBlocked[0]), true);

    setConfig(defaults());
    check('后台放行时返回空数组', $plugin->clientDetailsValidate(['email' => 'good@example.com']), []);

    setConfig(defaults(['affect_admin' => 0]));
    check('关闭后台拦截时放行', $plugin->clientDetailsValidate(['email' => 'spam@bad.com']), []);

    echo "== 分发前拦截 check_before_dispatch ==\n";
    setConfig(defaults());
    $GLOBALS['__request']->post = true;
    $GLOBALS['__request']->path = 'register_email_send';
    $GLOBALS['__request']->params = ['email' => 'spam@bad.com'];
    $caught = null;
    try {
        EmailFilterPlugin::checkBeforeDispatch();
    } catch (\think\exception\HttpResponseException $e) {
        $caught = $e->getResponse();
    }
    check('验证码发送被拦截', $caught !== null, true);
    check('响应是 status=400 的 json', $caught !== null && strpos($caught, '"status":400') !== false, true);

    setConfig(defaults());
    $GLOBALS['__request']->path = 'register_email_send';
    $GLOBALS['__request']->params = ['email' => 'good@example.com'];
    $caught = null;
    try {
        EmailFilterPlugin::checkBeforeDispatch();
    } catch (\think\exception\HttpResponseException $e) {
        $caught = $e->getResponse();
    }
    check('正常邮箱不拦截', $caught, null);

    setConfig(defaults());
    $GLOBALS['__request']->path = 'login';
    $GLOBALS['__request']->params = ['email' => 'spam@bad.com'];
    $caught = null;
    try {
        EmailFilterPlugin::checkBeforeDispatch();
    } catch (\think\exception\HttpResponseException $e) {
        $caught = $e->getResponse();
    }
    check('非验证码接口不拦截', $caught, null);

    setConfig(defaults(['affect_code' => 0]));
    $GLOBALS['__request']->path = 'register_email_send';
    $GLOBALS['__request']->params = ['email' => 'spam@bad.com'];
    $caught = null;
    try {
        EmailFilterPlugin::checkBeforeDispatch();
    } catch (\think\exception\HttpResponseException $e) {
        $caught = $e->getResponse();
    }
    check('关闭验证码拦截时不拦截', $caught, null);

    echo "== 日志 ==\n";
    check('记录了拦截日志', count($GLOBALS['__logs']) > 0, true);

    echo "\n共 {$total} 项,失败 {$failures} 项\n";
    exit($failures > 0 ? 1 : 0);
}
