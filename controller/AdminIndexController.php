<?php

namespace addons\email_filter\controller;

use addons\email_filter\logic\EmailFilterLogic;
use app\admin\controller\PluginAdminBaseController;

/**
 * 邮箱注册过滤 - 后台设置
 *
 * 注意:本页面的「保存」请求不走插件自己的路由,而是提交到后台统一的
 * 插件配置接口 /{后台目录}/pl_setting_post(与插件列表里的「配置」弹窗完全一致),
 * 这样鉴权、CSRF 防护、配置写回逻辑都复用内核,避免插件路由与后台会话机制不一致。
 */
class AdminIndexController extends PluginAdminBaseController
{
    /**
     * @var array 插件配置
     */
    private $_config = [];

    public function initialize()
    {
        parent::initialize();
        $this->_config = $this->loadConfig();
    }

    /**
     * 读取插件配置(数据库配置优先,缺省值来自 config.php)
     *
     * @return array
     */
    private function loadConfig()
    {
        $plugin = $this->getPlugin();
        $config = $plugin->getConfig();
        if (!is_array($config)) {
            $config = [];
        }
        return array_merge($plugin->getDefaultConfig(), $config);
    }

    /**
     * 设置页
     */
    public function setting()
    {
        // 测试邮箱匹配结果
        $testEmail = trim((string)$this->request->param('test_email', ''));
        if ($testEmail !== '') {
            $result = EmailFilterLogic::judge(
                $testEmail,
                $this->_config['email_list'],
                $this->_config['mode'],
                $this->_config['block_message']
            );
            $this->assign('testEmail', $testEmail);
            $this->assign('testBlocked', $result['blocked'] ? 1 : 0);
            $this->assign('testRule', $result['rule']);
            $this->assign('testMessage', $result['message']);
        }

        $this->assign('pluginId', $this->getPluginId());
        $this->assign('pluginTitle', $this->getPluginTitle());
        $this->assign('system', $this->_config);
        $this->assign('Title', '邮箱注册过滤设置');
        return $this->fetch('/setting');
    }

    /**
     * 插件在 shd_plugin 表里的主键,保存时需要传给内核的配置接口
     *
     * @return int
     */
    private function getPluginId()
    {
        return intval(\think\Db::name('plugin')->where('name', 'EmailFilter')->value('id'));
    }

    /**
     * 插件当前的后台标题,保存时原样带回,避免被默认值覆盖
     *
     * @return string
     */
    private function getPluginTitle()
    {
        $title = \think\Db::name('plugin')->where('name', 'EmailFilter')->value('title');
        return $title ?: '邮箱注册过滤';
    }
}
