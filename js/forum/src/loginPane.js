import { extend } from 'flarum/extend';
import app from 'flarum/app';
import SignUpModal from 'flarum/components/SignUpModal';
import Alert from 'flarum/components/Alert';
import extractText from 'flarum/utils/extractText';
import LogInButtons from 'flarum/components/LogInButtons';
import Button from 'flarum/components/Button';
import SettingsPage from 'flarum/components/SettingsPage';
import FieldSet from 'flarum/components/FieldSet';

export default function () {
  SignUpModal.prototype.body = function() {
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
    
  };
  SignUpModal.prototype.onsubmit = function(e) {
    e.preventDefault();

    this.loading = true;

    const data = this.submitData();

    app.request({
        url: app.forum.attribute('baseUrl') + '/api/issyrocks12/filter/register',
        method: 'POST',
        data,
        errorHandler: this.onerror.bind(this)
      }).then(response => {
        if (response.status == 418) {
          window.alert("it worked!");
        }
      }
      );
    };
  }
