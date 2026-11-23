<?php
/**
 * 测试用的插件基类桩件
 *
 * 魔方的 app\admin\lib\Plugin 依赖完整框架(视图、配置、数据库),
 * 自检脚本只需要拿到插件类的公开方法,所以这里用最小实现替代。
 */

namespace app\admin\lib;

if (!class_exists('app\\admin\\lib\\Plugin')) {
    class Plugin
    {
        public $info = [];

        public function install()
        {
            return true;
        }

        public function uninstall()
        {
            return true;
        }

        public function getConfig()
        {
            return [];
        }

        public function getDefaultConfig()
        {
            return [];
        }

        public function getName()
        {
            return 'EmailFilter';
        }
    }
}
