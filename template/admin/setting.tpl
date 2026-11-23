  <section class="admin-main">
    <div class="container-fluid">
      <div class="page-container">
        <div class="card">
          <div class="card-body">
            <div class="card-title row"> <div style="padding:0 15px;">{$Title}</div>
              <div class="col-lg-8 col-md-12 col-sm-12">
                {foreach $PluginsAdminMenu as $v}
                  {if $v['custom']}
                    <span  class="ml-2"><a  class="h5" href="{$v.url}" target="_blank">{$v.name}</a></span>
                  {else/}
                    <span  class="ml-2"> <a  class="h5" href="{$v.url}">{$v.name}</a></span>
                  {/if}
                {/foreach}
              </div>
            </div>
            <div class="tab-content mt-4">
              <div class="table-body">
                <form method="post" class="form" id="emailFilterForm" action="/{$Admin}/pl_setting_post">
                  <input type="hidden" name="id" value="{$pluginId}">
                  <input type="hidden" name="module" value="addons">
                  <div class="form-group row">
                    <div class="col-sm-3"><label>是否启用</label></div>
                    <div class="col-sm-5">
                      <select class="form-control" name="config[is_open]">
                        <option value="0" {if $system.is_open=='0'}selected{/if}>关闭</option>
                        <option value="1" {if $system.is_open=='1'}selected{/if}>开启</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-3"><label>拦截模式</label></div>
                    <div class="col-sm-5">
                      <select class="form-control" name="config[mode]">
                        <option value="1" {if $system.mode=='1'}selected{/if}>阻止指定邮箱注册(黑名单)</option>
                        <option value="2" {if $system.mode=='2'}selected{/if}>仅允许指定邮箱注册(白名单)</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-3"><label>邮箱规则</label></div>
                    <div class="col-sm-9">
                      <textarea class="form-control" name="config[email_list]" rows="10" placeholder="每行一条规则">{$system.email_list|htmlspecialchars}</textarea>
                      <small class="form-text text-muted">
                        以 # 或 // 开头的行视为注释。支持写法:
                        user@example.com 精确匹配、@example.com 整个域名、example.com 整个域名、
                        *@example.com 整个域名、*.example.com 域名及其子域名。
                      </small>
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-3"><label>拦截提示语</label></div>
                    <div class="col-sm-9">
                      <input class="form-control" type="text" name="config[block_message]" value="{$system.block_message|htmlspecialchars}" placeholder="留空使用默认提示语,支持变量 {email} 与 {rule}">
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-3"><label>后台添加客户</label></div>
                    <div class="col-sm-5">
                      <select class="form-control" name="config[affect_admin]">
                        <option value="0" {if $system.affect_admin=='0'}selected{/if}>不拦截</option>
                        <option value="1" {if $system.affect_admin=='1'}selected{/if}>同样拦截</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-3"><label>注册验证码</label></div>
                    <div class="col-sm-5">
                      <select class="form-control" name="config[affect_code]">
                        <option value="0" {if $system.affect_code=='0'}selected{/if}>不拦截</option>
                        <option value="1" {if $system.affect_code=='1'}selected{/if}>发送前拦截</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-group row">
                    <div class="col-sm-3"><label>无邮箱注册</label></div>
                    <div class="col-sm-9">
                      <select class="form-control" name="config[block_empty_email]">
                        <option value="0" {if $system.block_empty_email=='0'}selected{/if}>不拦截</option>
                        <option value="1" {if $system.block_empty_email=='1'}selected{/if}>拦截</option>
                      </select>
                      <small class="form-text text-muted">白名单模式下,手机号注册等没有填写邮箱的注册请求是否拦截。</small>
                    </div>
                  </div>
                  <input type="hidden" name="config[module_name]" value="{$pluginTitle|htmlspecialchars}">
                  <div class="form-group row">
                    <div class="col-sm-10">
                      <button type="submit" class="btn btn-primary w-md" id="emailFilterSave">保存更改</button>
                      <button type="button" class="btn btn-outline-secondary w-md" onclick="javascript:location.reload();">取消更改</button>
                      <span id="emailFilterTip" class="ml-2"></span>
                    </div>
                  </div>
                </form>
                <hr>
                <form method="get" class="form" action="/{$Admin}/addons">
                  <input type="hidden" name="_plugin" value="email_filter">
                  <input type="hidden" name="_controller" value="admin_index">
                  <input type="hidden" name="_action" value="setting">
                  <div class="form-group row">
                    <div class="col-sm-3"><label>测试邮箱</label></div>
                    <div class="col-sm-5">
                      <input class="form-control" type="text" name="test_email" value="{$testEmail|htmlspecialchars}" placeholder="输入邮箱验证匹配结果">
                    </div>
                    <div class="col-sm-2">
                      <button type="submit" class="btn btn-secondary w-md">测试</button>
                    </div>
                  </div>
                </form>
                {if $testEmail}
                  <div class="form-group row">
                    <div class="col-sm-12">
                      {if $testBlocked}
                        <div class="alert alert-danger">{$testEmail|htmlspecialchars} 将被拦截。{$testMessage|htmlspecialchars}{if $testRule}(命中规则:{$testRule|htmlspecialchars}){/if}</div>
                      {else/}
                        <div class="alert alert-success">{$testEmail|htmlspecialchars} 允许注册。</div>
                      {/if}
                    </div>
                  </div>
                {/if}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <script>
    (function () {
      var form = document.getElementById('emailFilterForm');
      var btn = document.getElementById('emailFilterSave');
      var tip = document.getElementById('emailFilterTip');
      if (!form) { return; }

      function show(text, ok) {
        tip.innerHTML = '<span style="color:' + (ok ? '#3fbf70' : '#dc3545') + '">' + text + '</span>';
      }

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        btn.disabled = true;
        show('保存中...', true);

        var body = new URLSearchParams();
        var inputs = form.querySelectorAll('input[name], select[name], textarea[name]');
        for (var i = 0; i < inputs.length; i++) {
          body.append(inputs[i].name, inputs[i].value);
        }

        fetch(form.action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: body.toString()
        }).then(function (res) {
          return res.text();
        }).then(function (text) {
          var data = null;
          try { data = JSON.parse(text); } catch (e) { data = null; }
          btn.disabled = false;
          if (data && Number(data.status) === 200) {
            show('保存成功', true);
            setTimeout(function () { location.reload(); }, 600);
            return;
          }
          var msg = (data && data.msg) ? data.msg : ('保存失败,返回内容:' + text.substring(0, 200));
          show(msg, false);
        }).catch(function (err) {
          btn.disabled = false;
          show('保存请求失败:' + err, false);
        });
      });
    })();
  </script>
