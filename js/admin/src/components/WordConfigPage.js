import Component from "flarum/Component";
import Button from "flarum/components/Button";
import saveSettings from "flarum/utils/saveSettings";
import Switch from 'flarum/components/Switch';
import Alert from "flarum/components/Alert";
import FieldSet from 'flarum/components/FieldSet';

export default class WordConfigPage extends Component {

    init() {
		const settings = app.data.settings;
			
    this.fields = [
      'Words',
			'emailWhenFlagged'
    ]
			
	this.values = {};
			
	this.fields.forEach(key => this.values[key] = m.prop(settings[key]));
    }

    view() {
    return (
      <div className="WordConfigPage">
        <div className="container">
          <form onsubmit={this.onsubmit.bind(this)}>
            <h2>{app.translator.trans('issyrocks12-filter.admin.title')}</h2>
            {FieldSet.component({
              label: app.translator.trans('issyrocks12-filter.admin.filter_label'),
              className: 'WordConfigPage-Settings',
              children: [
                <div className="WordConfigPage-Settings-input">
									<div className="helpText">
              				{app.translator.trans('issyrocks12-filter.admin.help')}
            			</div>
                  <textarea className="FormControl" placeholder={app.translator.trans('issyrocks12-filter.admin.input.placeholder')} rows="6" value={this.values.Words() || null} oninput={m.withAttr('value', this.values.Words)} />
                </div>
              ]
            })}
						{Switch.component({
                state: this.values.emailWhenFlagged(),
                children: app.translator.trans('issyrocks12-filter.admin.input.switch'),
								className: 'WordConfigPage-Settings-switch',
                onchange: this.values.emailWhenFlagged
              })}

            {Button.component({
              type: 'submit',
              className: 'Button Button--primary',
              children: app.translator.trans('core.admin.email.submit_button'),
              loading: this.loading,
              disabled: !this.changed()
            })}
          </form>
        </div>
      </div>
    );
  }


    /**
     * Saves the settings to the database and redraw the page
     *
     * @param e
     */
	  changed() {
    	return this.fields.some(key => this.values[key]() !== app.data.settings[key]);
  	}
    onsubmit(e)
					{
        // prevent the usual form submit behaviour
        e.preventDefault();


        // if the page is already saving, do nothing
        if (this.loading) return;

        // prevents multiple savings
        this.loading = true;
			
    		const settings = {};

				this.fields.forEach(key => settings[key] = this.values[key]());
        // remove previous success popup
        app.alerts.dismiss(this.successAlert);

        saveSettings(settings)
            .then(() => {
                // on success, show popup
                app.alerts.show(this.successAlert = new Alert({
                    type: 'success',
                    children: app.translator.trans('core.admin.basics.saved_message')
                }));
            })
            .catch(() => {
            })
            .then(() => {
                // return to the initial state and redraw the page
                this.loading = false;
                m.redraw();
            });
    }
}
