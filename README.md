# 邮箱注册过滤插件(EmailFilter)

用于魔方财务系统的插件,支持两种模式:

- **黑名单**:阻止指定邮箱注册
- **白名单**:仅允许指定邮箱注册

## 安装

1. 把整个 `email_filter` 目录上传到魔方财务的插件目录:

   ```
   <魔方财务根目录>/public/plugins/addons/email_filter/
   ```

2. 进入后台 **插件管理 → 插件列表**,找到「邮箱注册过滤」,点击 **安装**,再点击 **启用**。

3. 点击插件右侧的 **设置** 链接(或插件列表里的「配置」按钮)填写规则。

> 目录名必须是 `email_filter`,插件类名 `EmailFilterPlugin`,命名空间 `addons\email_filter`。
> 魔方靠目录名推导类名,改名会导致内核找不到插件类。

## 配置项

| 配置项 | 说明 |
| --- | --- |
| 是否启用 | 总开关,关闭后不拦截任何注册请求 |
| 拦截模式 | `1` 黑名单(阻止命中规则的邮箱);`2` 白名单(只放行命中规则的邮箱) |
| 邮箱规则 | 每行一条规则,详见下方「规则写法」 |
| 拦截提示语 | 留空使用默认文案,支持 `{email}`(被拦截的邮箱)与 `{rule}`(命中的规则)变量 |
| 后台添加客户 | 是否对后台「添加客户」功能生效 |
| 注册验证码 | 是否在发送注册验证码之前就拦截 |
| 无邮箱注册 | 白名单模式下,手机号注册等没有填写邮箱的请求是否拦截 |

设置页面带一个 **测试邮箱** 输入框,填入邮箱后点「测试」可以立刻看到会不会被拦截。

### 规则写法

| 写法 | 含义 |
| --- | --- |
| `user@example.com` | 精确匹配该邮箱 |
| `@example.com` | 匹配 `example.com` 域名下的所有邮箱 |
| `example.com` | 同上 |
| `*@example.com` | 同上 |
| `*.example.com` | 匹配 `example.com` 及其任意子域名(`a.example.com`、`a.b.example.com` 都算) |

其它约定:

- 匹配不区分大小写。
- 换行、逗号 `,`、分号 `;`、顿号 `、` 都可以作为分隔符。
- 以 `#` 或 `//` 开头的行视为注释。
- 白名单模式下如果规则列表为空,插件不会拦截任何注册(避免误伤全站注册)。

## 生效范围

| 场景 | 拦截点 | 说明 |
| --- | --- | --- |
| 前台邮箱注册 | `before_client_register` | 注册接口在创建账户前触发,被拦截时返回 `{"status":400,"msg":"提示语"}` |
| 前台手机注册 | `before_client_register` | 没有邮箱时按「无邮箱注册」配置处理 |
| 前台注册验证码 | `app_dispatch` | 在发验证码之前就挡住,避免给被禁邮箱发验证码 |
| 后台添加客户 | `client_details_validate` | 后台「添加客户」时同样按规则校验 |

三方登录(OAuth)自动注册走的是 OAuth 插件自己的流程,不在本插件的拦截范围内。

## 实现说明

魔方 3.x 内核里:

- `client_details_validate` 是系统钩子,安装插件时内核会自动扫描插件类的公开方法并登记,
  所以只需要在 `EmailFilterPlugin` 里实现 `clientDetailsValidate()` 方法即可。
- `before_client_register` 和 `app_dispatch` **不是**系统钩子,内核不会自动登记,
  所以由插件根目录的 `hooks.php`(以及同内容的 `hook.php`)在每次请求时手动注册。

`hooks.php` 用闭包调用插件类的静态方法,这样 `app_dispatch`(每个请求都会触发)不会实例化插件类。

## 权限与保存方式

设置页的「保存」请求提交到后台统一的插件配置接口 `/{后台目录}/pl_setting_post`,
与插件列表里「配置」弹窗走的是同一个接口、同一套后台鉴权,不再用插件自己的路由写配置
(插件路由 `/admin/addons` 与后台会话机制的时序不一致,表单提交容易被判成未登录)。

插件安装时**不会**写入后台权限规则。魔方对「不存在权限规则」的插件页面默认放行,
写入规则反而会让非超管管理员直接收到 401。需要收紧权限的话,在后台
「系统设置 → 角色权限」里按需配置即可。

> 如果之前安装过会写权限规则的旧版本,升级后可以执行一次清理:
> `DELETE FROM shd_auth_rule WHERE name = 'addons/email_filter/adminindex/setting';`

## 目录结构

```
email_filter
├── EmailFilterPlugin.php              插件主文件(钩子实现)
├── config.php                         后台「插件设置」表单定义
├── hooks.php / hook.php               非系统钩子的注册文件
├── menu.php                           后台菜单
├── menuclientarea.php                 前台菜单(空)
├── controller/AdminIndexController.php 后台设置页控制器
├── template/admin/setting.tpl         后台设置页模板
├── logic/EmailFilterLogic.php         规则解析与匹配逻辑
├── lang/zh-cn.php, lang/en-us.php     多语言
└── tests/                             自检脚本
```

## 自检脚本

插件目录里带了两个不需要魔方环境就能跑的脚本:

```bash
# 规则解析与匹配逻辑(25 项断言)
php email_filter/tests/EmailFilterLogicTest.php

# 钩子返回结构与配置开关(20 项断言)
php email_filter/tests/HookBehaviourTest.php

# 与内核的对接检查(需要魔方源码路径)
php email_filter/tests/PluginWiringTest.php /path/to/mofang/source
```
