'use strict';

System.register('issyrocks12/filter/loginPane', ['flarum/extend', 'flarum/app', 'flarum/components/SignUpModal', 'flarum/components/Alert', 'flarum/utils/extract', 'flarum/utils/extractText', 'flarum/components/LogInButtons', 'flarum/components/Button', 'flarum/utils/patchMithril', 'flarum/components/SettingsPage', 'flarum/components/FieldSet', 'flarum/Component', 'flarum/utils/saveSettings', 'flarum/components/Switch'], function (_export, _context) {
  "use strict";

  var extend, app, SignUpModal, Alert, extract, extractText, LogInButtons, Button, patchMithril, SettingsPage, FieldSet, Component, saveSettings, Switch;

  _export('default', function () {
    /**  SignUpModal.prototype.body = function() {
        return [
          this.props.token ? '' : <LogInButtons/>,
    
          <div className="Form Form--centered">
            <div className="Form-group">
              <input className="FormControl" name="username" type="text" placeholder={extractText(app.translator.trans('core.forum.sign_up.username_placeholder'))}
                value={this.username()}
                onchange={m.withAttr('value', this.username)}
                disabled={this.loading} />
            </div>
    
            <div className="Form-group">
              <input className="FormControl" name="email" type="email" placeholder={extractText(app.translator.trans('core.forum.sign_up.email_placeholder'))}
                value={this.email()}
                onchange={m.withAttr('value', this.email)}
                disabled={this.loading} />
            </div>
    
            {this.props.token ? '' : (
              <div className="Form-group">
                <input className="FormControl" name="password" type="password" placeholder={extractText(app.translator.trans('core.forum.sign_up.password_placeholder'))}
                  value={this.password()}
                  onchange={m.withAttr('value', this.password)}
                  disabled={this.loading} />
              </div>
            )}
            
    
            <div className="Form-group">
              <Button
                className="Button Button--primary Button--block"
                type="submit"
                loading={this.loading}>
                {app.translator.trans('core.forum.sign_up.submit_button')}
              </Button>
            </div>
          </div>
        ];
      };
      SignUpModal.prototype.submitData = function() {
        const data = {
          username: this.username(),
          email: this.email()
        };
    
        if (this.props.token) {
          data.token = this.props.token;
        } else {
          data.password = this.password();
        }
    
        if (this.props.avatarUrl) {
          data.avatarUrl = this.props.avatarUrl;
        }
        
        return data;
      }; 
      SignUpModal.prototype.init = function() {
        this.username = m.prop(this.props.username || '');
    
        this.email = m.prop(this.props.email || '');
    
        this.password = m.prop(this.props.password || '');
        
      }; **/
    SignUpModal.prototype.onsubmit = function (e) {
      var _this = this;

      e.preventDefault();

      this.fiel = ['Words'];

      this.val = {};

      this.fiel.forEach(function (key) {
        return _this.val[key] = m.prop(settings[key]);
      });

      this.loading = true;

      var data = this.submitData();
      var Words = this.val.Words().split(', ');
      Words.forEach(function (Word) {
        if (data.username.includes(Word)) {
          windows.alert(app.translator.trans('issyrocks12-filter.forum.filtered1') + Word + app.translator.trans('issyrocks12-filter.forum.filtered2'));
          return;
        } else {

          app.request({
            url: app.forum.attribute('baseUrl') + '/api/user',
            method: 'POST',
            data: data,
            errorHandler: this.onerror.bind(this)
          }).then(function () {
            return window.location.reload();
          }, this.loaded.bind(this));
        }
      });
    };
  });

  return {
    setters: [function (_flarumExtend) {
      extend = _flarumExtend.extend;
    }, function (_flarumApp) {
      app = _flarumApp.default;
    }, function (_flarumComponentsSignUpModal) {
      SignUpModal = _flarumComponentsSignUpModal.default;
    }, function (_flarumComponentsAlert) {
      Alert = _flarumComponentsAlert.default;
    }, function (_flarumUtilsExtract) {
      extract = _flarumUtilsExtract.default;
    }, function (_flarumUtilsExtractText) {
      extractText = _flarumUtilsExtractText.default;
    }, function (_flarumComponentsLogInButtons) {
      LogInButtons = _flarumComponentsLogInButtons.default;
    }, function (_flarumComponentsButton) {
      Button = _flarumComponentsButton.default;
    }, function (_flarumUtilsPatchMithril) {
      patchMithril = _flarumUtilsPatchMithril.default;
    }, function (_flarumComponentsSettingsPage) {
      SettingsPage = _flarumComponentsSettingsPage.default;
    }, function (_flarumComponentsFieldSet) {
      FieldSet = _flarumComponentsFieldSet.default;
    }, function (_flarumComponent) {
      Component = _flarumComponent.default;
    }, function (_flarumUtilsSaveSettings) {
      saveSettings = _flarumUtilsSaveSettings.default;
    }, function (_flarumComponentsSwitch) {
      Switch = _flarumComponentsSwitch.default;
    }],
    execute: function () {}
  };
});;
'use strict';

System.register('issyrocks12/filter/main', ['flarum/extend', 'flarum/app', 'flarum/utils/PostControls', 'flarum/components/CommentPost', 'issyrocks12/filter/loginPane'], function (_export, _context) {
  "use strict";

  var extend, override, app, PostControls, CommentPost, loginPane;
  return {
    setters: [function (_flarumExtend) {
      extend = _flarumExtend.extend;
      override = _flarumExtend.override;
    }, function (_flarumApp) {
      app = _flarumApp.default;
    }, function (_flarumUtilsPostControls) {
      PostControls = _flarumUtilsPostControls.default;
    }, function (_flarumComponentsCommentPost) {
      CommentPost = _flarumComponentsCommentPost.default;
    }, function (_issyrocks12FilterLoginPane) {
      loginPane = _issyrocks12FilterLoginPane.default;
    }],
    execute: function () {

      app.initializers.add('issyrocks12-filter', function () {
        var settings = app.data.settings;
        loginPane();
        override(CommentPost.prototype, 'flagReason', function (original, flag) {
          if (flag.type() === 'issyrocks12-filter.forum.flagger_name') {
            return app.translator.trans('issyrocks12-filter.forum.flagger_name');
          }

          return original(flag);
        });
      }, -20);
    }
  };
});